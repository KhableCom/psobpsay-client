# psobpsay/client

Server-to-server client for the psobpsay ad network, for Laravel sites
that are publishers there. One batched call per page view, fail-empty, with a
circuit breaker, a strict field whitelist, and a card for Blade or React.

Source: [KhableCom/psobpsay-client](https://github.com/KhableCom/psobpsay-client), public,
with development on `main`. The main application repository is private; this README provides
public installation guidance. Source publication does not establish a tagged package release
or a live serving endpoint. Internal maintainers can find the complete publisher guide at
`docs/ads-network/publisher-integration.md` in the main repository.

## Install

```json
// composer.json of the site
"repositories": [{ "type": "vcs", "url": "https://github.com/KhableCom/psobpsay-client" }]
```

```bash
composer require psobpsay/client
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
ADS_BASE_URL=https://s.psobpsay.com      # the serving host, no path
ADS_API_KEY=pk_...                     # issued once in the console; server side only
ADS_ZONE_FEED=zn_...
ADS_ZONE_SIDEBAR=zn_...
ADS_COUNTRY_HEADER=CF-IPCountry        # or empty when not behind an edge; edge placeholders such as T1 or XX are dropped
```

## Ask for the placements on a page

```php
use PsobPsay\Client\PsobPsayClient;

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

## Implemented capabilities and remaining work

Implemented: `manifest()`, `report()`, `syncSpaces()`, `creatives()`, `block()`,
`psobpsay:sync`, `psobpsay:check`, local `status()`, remote `inspect()` / `diagnostics()`,
shared `SiteMatcher` + `SpaceDiagnostics`, optional admin dashboard, and distinct web
renderers for `card_landscape`, `card_portrait`, `row_compact`, `bar_logo`, `rail`,
`logo_grid`, `hero_cobrand`, and `reward` (React + Blade). Promo copy controls sit outside
the billable click link. `promoted_content` is supported with a default React card that hosts
usually override to draw their own content from `contentRef`; Blade has no dedicated layout for
it. Mobile-only catalogue templates (`story_video`, `interstitial`) stay out of
`SupportedTemplates` until their web path exists.

Remaining improvements:

- Browser-test renderers (reduced-motion, keyboard) and hide/report/why-this-ad UX (P5).
- Verify clean installation into supported Laravel hosts, CI and package release constraints
  before creating a release tag.

The internal execution plan is `docs/ads-network/launch-implementation-plan.md`; these public
notes summarize the client work without requiring access to the private repository.

## Publisher dashboard

Off by default. Enable only behind the host's admin policy:

```env
ADS_DASHBOARD_ENABLED=true
ADS_DASHBOARD_MIDDLEWARE=web,auth,can:viewPsobPsayDashboard
ADS_DASHBOARD_PREFIX=admin/psobpsay
```

Publish optional Blade/Inertia stubs with `php artisan vendor:publish --tag=psobpsay-dashboard`.
Package installation never registers the routes without both `enabled` and non-empty middleware.

`status()` remains local and network-free. Use `inspect()` / `diagnostics()` for remote
inventory checks (cached manifest age and stale flag included). `psobpsay:check` shares the
same diagnosis; add `--strict-traffic` when absence of requests in 24 hours must fail CI.

## Tests

```bash
composer test
composer lint
```

## Public page topics

Declare allowed topic keys and values on the publisher site in PsobPsay, then submit public
page categories explicitly from the host controller:

```php
$sponsored = $psobpsay->spacesWithContext($request, [
    'city' => ['phnom-penh'],
    'category' => ['music'],
], 'web.grid.card');
```

Use page metadata only, never email, user IDs, purchase history, or other visitor attributes.
The client forwards only values declared for the matching site's manifest, including its supported `www` host (up to eight topics
and five values per topic). Existing `spaces()` callers continue sending no context. Missing
schema or unavailable manifest means no context is forwarded. GPC/DNT still apply.
