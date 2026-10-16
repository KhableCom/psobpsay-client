<?php

use Illuminate\Support\Facades\Http;

function dashboardManifest(): array
{
    return ['sites' => [['domain' => 'site.test', 'can_serve' => true, 'status' => 'active', 'verified' => true, 'zones' => [[
        'key' => 'zn_feed',
        'slot_id' => 'feed',
        'name' => 'Feed',
        'template' => 'card_landscape',
        'format' => 'native',
        'is_active' => true,
        'requests_24h' => 4,
        'fill_rate_24h' => 50.0,
        'last_request_at' => '2026-10-16T00:00:00+00:00',
        'reservation' => null,
        'snippets' => ['react' => '<PsobPsaySlot id="feed" />'],
    ]]]]];
}

beforeEach(function () {
    psobpsayConfigured();
    config()->set('psobpsay.site', 'site.test');
    config()->set('psobpsay.zones', ['feed' => 'zn_feed']);
    config()->set('psobpsay.console_url', 'https://ads.test/publisher');
    config()->set('psobpsay.dashboard.enabled', true);
    config()->set('psobpsay.dashboard.middleware', ['web', 'psobpsay.test-admin']);
});

test('guests are denied before any upstream request', function () {
    Http::fake();
    $this->get('/admin/psobpsay')->assertForbidden();
    Http::assertNothingSent();
});

test('admins see health spaces and unavailable reports without invented zeros', function () {
    Http::fake([
        'https://ads.test/api/v1/publisher/manifest' => Http::response(dashboardManifest()),
        'https://ads.test/api/v1/publisher/report*' => Http::response([], 503),
        'https://ads.test/api/v1/publisher/creatives*' => Http::response(['ads' => [[
            'id' => 'cr_1',
            'brand' => 'Brand',
            'headline' => 'Hello',
            'image' => 'https://cdn.test/a.png',
            'landing_host' => 'brand.test',
            'impression_url' => 'https://ads.test/api/v1/imp/secret.gif',
            'click_url' => 'https://ads.test/api/v1/click/secret',
        ]]]),
    ]);

    $response = $this->withHeader('X-Test-Admin', '1')->get('/admin/psobpsay');

    $response->assertSuccessful()
        ->assertSee('PsobPsay', false)
        ->assertSee('feed', false)
        ->assertSee('Report data is unavailable', false)
        ->assertDontSee('secret.gif', false)
        ->assertDontSee('/click/secret', false);

    expect($response->getContent())->not->toContain((string) config('psobpsay.api_key'));
});

test('csv export uses the fixed schema and neutralizes formula cells', function () {
    Http::fake([
        'https://ads.test/api/v1/publisher/report*' => Http::response([
            'rows' => [[
                'dimension' => '=cmd',
                'requests' => 1,
                'fills' => 1,
                'impressions' => 1,
                'viewable_rate' => 1,
                'clicks' => 0,
                'ctr' => 0,
                'earnings_micros' => 0,
                'ecpm_micros' => 0,
            ]],
            'totals' => [
                'requests' => 1, 'fills' => 1, 'impressions' => 1, 'viewable_rate' => 1,
                'clicks' => 0, 'ctr' => 0, 'earnings_micros' => 0, 'ecpm_micros' => 0,
            ],
        ]),
    ]);

    $response = $this->withHeader('X-Test-Admin', '1')->get('/admin/psobpsay/report.csv');
    $response->assertSuccessful();
    $csv = $response->streamedContent();

    expect($csv)->toContain('dimension,requests,fills')
        ->and($csv)->toContain("'=cmd")
        ->and($csv)->toContain('totals');
});
