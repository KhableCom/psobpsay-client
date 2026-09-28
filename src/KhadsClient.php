<?php

namespace Khable\KhadsClient;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Asks the ad server for every placement on a page in one call.
 *
 * Fails empty, never loud: a timeout, an error, or an answer in a shape this code does
 * not recognise all become "no ad", and after one failure the server is left alone for
 * a while so a down ad server costs one timeout rather than one per page view.
 * A refusal is different: a wrong key or a request the ad server will not accept is
 * this site's mistake, not an outage, so it empties only that page and is reported
 * at most once an hour instead of switching every page's ads off.
 *
 * @phpstan-type Frequency array{key: string, cap: int}
 * @phpstan-type SponsoredAd array{id: string, headline: string|null, body: string|null, cta: string|null, brand: string|null, image: string|null, impressionUrl: string, clickUrl: string, frequency: Frequency|null}
 * @phpstan-type AdsStatus array{enabled: bool, configured: bool, zones: list<string>, circuitOpen: bool, refusedStatus: int|null, consoleUrl: string|null}
 */
class KhadsClient
{
    /** Set by the published `forwardCappedCampaigns()` hook on the page's own requests. */
    public const SeenHeader = 'X-Khads-Seen';

    private const RefusalReportSeconds = 3600;

    private const CampaignKeyPattern = '/\Acmp_[a-z0-9]{32}\z/';

    /** @var list<string> */
    private const SupportedFormats = ['native', 'in_feed', 'text'];

    /**
     * Every placement a page shows, by the names this site gave them in
     * `khads.zones`, with the visitor's country read from the edge header the
     * site sits behind. A placement with no zone configured is simply not asked
     * for and is absent from the answer.
     *
     * A visitor who sends Global Privacy Control or Do Not Track is served as
     * restricted, exactly as the khads tag would serve them. Anyone else's capped-out
     * campaigns, forwarded by the page in {@see SeenHeader}, are passed on so a
     * campaign's daily frequency cap holds here too.
     *
     * @return array<string, SponsoredAd|null>
     */
    public function slotsFor(Request $request, string $page, string ...$placements): array
    {
        $zones = [];

        foreach ($placements as $placement) {
            $zones[$placement] = config('khads.zones.'.$placement);
        }

        $header = (string) config('khads.country_header', 'CF-IPCountry');
        $restricted = $request->header('Sec-GPC') === '1' || $request->header('DNT') === '1';

        return $this->slots(
            $page,
            $zones,
            $header === '' ? null : self::country($request->header($header)),
            $restricted,
            $restricted ? [] : self::seen($request->header(self::SeenHeader)),
        );
    }

    /**
     * The header comes from the browser, so only well-formed campaign keys go on: the ad
     * server refuses the whole page over one bad entry. Newest last, capped at the 50 it
     * accepts.
     *
     * @return list<string>
     */
    private static function seen(?string $value): array
    {
        $keys = array_filter(
            array_map(trim(...), explode(',', (string) $value)),
            fn (string $key): bool => preg_match(self::CampaignKeyPattern, $key) === 1,
        );

        return array_slice(array_values(array_unique($keys)), -50);
    }

    /**
     * Only a real ISO-3166 code is worth sending. Edges also emit placeholders — `T1`
     * for Tor, `XX` for unknown — and the ad server refuses anything but two letters,
     * which would fail the whole page and open the circuit for every visitor after.
     */
    private static function country(?string $value): ?string
    {
        $value = strtoupper(trim((string) $value));

        return preg_match('/\A[A-Z]{2}\z/', $value) === 1 && $value !== 'XX' ? $value : null;
    }

    /**
     * @param  array<string, string|null>  $slots  placement name => zone public key
     * @param  bool  $restricted  the visitor asked not to be tracked
     * @param  list<string>  $seen  campaign keys the visitor has capped out on today
     * @return array<string, SponsoredAd|null> one entry per requested placement
     */
    public function slots(string $page, array $slots, ?string $country = null, bool $restricted = false, array $seen = []): array
    {
        $slots = array_filter($slots, fn (?string $zone): bool => filled($zone));
        $empty = array_fill_keys(array_keys($slots), null);

        if ($slots === [] || ! $this->isConfigured() || Cache::has($this->circuitKey())) {
            return $empty;
        }

        try {
            $response = Http::baseUrl((string) config('khads.base_url'))
                ->withToken((string) config('khads.api_key'))
                ->acceptJson()
                ->connectTimeout($this->connectTimeoutSeconds())
                ->timeout($this->timeoutSeconds())
                ->post('/api/v1/serve', array_filter([
                    'page' => $page,
                    'slots' => collect($slots)->map(fn (string $zone, string $id): array => ['id' => $id, 'zone' => $zone])->values()->all(),
                    'country' => $country,
                    'privacy' => $restricted ? 'restricted' : 'standard',
                    'seen' => $restricted ? null : implode(',', $seen),
                ]))
                ->throw();
        } catch (ConnectionException $exception) {
            return $this->openCircuit($exception, $empty);
        } catch (RequestException $exception) {
            $status = $exception->response->status();

            if ($status >= 500 || $status === 429) {
                return $this->openCircuit($exception, $empty);
            }

            if (Cache::add($this->cacheKey('refused'), $status, self::RefusalReportSeconds)) {
                report($exception);
            }

            return $empty;
        }

        $answer = $response->json('slots');

        return collect($empty)->map(fn ($_, string $id): ?array => $this->shape(is_array($answer) ? ($answer[$id]['ad'] ?? null) : null))->all();
    }

    /**
     * @param  array<string, null>  $empty
     * @return array<string, null>
     */
    private function openCircuit(Throwable $exception, array $empty): array
    {
        Cache::add($this->circuitKey(), true, max(1, (int) config('khads.circuit_seconds')));
        report($exception);

        return $empty;
    }

    /**
     * Read-only operational state for an admin dashboard. Never calls khads.
     *
     * `refusedStatus` is the HTTP status of a refusal in the past hour: 401 for a wrong
     * key, 422 for a request the ad server would not accept. Rotating the key clears it.
     *
     * @return AdsStatus
     */
    public function status(): array
    {
        $zones = [];

        foreach ((array) config('khads.zones') as $name => $zone) {
            if (is_string($name) && is_string($zone) && $zone !== '') {
                $zones[] = $name;
            }
        }

        $refused = Cache::get($this->cacheKey('refused'));

        return [
            'enabled' => (bool) config('khads.enabled'),
            'configured' => $this->isConfigured() && $zones !== [],
            'zones' => $zones,
            'circuitOpen' => Cache::has($this->circuitKey()),
            'refusedStatus' => is_numeric($refused) ? (int) $refused : null,
            'consoleUrl' => $this->httpUrl(config('khads.console_url')),
        ];
    }

    /** Public sign-up destination, or null while the network stays closed. */
    public function advertiserUrl(): ?string
    {
        if (! config('khads.advertise_enabled')) {
            return null;
        }

        return $this->httpUrl(config('khads.advertiser_url'));
    }

    /** @internal Public so the budget arithmetic can be tested; not part of the contract. */
    public function timeoutSeconds(): float
    {
        return max(50, (int) config('khads.timeout_ms')) / 1000;
    }

    /**
     * Never longer than the whole budget: connecting is one part of spending it.
     *
     * @internal
     */
    public function connectTimeoutSeconds(): float
    {
        return min($this->timeoutSeconds(), max(50, (int) config('khads.connect_timeout_ms')) / 1000);
    }

    /**
     * Sister sites often share one cache. Keyed by the ad server and the site's own key,
     * one site's failures open only its own circuit, and a rotated key starts clean.
     * Only the key's prefix is used, so the cache never holds the secret.
     *
     * @internal Public so tests can open the circuit.
     */
    public function circuitKey(): string
    {
        return $this->cacheKey('circuit-open');
    }

    private function cacheKey(string $name): string
    {
        $site = md5((string) config('khads.base_url').'|'.substr((string) config('khads.api_key'), 0, 12));

        return 'khads:'.$site.':'.$name;
    }

    private function isConfigured(): bool
    {
        return (bool) config('khads.enabled') && filled(config('khads.base_url')) && filled(config('khads.api_key'));
    }

    /**
     * Keep only what the page renders, so a change on the ad server's side cannot leak
     * new fields into the page props. Every URL must be plain http(s): an ad whose click
     * or impression URL is anything else is dropped, and a non-http image is omitted, so
     * even a compromised ad server cannot put a javascript: link into the page.
     *
     * @return SponsoredAd|null
     */
    private function shape(mixed $ad): ?array
    {
        if (! is_array($ad) || ! in_array($ad['format'] ?? null, self::SupportedFormats, true)) {
            return null;
        }

        $url = fn (string $key): ?string => $this->httpUrl($ad[$key] ?? null);

        if (! is_string($ad['id'] ?? null) || $url('impression_url') === null || $url('click_url') === null) {
            return null;
        }

        $string = static function (string $key, int $limit) use ($ad): ?string {
            if (! is_string($ad[$key] ?? null) || trim($ad[$key]) === '') {
                return null;
            }

            return Str::limit(trim($ad[$key]), $limit, '…');
        };

        $headline = $string('headline', 180);
        $body = $string('body', 400);
        $cta = $string('cta', 80);
        $brand = $string('brand', 100);
        $image = $url('image');

        if ($headline === null && $body === null && $cta === null && $brand === null && $image === null) {
            return null;
        }

        return [
            'id' => Str::limit($ad['id'], 128, ''),
            'headline' => $headline,
            'body' => $body,
            'cta' => $cta,
            'brand' => $brand,
            'image' => $image,
            'impressionUrl' => $ad['impression_url'],
            'clickUrl' => $ad['click_url'],
            'frequency' => self::frequency($ad['frequency'] ?? null),
        ];
    }

    /**
     * What the card counts toward a campaign's daily cap. Anything malformed is dropped,
     * which serves that visitor an extra impression rather than losing the ad.
     *
     * @return Frequency|null
     */
    private static function frequency(mixed $value): ?array
    {
        if (! is_array($value) || ! is_string($value['key'] ?? null) || preg_match(self::CampaignKeyPattern, $value['key']) !== 1) {
            return null;
        }

        return is_int($value['cap'] ?? null) && $value['cap'] >= 1 ? ['key' => $value['key'], 'cap' => $value['cap']] : null;
    }

    private function httpUrl(mixed $value): ?string
    {
        if (! is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true)
            ? $value
            : null;
    }
}
