# khable/khads-client

Server-to-server client for the khads ad network, for Laravel sites
that are publishers there. One batched call per page view, fail-empty, with a
circuit breaker, a strict field whitelist, and a card for Blade or React.

The full publisher guide — console onboarding, the API contract, local
development, troubleshooting — lives in khads:
`docs/ads-network/publisher-integration.md`. This file is only the install.

## Install

```json
// composer.json of the site
"repositories": [{ "type": "vcs", "url": "https://github.com/KhableCom/khads-client" }]
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
ADS_COUNTRY_HEADER=CF-IPCountry        # or empty when not behind an edge; edge placeholders such as T1 or XX are dropped
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

When the ad server is unreachable, erroring or rate limiting, the client stops asking for
`ADS_CIRCUIT_SECONDS`. When it refuses the request — a wrong key (401) or a request it
will not accept (422) — only that page goes empty; the refusal is reported at most once
an hour and shows as `refusedStatus` in `$ads->status()`. Both are kept per site, so
sister sites sharing a cache do not trip each other.

## Render it

Blade:

```blade
<x-khads::sponsored :ad="$sponsored['feed'] ?? null" :nonce="Vite::cspNonce()" class="my-card" />
```

React (Inertia): publish the card and restyle it to match your list.

```bash
php artisan vendor:publish --tag=khads-react   # resources/js/components/khads/
```

Keep `use-viewable-impression.ts` and `frequency-caps.ts` as published — the first
makes an impression count the way the ad server bills it (half the card on screen for
one second), the second keeps each campaign's daily frequency cap.

Then forward the campaigns a visitor has capped out on, once, where the app is created:

```tsx
// resources/js/app.tsx
import { forwardCappedCampaigns } from '@/components/khads/frequency-caps';

forwardCappedCampaigns();
```

Every Inertia request, including the deferred one that loads the ads, then carries an
`X-Khads-Seen` header, and `slotsFor()` passes the well-formed keys in it to khads.
Counts live in the site's own `localStorage`, in the same format the khads tag uses.

## Privacy

`slotsFor()` serves a visitor who sends `Sec-GPC: 1` or `DNT: 1` as restricted, as the
khads tag would, and forwards none of their caps; the React card counts nothing for them
either. There is nothing to configure.

Blade pages get the privacy handling but not frequency caps: a full page load has no
follow-up request to carry the header.

## Content Security Policy

Creative images load from the serving host, so it belongs in `img-src`
(`https:` already covers it if you allow that). Click and impression URLs are
plain links and an image beacon; no script is loaded from the ad server.

## Roadmap

Planned, not yet built; the design lives in khads at
`docs/ads-network/production-readiness-plan.md`, Phase 7b:

- `manifest()` and `report()` against khads' publisher API, fail-empty like `slots()`.
- `php artisan khads:check` to fail a deploy on a wrong key, a missing or inactive
  zone, a site that cannot serve, or a zone format this client cannot render.
- A publishable admin dashboard (`--tag=khads-dashboard`): spaces, analytics,
  what is showing now, and a setup checklist.
- Each slot tells khads which space templates it can render, and empty answers carry a reason.

Phase 8g, spaces by name and copyable code:

- Spaces are addressed by `slot_id` (`web.grid.card`), resolved to zone keys through
  the manifest, so `.env` holds only the base URL and the API key.
- `<KhadsSlot id="..." ads={sponsored} />` and `<x-khads::slot>`: one line per space,
  with a renderer per template, a reserved skeleton box, house fill or collapse on
  empty, the viewability hook, and the hide / report / why-this-ad menu.
- `$ads->deferred($request, ...$slotIds)` for the controller side.
- `php artisan khads:sync` creates the spaces declared in `config('khads.spaces')`
  in khads; `khads:check` also fails on a `slot_id` used in code but unknown to khads.
- The dashboard's Spaces table gets a "Get code" sheet per space with the same
  snippets the khads console shows.

## Tests

```bash
composer test
composer lint
```
