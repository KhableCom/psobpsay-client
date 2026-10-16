<?php

namespace Khable\PsobPsayClient;

use Illuminate\Support\Collection;
use Khable\PsobPsayClient\Support\SiteMatcher;

/**
 * Shared inventory diagnosis for `psobpsay:check` and the host admin dashboard.
 *
 * Structural problems (missing keys, inactive inventory, unsupported renderers)
 * are errors. Absence of recent traffic is a warning by default so a newly
 * connected site can pass setup before its first request; pass
 * `$strictTraffic` to treat missing traffic as a deploy failure.
 *
 * @phpstan-type Finding array{id: string|null, severity: 'error'|'warning', code: string, message: string, next: string}
 * @phpstan-type SpaceRow array{
 *     id: string,
 *     zone_key: string|null,
 *     slot_id: string|null,
 *     name: string|null,
 *     format: string|null,
 *     template: string|null,
 *     is_active: bool|null,
 *     requests_24h: int|null,
 *     fill_rate_24h: float|null,
 *     last_request_at: string|null,
 *     reservation: array{campaign_brand: string|null, starts_at: string|null, ends_at: string|null}|null,
 *     snippets: array<string, string>|null,
 *     ok: bool,
 *     findings: list<Finding>
 * }
 * @phpstan-type Diagnostics array{
 *     ok: bool,
 *     structural_ok: bool,
 *     reachable: bool,
 *     site: array{domain: string, can_serve: bool, reason: string|null, status: string|null, verified: bool|null}|null,
 *     findings: list<Finding>,
 *     spaces: list<SpaceRow>,
 *     checklist: list<array{key: string, ok: bool, label: string, detail: string|null}>
 * }
 */
class SpaceDiagnostics
{
    public function __construct(private readonly PsobPsayClient $client) {}

    /**
     * @param  list<string>  $extraSlotIds
     * @return Diagnostics
     */
    public function build(?string $siteDomain = null, array $extraSlotIds = [], bool $strictTraffic = false): array
    {
        $local = $this->client->status();

        if (! $local['enabled'] || ! filled(config('psobpsay.base_url')) || ! filled(config('psobpsay.api_key'))) {
            $finding = $this->finding(null, 'error', 'not_configured',
                'Ads are not configured.',
                'Set ADS_ENABLED, ADS_BASE_URL and ADS_API_KEY on the server, then try again.');

            return $this->empty(false, false, [$finding]);
        }

        if ($local['circuitOpen']) {
            $finding = $this->finding(null, 'error', 'circuit_open',
                'The ad server circuit is open after a recent outage.',
                'Wait for the circuit to close, or fix connectivity and clear the cache key if the outage has ended.');

            return $this->empty(false, false, [$finding]);
        }

        if ($local['refusedStatus'] !== null) {
            $finding = $this->finding(null, 'error', 'refused',
                'The ad server refused the API key or last request (HTTP '.$local['refusedStatus'].').',
                $local['refusedStatus'] === 401
                    ? 'Rotate or replace ADS_API_KEY with a key issued for this site.'
                    : 'Check the request shape and site configuration in the publisher console.');

            return $this->empty(false, false, [$finding]);
        }

        $manifest = $this->client->manifest();

        if ($manifest === [] || ! is_array($manifest['sites'] ?? null)) {
            $finding = $this->finding(null, 'error', 'manifest_unavailable',
                'Cannot read the publisher manifest.',
                'Check ADS_BASE_URL, ADS_API_KEY and connectivity. Invalid credentials and unsupported responses both look empty here.');

            return $this->empty(false, false, [$finding]);
        }

        $fallbackHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $site = SiteMatcher::siteForConfiguredDomain(
            $manifest['sites'],
            $siteDomain ?? config('psobpsay.site'),
            is_string($fallbackHost) ? $fallbackHost : null,
        );
        $domain = SiteMatcher::normalizeConfiguredDomain($siteDomain ?? config('psobpsay.site'))
            ?? SiteMatcher::normalizeConfiguredDomain(is_string($fallbackHost) ? $fallbackHost : null)
            ?? 'unknown';

        if ($site === null) {
            $finding = $this->finding(null, 'error', 'site_unknown',
                'The key has no access to site '.$domain.'.',
                'Issue a site-scoped key for this domain, or use a workspace key that includes it.');

            return $this->empty(true, false, [$finding]);
        }

        $findings = [];
        if (($site['can_serve'] ?? false) !== true) {
            $reason = is_string($site['reason'] ?? null) ? $site['reason'] : 'site_inactive';
            $findings[] = $this->finding(null, 'error', 'site_cannot_serve',
                'Site cannot serve: '.$reason,
                match ($reason) {
                    'site_unverified' => 'Complete domain ownership verification in the publisher console.',
                    'site_inactive', 'publisher_inactive' => 'Ask the network operator to activate this publisher site.',
                    'paused' => 'Serving is paused network-wide; wait for the operator to re-enable it.',
                    default => 'Open the publisher console and resolve the site status.',
                });
        }

        $zones = collect(is_array($site['zones'] ?? null) ? $site['zones'] : []);
        $configured = (array) config('psobpsay.zones', []);
        $spacesConfig = (array) config('psobpsay.spaces', []);
        $ids = array_values(array_unique([
            ...array_keys($configured),
            ...array_keys($spacesConfig),
            ...array_values(array_filter($extraSlotIds, fn ($id): bool => is_string($id) && $id !== '')),
        ]));

        $spaces = [];
        foreach ($ids as $id) {
            if (! is_string($id) || $id === '') {
                continue;
            }

            $row = $this->spaceRow($id, $configured[$id] ?? null, $spacesConfig[$id] ?? null, $zones, $strictTraffic);
            $spaces[] = $row;
            $findings = [...$findings, ...$row['findings']];
        }

        $errors = array_values(array_filter($findings, fn (array $f): bool => $f['severity'] === 'error'));
        $structuralOk = $errors === [] && ($site['can_serve'] ?? false) === true;
        // Traffic warnings alone do not fail structural setup unless strict mode elevates them.
        $ok = $errors === [];

        return [
            'ok' => $ok,
            'structural_ok' => $structuralOk,
            'reachable' => true,
            'site' => [
                'domain' => is_string($site['domain'] ?? null) ? $site['domain'] : $domain,
                'can_serve' => ($site['can_serve'] ?? false) === true,
                'reason' => is_string($site['reason'] ?? null) ? $site['reason'] : null,
                'status' => is_string($site['status'] ?? null) ? $site['status'] : null,
                'verified' => isset($site['verified']) ? (bool) $site['verified'] : null,
            ],
            'findings' => $findings,
            'spaces' => $spaces,
            'checklist' => $this->checklist($local, $site, $spaces, $ok),
        ];
    }

    /**
     * Command / UI lines that stay stable across surfaces.
     *
     * @param  Diagnostics  $diagnostics
     * @return list<string>
     */
    public function lines(array $diagnostics): array
    {
        return array_map(
            fn (array $finding): string => ($finding['id'] ? $finding['id'].': ' : '').$finding['message'],
            $diagnostics['findings'],
        );
    }

    /**
     * @param  list<Finding>  $findings
     * @return Diagnostics
     */
    private function empty(bool $reachable, bool $ok, array $findings): array
    {
        return [
            'ok' => $ok,
            'structural_ok' => $ok,
            'reachable' => $reachable,
            'site' => null,
            'findings' => $findings,
            'spaces' => [],
            'checklist' => [],
        ];
    }

    /**
     * @param  Collection<int, mixed>  $zones
     * @return SpaceRow
     */
    private function spaceRow(string $id, mixed $configuredKey, mixed $spaceConfig, $zones, bool $strictTraffic): array
    {
        $findings = [];
        $key = is_string($configuredKey) && $configuredKey !== '' ? $configuredKey : null;
        $zone = filled($key)
            ? $zones->first(fn ($candidate): bool => is_array($candidate) && ($candidate['key'] ?? null) === $key)
            : $zones->first(fn ($candidate): bool => is_array($candidate) && ($candidate['slot_id'] ?? null) === $id);

        if (! is_array($zone)) {
            $findings[] = $this->finding($id, 'error', 'zone_unknown',
                'zone missing or key unknown',
                'Sync spaces with `php artisan psobpsay:sync`, or fix the zone key / slot id in config.');

            return $this->row($id, $key, null, $findings);
        }

        if (($zone['is_active'] ?? false) !== true) {
            $findings[] = $this->finding($id, 'error', 'zone_inactive',
                'zone inactive',
                'Activate the space in the publisher console, or remove it from this site until it is ready.');
        }

        $template = is_string($zone['template'] ?? null) ? $zone['template'] : null;
        $format = is_string($zone['format'] ?? null) ? $zone['format'] : null;

        if ($template !== null && ! in_array($template, PsobPsayClient::SupportedTemplates, true)) {
            $findings[] = $this->finding($id, 'error', 'template_unsupported',
                'template has no bundled renderer ('.$template.')',
                'Use a supported template, or publish a host renderer before advertising this capability.');
        } elseif ($template === null && ! in_array($format, ['native', 'in_feed', 'text'], true)) {
            $findings[] = $this->finding($id, 'error', 'format_unsupported',
                'unsupported format',
                'Choose a native, in-feed or text format for server-rendered pages, or serve this space with the browser tag.');
        }

        if (is_array($spaceConfig) && isset($spaceConfig['template']) && $template !== $spaceConfig['template']) {
            $findings[] = $this->finding($id, 'error', 'template_mismatch',
                'template differs from psobpsay.spaces',
                'Update config or the console space so both sides declare the same template.');
        }

        $requests = is_numeric($zone['requests_24h'] ?? null) ? (int) $zone['requests_24h'] : 0;
        if ($requests < 1) {
            $severity = $strictTraffic ? 'error' : 'warning';
            $findings[] = $this->finding($id, $severity, 'no_recent_traffic',
                'no requests recorded in the last 24 hours',
                'This is normal for a new integration. Confirm the slot is mounted on a public page; treat as a failure only with --strict-traffic.');
        }

        return $this->row($id, $key, $zone, $findings);
    }

    /**
     * @param  array<string, mixed>|null  $zone
     * @param  list<Finding>  $findings
     * @return SpaceRow
     */
    private function row(string $id, ?string $key, ?array $zone, array $findings): array
    {
        $reservation = is_array($zone['reservation'] ?? null) ? $zone['reservation'] : null;
        $snippets = is_array($zone['snippets'] ?? null)
            ? array_filter($zone['snippets'], fn ($value): bool => is_string($value))
            : null;

        return [
            'id' => $id,
            'zone_key' => is_string($zone['key'] ?? null) ? $zone['key'] : $key,
            'slot_id' => is_string($zone['slot_id'] ?? null) ? $zone['slot_id'] : $id,
            'name' => is_string($zone['name'] ?? null) ? $zone['name'] : null,
            'format' => is_string($zone['format'] ?? null) ? $zone['format'] : null,
            'template' => is_string($zone['template'] ?? null) ? $zone['template'] : null,
            'is_active' => isset($zone['is_active']) ? (bool) $zone['is_active'] : null,
            'requests_24h' => is_numeric($zone['requests_24h'] ?? null) ? (int) $zone['requests_24h'] : null,
            'fill_rate_24h' => is_numeric($zone['fill_rate_24h'] ?? null) ? (float) $zone['fill_rate_24h'] : null,
            'last_request_at' => is_string($zone['last_request_at'] ?? null) ? $zone['last_request_at'] : null,
            'reservation' => $reservation === null ? null : [
                'campaign_brand' => is_string($reservation['campaign_brand'] ?? null) ? $reservation['campaign_brand'] : null,
                'starts_at' => is_string($reservation['starts_at'] ?? null) ? $reservation['starts_at'] : null,
                'ends_at' => is_string($reservation['ends_at'] ?? null) ? $reservation['ends_at'] : null,
            ],
            'snippets' => $snippets,
            'ok' => array_filter($findings, fn (array $f): bool => $f['severity'] === 'error') === [],
            'findings' => $findings,
        ];
    }

    /**
     * @return Finding
     */
    private function finding(?string $id, string $severity, string $code, string $message, string $next): array
    {
        return compact('id', 'severity', 'code', 'message', 'next');
    }

    /**
     * @param  AdsStatus  $local
     * @param  array<string, mixed>  $site
     * @param  list<SpaceRow>  $spaces
     * @return list<array{key: string, ok: bool, label: string, detail: string|null}>
     */
    private function checklist(array $local, array $site, array $spaces, bool $ok): array
    {
        $spaceErrors = collect($spaces)->contains(fn (array $space): bool => ! $space['ok']);

        return [
            ['key' => 'enabled', 'ok' => $local['enabled'], 'label' => 'Ads enabled', 'detail' => null],
            ['key' => 'configured', 'ok' => $local['configured'], 'label' => 'Server URL and API key set', 'detail' => null],
            ['key' => 'circuit', 'ok' => ! $local['circuitOpen'], 'label' => 'Connection healthy', 'detail' => $local['circuitOpen'] ? 'Circuit open' : null],
            ['key' => 'site', 'ok' => ($site['can_serve'] ?? false) === true, 'label' => 'Site can serve', 'detail' => is_string($site['reason'] ?? null) ? $site['reason'] : null],
            ['key' => 'spaces', 'ok' => ! $spaceErrors, 'label' => 'Configured spaces match the manifest', 'detail' => null],
            ['key' => 'ready', 'ok' => $ok, 'label' => 'Ready for traffic', 'detail' => null],
        ];
    }
}
