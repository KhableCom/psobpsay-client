# khable/psobpsay-client

Server-to-server client for the psobpsay ad network, for Laravel sites
that are publishers there. One batched call per page view, fail-empty, with a
circuit breaker, a strict field whitelist, and a card for Blade or React.

The full publisher guide — console onboarding, the API contract, local
development, troubleshooting — lives in psobpsay:
`docs/ads-network/publisher-integration.md`. This file is only the install.

## Install

```json
// composer.json of the site
"repositories": [{ "type": "vcs", "url": "https://github.com/KhableCom/psobpsay-client" }]
```

```bash
composer require khable/psobpsay-client
php artisan vendor:publish --tag=psobpsay-config   # config/psobpsay.php
```

Name your placements in `config/psobpsay.php` and give each the zone key the ad
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
ADS_COUNTRY_HEADER=CF-IPCountry        # or empty when not behind an edge; edge placeholders such as T1 or XX are dropped
```

## Ask for the placements on a page

```php
use Khable\PsobPsayClient\PsobPsayClient;

public function __invoke(Request $request, PsobPsayClient $ads)
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

When the ad server is unreachable, erroring or rate limiting, the client stops asking for
`ADS_CIRCUIT_SECONDS`. When it refuses the request — a wrong key (401) or a request it
will not accept (422) — only that page goes empty; the refusal is reported at most once
an hour and shows as `refusedStatus` in `$ads->status()`. Both are kept per site, so
sister sites sharing a cache do not trip each other.

## Render it

Blade:

```blade
<x-psobpsay::sponsored :ad="$sponsored['feed'] ?? null" :nonce="Vite::cspNonce()" class="my-card" />
```

React (Inertia): publish the card and restyle it to match your list.

```bash
php artisan vendor:publish --tag=psobpsay-react   # resources/js/components/psobpsay/
```

Keep `use-viewable-impression.ts` and `frequency-caps.ts` as published — the first
makes an impression count the way the ad server bills it (half the card on screen for
one second), the second keeps each campaign's daily frequency cap.

Then forward the campaigns a visitor has capped out on, once, where the app is created:

```tsx
// resources/js/app.tsx
import { forwardCappedCampaigns } from '@/components/psobpsay/frequency-caps';

forwardCappedCampaigns();
```

Every Inertia request, including the deferred one that loads the ads, then carries an
`X-PsobPsay-Seen` header, and `slotsFor()` passes the well-formed keys in it to psobpsay.
Counts live in the site's own `localStorage`, in the same format the psobpsay tag uses.

## Spaces by slot id

A templated space (a card, a rail, a logo bar, …) is named by this site, not by a zone
key. Declare the spaces in `config/psobpsay.php` and create any that are missing in psobpsay:

```php
'spaces' => [
    'web.grid.card' => ['template' => 'card_landscape'],
    'web.home.rail' => ['template' => 'rail', 'max_ads' => 4],
],
```

```bash
php artisan psobpsay:sync   # safe on every deploy; never changes a space that exists
```

The site is `ADS_SITE`, or the host of `app.url`. Slot ids are resolved to zone keys
through psobpsay' manifest (`$ads->manifest()`, kept `ADS_MANIFEST_SECONDS`, default 300),
so `.env` needs only the base URL and the API key. A slot id also listed under `zones`
uses that key instead.

Ask for every space on the page at once, deferred so the page draws first:

```php
'sponsored' => Inertia::defer(fn () => $ads->spaces($request, 'web.grid.card', 'web.home.rail')),
```

`spaces()` answers each slot id with a list of ads: one for a card, several for a rail,
none when unfilled. Each ad has the card's fields plus `template`, `disclosure`,
`assets` (crops by role), `logo`, `video` and `promo`. Then draw each space where it goes:

```tsx
<PsobPsaySlot id="web.grid.card" template="card_landscape" ads={sponsored} />
```

```blade
<x-psobpsay::slot id="web.grid.card" template="card_landscape" :ads="$sponsored" />
```

`PsobPsaySlot` (published with `--tag=psobpsay-react`) keeps the template's box while the prop
loads, draws nothing for an unfilled space, and frames every ad as one sponsored link
with its own viewable impression. Pass `renderers={{ card_landscape: (ad, label) => … }}`
to draw a template with your own card; the renderer draws only the inside and must show
`label`. The psobpsay console shows these lines for each space under **Get the tag → Laravel**.

## Privacy

`slotsFor()` serves a visitor who sends `Sec-GPC: 1` or `DNT: 1` as restricted, as the
psobpsay tag would, and forwards none of their caps; the React card counts nothing for them
either. There is nothing to configure.

Blade pages get the privacy handling but not frequency caps: a full page load has no
follow-up request to carry the header.

## Content Security Policy

Creative images load from the serving host, so it belongs in `img-src`
(`https:` already covers it if you allow that). Click and impression URLs are
plain links and an image beacon; no script is loaded from the ad server.

## Roadmap

Planned, not yet built; the design lives in psobpsay at
`docs/ads-network/production-readiness-plan.md`, Phase 7b:

- `report()` against psobpsay' publisher API, fail-empty like `slots()`.
- `php artisan psobpsay:check` to fail a deploy on a wrong key, a missing or inactive
  zone, a `slot_id` used in code but unknown to psobpsay, a site that cannot serve, or a
  zone format this client cannot render.
- A publishable admin dashboard (`--tag=psobpsay-dashboard`): spaces with a "Get code"
  sheet, analytics, what is showing now, and a setup checklist.
- Each slot tells psobpsay which space templates it can render, and empty answers carry a reason.
- The hide / report / why-this-ad menu on each space ad.

## Tests

```bash
composer test
composer lint
```
