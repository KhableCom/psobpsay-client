# khable/khads-client

Server-to-server client for the [khads](../khads) ad network, for Laravel sites
that are publishers there. One batched call per page view, fail-empty, with a
circuit breaker, a strict field whitelist, and a card for Blade or React.

The full publisher guide — console onboarding, the API contract, local
development, troubleshooting — lives in khads:
`docs/ads-network/publisher-integration.md`. This file is only the install.

## Install

```json
// composer.json of the site
"repositories": [{ "type": "vcs", "url": "git@github.com:khable/khads-client.git" }]
```

```bash
composer require khable/khads-client
php artisan vendor:publish --tag=khads-config   # config/khads.php
```

Name your placements in `config/khads.php` and give each the zone key the ad
console shows for your site:

```php
'zones' => [
    'feed' => env('ADS_ZONE_FEED'),
    'sidebar' => env('ADS_ZONE_SIDEBAR'),
],
```

```dotenv
ADS_ENABLED=true
ADS_BASE_URL=https://s.khable.com      # the serving host, no path
ADS_API_KEY=pk_...                     # issued once in the console; server side only
ADS_ZONE_FEED=zn_...
ADS_ZONE_SIDEBAR=zn_...
ADS_COUNTRY_HEADER=CF-IPCountry        # or empty when not behind an edge
```

## Ask for the placements on a page

```php
use Khable\KhadsClient\KhadsClient;

public function __invoke(Request $request, KhadsClient $ads)
{
    // Inertia: deferred so the page draws first and a slow ad server only delays the ad.
    return Inertia::render('home', [
        'sponsored' => Inertia::defer(fn (): array => $ads->slotsFor($request, route('home'), 'feed', 'sidebar'), rescue: true),
    ]);

    // Blade: the same call, straight into the view.
    return view('home', ['sponsored' => $ads->slotsFor($request, route('home'), 'feed')]);
}
```

`slotsFor()` returns one entry per placement you named — the shaped ad, or `null`.
A placement with no zone configured is left out. Nothing is ever thrown.

## Render it

Blade:

```blade
<x-khads::sponsored :ad="$sponsored['feed'] ?? null" :nonce="Vite::cspNonce()" class="my-card" />
```

React (Inertia): publish the card and restyle it to match your list.

```bash
php artisan vendor:publish --tag=khads-react   # resources/js/components/khads/
```

Keep `use-viewable-impression.ts` as published — it is what makes an impression
count the way the ad server bills it (half the card on screen for one second).

## Content Security Policy

Creative images load from the serving host, so it belongs in `img-src`
(`https:` already covers it if you allow that). Click and impression URLs are
plain links and an image beacon; no script is loaded from the ad server.

## Tests

```bash
composer test
composer lint
```
