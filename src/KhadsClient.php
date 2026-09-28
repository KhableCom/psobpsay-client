<?php

namespace Khable\KhadsClient;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
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
 * @phpstan-type Picture array{src: string, srcset: string|null, width: int|null, height: int|null}
 * @phpstan-type SpaceAd array{id: string, headline: string|null, body: string|null, cta: string|null, brand: string|null, image: string|null, impressionUrl: string, clickUrl: string, frequency: Frequency|null, template: string|null, disclosure: string, assets: array<string, Picture>, logo: Picture|null, video: array{src: string, poster: string|null, durationMs: int|null}|null, promo: array{code: string, terms: string|null}|null}
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

    /** @var list<string> */
    private const Disclosures = ['sponsored', 'promoted', 'presented', 'paid', 'ad'];

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

        return $this->slots($page, $zones, ...$this->visitor($request));
    }

    /**
     * Every templated space a page shows, by the slot ids the site gave them, for the page
     * the request is for. Each answer is the list of ads the space draws: one for a card,
     * several for a rail, none when it goes unfilled (draw nothing then, not a gap).
     *
     * A slot id is resolved to its zone through `khads.zones` when it is listed there, and
     * otherwise through the ad server's manifest, so spaces made with `khads:sync` need no
     * zone keys in the environment.
     *
     * @return array<string, list<SpaceAd>>
     */
    public function spaces(Request $request, string ...$slotIds): array
    {
        $page = $request->url();
        $empty = array_fill_keys($slotIds, []);
        $zones = $this->zoneKeys($page, $slotIds);
        $answer = $this->serve($page, $zones, ...$this->visitor($request));

        if ($answer === null) {
            return $empty;
        }

        return collect($empty)->map(function (array $_, string $id) use ($answer): array {
            $slot = is_array($answer[$id] ?? null) ? $answer[$id] : [];
            // An ad server from before multi-ad spaces answers `ad` alone.
            $ads = is_array($slot['ads'] ?? null) ? $slot['ads'] : [$slot['ad'] ?? null];

            return array_values(array_filter(array_map($this->shapeSpaceAd(...), $ads)));
        })->all();
    }

    /**
     * The zone key for each slot id on the page's site, or null for one this site has not
     * got (or has paused), which is then simply not asked for.
     *
     * @param  list<string>  $slotIds
     * @return array<string, string|null>
     */
    public function zoneKeys(string $page, array $slotIds): array
    {
        $keys = [];
        $manifestZones = null;
        // Looked up as one key: slot ids have dots in them, which config() would nest.
        $zones = (array) config('khads.zones', []);

        foreach ($slotIds as $slotId) {
            $configured = $zones[$slotId] ?? null;

            if (is_string($configured) && $configured !== '') {
                $keys[$slotId] = $configured;

                continue;
            }

            $manifestZones ??= $this->manifestZones($page);
            $keys[$slotId] = $manifestZones[$slotId] ?? null;
        }

        return $keys;
    }

    /**
     * What the ad server knows about this site's inventory: its sites, each space by slot
     * id, and the template catalogue. Cached for `khads.manifest_seconds`; empty, and not
     * cached, while the ad server cannot be reached or refuses the key.
     *
     * @return array<string, mixed>
     */
    public function manifest(): array
    {
        if (! $this->isConfigured() || Cache::has($this->circuitKey())) {
            return [];
        }

        $cached = Cache::get($this->cacheKey('manifest'));

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $manifest = $this->http()->get('/api/v1/publisher/manifest')->throw()->json();
        } catch (ConnectionException $exception) {
            return $this->openCircuit($exception, []);
        } catch (RequestException $exception) {
            return $this->refused($exception, []);
        }

        if (! is_array($manifest) || ! is_array($manifest['sites'] ?? null)) {
            return [];
        }

        Cache::put($this->cacheKey('manifest'), $manifest, max(1, (int) config('khads.manifest_seconds', 300)));

        return $manifest;
    }

    /** Drop the cached manifest, so a space just created is served on the next page view. */
    public function forgetManifest(): void
    {
        Cache::forget($this->cacheKey('manifest'));
    }

    /**
     * Create the spaces declared in `khads.spaces` on this site in the ad server. Existing
     * slot ids are left as they are there.
     *
     * @param  array<string, array<string, mixed>>  $spaces  slot id => {template, platform?, max_ads?, name?}
     * @return array<string, mixed> the ad server's answer: created, existing and the site
     *
     * @throws RequestException|ConnectionException
     */
    public function syncSpaces(string $site, array $spaces): array
    {
        $response = $this->http()->post('/api/v1/publisher/zones', [
            'site' => $site,
            'spaces' => collect($spaces)->map(fn (array $space, string $slotId): array => ['slot_id' => $slotId, ...$space])->values()->all(),
        ])->throw();

        $this->forgetManifest();

        return (array) $response->json();
    }

    /**
     * Slot id => zone key for the manifest's site matching the page: its exact bare domain
     * or `www.`, the same rule the ad server serves by. Paused spaces are left out.
     *
     * @return array<string, string>
     */
    private function manifestZones(string $page): array
    {
        $host = strtolower((string) parse_url($page, PHP_URL_HOST));
        $zones = [];

        foreach ((array) ($this->manifest()['sites'] ?? []) as $site) {
            $domain = is_array($site) && is_string($site['domain'] ?? null) ? strtolower($site['domain']) : null;

            if ($domain === null || ($host !== $domain && $host !== 'www.'.$domain)) {
                continue;
            }

            foreach ((array) ($site['zones'] ?? []) as $zone) {
                if (is_array($zone) && is_string($zone['slot_id'] ?? null) && is_string($zone['key'] ?? null) && ($zone['is_active'] ?? false) === true) {
                    $zones[$zone['slot_id']] = $zone['key'];
                }
            }
        }

        return $zones;
    }

    /**
     * The visitor's country, privacy choice and capped-out campaigns, read from the page
     * request.
     *
     * @return array{0: string|null, 1: bool, 2: list<string>}
     */
    private function visitor(Request $request): array
    {
        $header = (string) config('khads.country_header', 'CF-IPCountry');
        $restricted = $request->header('Sec-GPC') === '1' || $request->header('DNT') === '1';

        return [
            $header === '' ? null : self::country($request->header($header)),
            $restricted,
            $restricted ? [] : self::seen($request->header(self::SeenHeader)),
        ];
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
        $empty = array_fill_keys(array_keys(array_filter($slots, fn (?string $zone): bool => filled($zone))), null);
        $answer = $this->serve($page, $slots, $country, $restricted, $seen);

        return collect($empty)->map(fn ($_, string $id): ?array => $this->shape(is_array($answer) ? ($answer[$id]['ad'] ?? null) : null))->all();
    }

    /**
     * The one serve call behind both {@see slots()} and {@see spaces()}: the answer's
     * `slots` object, or null when nothing was asked, the ad server is off or down, or it
     * refused the page.
     *
     * @param  array<string, string|null>  $slots  slot id => zone public key
     * @param  list<string>  $seen
     * @return array<string, mixed>|null
     */
    private function serve(string $page, array $slots, ?string $country, bool $restricted, array $seen): ?array
    {
        $slots = array_filter($slots, fn (?string $zone): bool => filled($zone));

        if ($slots === [] || ! $this->isConfigured() || Cache::has($this->circuitKey())) {
            return null;
        }

        try {
            $response = $this->http()
                ->post('/api/v1/serve', array_filter([
                    'page' => $page,
                    'slots' => collect($slots)->map(fn (string $zone, string $id): array => ['id' => $id, 'zone' => $zone])->values()->all(),
                    'country' => $country,
                    'privacy' => $restricted ? 'restricted' : 'standard',
                    'seen' => $restricted ? null : implode(',', $seen),
                ]))
                ->throw();
        } catch (ConnectionException $exception) {
            return $this->openCircuit($exception, null);
        } catch (RequestException $exception) {
            return $this->refused($exception, null);
        }

        $answer = $response->json('slots');

        return is_array($answer) ? $answer : null;
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl((string) config('khads.base_url'))
            ->withToken((string) config('khads.api_key'))
            ->acceptJson()
            ->connectTimeout($this->connectTimeoutSeconds())
            ->timeout($this->timeoutSeconds());
    }

    /**
     * @template TEmpty
     *
     * @param  TEmpty  $empty
     * @return TEmpty
     */
    private function openCircuit(Throwable $exception, mixed $empty): mixed
    {
        Cache::add($this->circuitKey(), true, max(1, (int) config('khads.circuit_seconds')));
        report($exception);

        return $empty;
    }

    /**
     * An outage opens the circuit; a refusal is this site's own mistake, so it empties only
     * this call and is reported at most hourly.
     *
     * @template TEmpty
     *
     * @param  TEmpty  $empty
     * @return TEmpty
     */
    private function refused(RequestException $exception, mixed $empty): mixed
    {
        $status = $exception->response->status();

        if ($status >= 500 || $status === 429) {
            return $this->openCircuit($exception, $empty);
        }

        if (Cache::add($this->cacheKey('refused'), $status, self::RefusalReportSeconds)) {
            report($exception);
        }

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
            'configured' => $this->isConfigured() && ($zones !== [] || (array) config('khads.spaces', []) !== []),
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
     * A card's fields plus what a space template draws with: its template and disclosure
     * word, the advertiser's cropped pictures, a video and a promo code. The same rule
     * holds: a picture or video whose URL is not plain http(s) is dropped.
     *
     * @return SpaceAd|null
     */
    private function shapeSpaceAd(mixed $ad): ?array
    {
        $card = $this->shape($ad);

        if ($card === null || ! is_array($ad)) {
            return null;
        }

        $assets = [];

        foreach ((array) ($ad['assets'] ?? []) as $role => $picture) {
            if (is_string($role) && ($shaped = $this->picture($picture)) !== null) {
                $assets[$role] = $shaped;
            }
        }

        $video = is_array($ad['video'] ?? null) ? $ad['video'] : [];
        $promo = is_array($ad['promo'] ?? null) ? $ad['promo'] : [];

        return [
            ...$card,
            'template' => is_string($ad['template'] ?? null) ? $ad['template'] : null,
            'disclosure' => in_array($ad['disclosure'] ?? null, self::Disclosures, true) ? $ad['disclosure'] : 'sponsored',
            'assets' => $assets,
            'logo' => $this->picture($ad['logo'] ?? null),
            'video' => $this->httpUrl($video['src'] ?? null) === null ? null : [
                'src' => $video['src'],
                'poster' => $this->httpUrl($video['poster'] ?? null),
                'durationMs' => is_int($video['duration_ms'] ?? null) ? $video['duration_ms'] : null,
            ],
            'promo' => is_string($promo['code'] ?? null) && $promo['code'] !== '' ? [
                'code' => Str::limit($promo['code'], 64, ''),
                'terms' => is_string($promo['terms'] ?? null) ? Str::limit($promo['terms'], 400, '…') : null,
            ] : null,
        ];
    }

    /**
     * A srcset is kept only when every candidate in it is a plain http(s) URL.
     *
     * @return Picture|null
     */
    private function picture(mixed $picture): ?array
    {
        if (! is_array($picture) || ($src = $this->httpUrl($picture['src'] ?? null)) === null) {
            return null;
        }

        $srcset = is_string($picture['srcset'] ?? null) ? $picture['srcset'] : null;

        if ($srcset !== null) {
            foreach (explode(',', $srcset) as $candidate) {
                if ($this->httpUrl(strtok(trim($candidate), ' ') ?: null) === null) {
                    $srcset = null;

                    break;
                }
            }
        }

        return [
            'src' => $src,
            'srcset' => $srcset,
            'width' => is_int($picture['width'] ?? null) ? $picture['width'] : null,
            'height' => is_int($picture['height'] ?? null) ? $picture['height'] : null,
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
