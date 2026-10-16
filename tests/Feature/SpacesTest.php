<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Khable\PsobPsayClient\PsobPsayClient;

/** @return array<string, mixed> */
function manifestBody(): array
{
    return [
        'sites' => [
            ['domain' => 'site.test', 'zones' => [
                ['key' => 'zn_card', 'slot_id' => 'web.grid.card', 'is_active' => true],
                ['key' => 'zn_rail', 'slot_id' => 'web.home.rail', 'is_active' => true],
                ['key' => 'zn_paused', 'slot_id' => 'web.paused', 'is_active' => false],
            ]],
            ['domain' => 'other.test', 'zones' => [['key' => 'zn_other', 'slot_id' => 'web.grid.card', 'is_active' => true]]],
        ],
        'templates' => [],
    ];
}

/** @return array<string, mixed> */
function spaceAd(string $id, array $overrides = []): array
{
    return [
        ...servedAd($id),
        'template' => 'rail',
        'disclosure' => 'paid',
        'assets' => ['landscape' => ['src' => 'https://cdn.test/l.jpg', 'srcset' => 'https://cdn.test/l.jpg 600w, https://cdn.test/l@2x.jpg 1200w', 'width' => 600, 'height' => 375]],
        'logo' => ['src' => 'https://cdn.test/logo.png', 'srcset' => null, 'width' => 128, 'height' => 128],
        'video' => null,
        'promo' => null,
        ...$overrides,
    ];
}

function pageRequest(string $url = 'https://www.site.test/events?page=2'): Illuminate\Http\Request
{
    return Illuminate\Http\Request::create($url);
}

beforeEach(function () {
    psobpsayConfigured();
    config()->set('psobpsay.zones', []);
});

test('spaces are addressed by slot id, resolved through the manifest for the page\'s own site', function () {
    Http::fake([
        'https://ads.test/api/v1/publisher/manifest' => Http::response(manifestBody()),
        'https://ads.test/api/v1/serve' => Http::response(['slots' => [
            'web.home.rail' => ['ad' => spaceAd('cr_a'), 'ads' => [spaceAd('cr_a'), spaceAd('cr_b')], 'no_fill' => false],
            'web.grid.card' => ['ad' => null, 'ads' => [], 'no_fill' => true],
        ]]),
    ]);

    $spaces = app(PsobPsayClient::class)->spaces(pageRequest(), 'web.home.rail', 'web.grid.card', 'web.paused', 'web.unknown');

    expect(array_keys($spaces))->toBe(['web.home.rail', 'web.grid.card', 'web.paused', 'web.unknown'])
        ->and(array_column($spaces['web.home.rail'], 'id'))->toBe(['cr_a', 'cr_b'])
        ->and($spaces['web.grid.card'])->toBe([])
        ->and($spaces['web.paused'])->toBe([])
        ->and($spaces['web.home.rail'][0])->toMatchArray([
            'template' => 'rail',
            'disclosure' => 'paid',
            'assets' => ['landscape' => ['src' => 'https://cdn.test/l.jpg', 'srcset' => 'https://cdn.test/l.jpg 600w, https://cdn.test/l@2x.jpg 1200w', 'width' => 600, 'height' => 375]],
            'logo' => ['src' => 'https://cdn.test/logo.png', 'srcset' => null, 'width' => 128, 'height' => 128],
        ]);

    // The page is sent without its query string, and only the spaces this site has live
    // are asked for: another site's zone of the same slot id would earn for the wrong page.
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/api/v1/serve')
        && $request['page'] === 'https://www.site.test/events'
        && $request['slots'] === [['id' => 'web.home.rail', 'zone' => 'zn_rail', 'accepts' => ['native', 'in_feed', 'text', ...PsobPsayClient::SupportedTemplates]], ['id' => 'web.grid.card', 'zone' => 'zn_card', 'accepts' => ['native', 'in_feed', 'text', ...PsobPsayClient::SupportedTemplates]]]);

    // The manifest is read once and kept, so a page view costs one serve call.
    app(PsobPsayClient::class)->spaces(pageRequest(), 'web.home.rail');
    Http::assertSentCount(3);
});

test('a slot id listed in psobpsay.zones uses that key without reading the manifest', function () {
    config()->set('psobpsay.zones', ['web.grid.card' => 'zn_pinned']);
    Http::fake(['https://ads.test/api/v1/serve' => Http::response(['slots' => []])]);

    app(PsobPsayClient::class)->spaces(pageRequest(), 'web.grid.card');

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request['slots'] === [['id' => 'web.grid.card', 'zone' => 'zn_pinned', 'accepts' => ['native', 'in_feed', 'text', ...PsobPsayClient::SupportedTemplates]]]);
});

test('nothing unsafe in a space ad reaches the page', function () {
    config()->set('psobpsay.zones', ['s' => 'zn_s']);
    Http::fake(['https://ads.test/api/v1/serve' => Http::response(['slots' => ['s' => ['ads' => [
        spaceAd('cr_a', [
            'disclosure' => 'totally-organic',
            'assets' => [
                'landscape' => ['src' => 'javascript:alert(1)', 'width' => 600],
                'square' => ['src' => 'https://cdn.test/s.jpg', 'srcset' => 'https://cdn.test/s.jpg 1x, javascript:x 2x'],
            ],
            'logo' => ['src' => 'data:image/png;base64,AAAA'],
            'video' => ['src' => 'javascript:x', 'poster' => 'https://cdn.test/p.jpg'],
            'promo' => ['code' => 'KHMER10', 'terms' => 'One per person'],
            'extra' => 'never passed on',
        ]),
        spaceAd('cr_b', ['click_url' => 'javascript:alert(1)']),
    ]]]])]);

    $ads = app(PsobPsayClient::class)->spaces(pageRequest(), 's')['s'];

    // An advertiser cannot choose how the space labels them, and a bad URL in one crop
    // drops that crop, not the whole ad.
    expect($ads)->toHaveCount(1)
        ->and($ads[0]['disclosure'])->toBe('sponsored')
        ->and($ads[0]['assets'])->toBe(['square' => ['src' => 'https://cdn.test/s.jpg', 'srcset' => null, 'width' => null, 'height' => null]])
        ->and($ads[0]['logo'])->toBeNull()
        ->and($ads[0]['video'])->toBeNull()
        ->and($ads[0]['promo'])->toBe(['code' => 'KHMER10', 'terms' => 'One per person'])
        ->and($ads[0])->not->toHaveKey('extra');
});

test('an ad server that answers only `ad` still fills a one-ad space', function () {
    config()->set('psobpsay.zones', ['s' => 'zn_s']);
    Http::fake(['https://ads.test/api/v1/serve' => function (Request $request) {
        return Http::response(['slots' => ['s' => ['ad' => in_array('native', $request['slots'][0]['accepts'], true) ? servedAd('cr_old') : null]]]);
    }]);

    $ads = app(PsobPsayClient::class)->spaces(pageRequest(), 's')['s'];

    expect(array_column($ads, 'id'))->toBe(['cr_old'])->and($ads[0]['disclosure'])->toBe('sponsored');
});

test('a manifest that cannot be read leaves every space empty and is tried again', function () {
    Exceptions::fake();
    Http::fake(['https://ads.test/api/v1/publisher/manifest' => Http::response(['message' => 'Unknown API key.'], 401)]);

    expect(app(PsobPsayClient::class)->spaces(pageRequest(), 'web.grid.card'))->toBe(['web.grid.card' => []])
        ->and(app(PsobPsayClient::class)->manifest())->toBe([])
        ->and(Cache::has(app(PsobPsayClient::class)->circuitKey()))->toBeFalse();

    // A refused key is this site's mistake: reported once, never cached as the answer.
    Http::assertSentCount(2);
    Exceptions::assertReportedCount(1);
});

test('a manifest request that times out opens the circuit for serving too', function () {
    Exceptions::fake();
    Http::fake(['https://ads.test/api/v1/publisher/manifest' => Http::failedConnection()]);

    app(PsobPsayClient::class)->spaces(pageRequest(), 'web.grid.card');

    expect(Cache::has(app(PsobPsayClient::class)->circuitKey()))->toBeTrue();
    Http::assertSentCount(1);
});

test('psobpsay:sync creates the declared spaces on this site and says which it left alone', function () {
    config()->set('app.url', 'https://www.site.test');
    config()->set('psobpsay.spaces', [
        'web.grid.card' => ['template' => 'card_landscape'],
        'web.home.rail' => ['template' => 'rail', 'max_ads' => 6],
    ]);
    Http::fake([
        'https://ads.test/api/v1/publisher/manifest' => Http::response(manifestBody()),
        'https://ads.test/api/v1/publisher/zones' => Http::response([
            'created' => ['web.grid.card'],
            'existing' => ['web.home.rail'],
            'site' => ['zones' => [['slot_id' => 'web.home.rail', 'template' => 'rail', 'max_ads' => 4]]],
        ], 201),
    ]);
    app(PsobPsayClient::class)->manifest();

    $this->artisan('psobpsay:sync')
        ->expectsOutputToContain('web.grid.card')
        ->expectsOutputToContain('left as the console has it')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/api/v1/publisher/zones')
        && $request->hasHeader('Authorization', 'Bearer pk_'.str_repeat('a', 32))
        && $request['site'] === 'site.test'
        && $request['spaces'] === [
            ['slot_id' => 'web.grid.card', 'template' => 'card_landscape'],
            ['slot_id' => 'web.home.rail', 'template' => 'rail', 'max_ads' => 6],
        ]);

    // The new space must be servable on the next page view, not five minutes later.
    app(PsobPsayClient::class)->manifest();
    Http::assertSentCount(3);
});

test('psobpsay:sync fails loudly when the ad server refuses it', function () {
    config()->set('psobpsay.spaces', ['web.x' => ['template' => 'banner']]);
    config()->set('psobpsay.site', 'site.test');
    Http::fake(['https://ads.test/api/v1/publisher/zones' => Http::response(['message' => 'The selected spaces.0.template is invalid.'], 422)]);

    $this->artisan('psobpsay:sync')->expectsOutputToContain('422')->assertFailed();
});

test('the Blade slot draws its own space with the space\'s disclosure, and nothing when unfilled', function () {
    $ads = ['web.home.rail' => [
        [...spaceAdShaped('cr_a'), 'disclosure' => 'paid'],
        [...spaceAdShaped('cr_b'), 'disclosure' => 'paid'],
    ], 'web.grid.card' => []];

    $html = Blade::render('<x-psobpsay::slot id="web.home.rail" template="rail" :ads="$ads" />', ['ads' => $ads]);

    expect($html)->toContain('data-psobpsay-slot="web.home.rail"')
        ->toContain('psobpsay-slot--rail')
        ->toContain('data-card-id="cr_a"')
        ->toContain('data-card-id="cr_b"')
        ->and(substr_count($html, '>Paid partner<'))->toBe(2)
        ->and(trim(Blade::render('<x-psobpsay::slot id="web.grid.card" :ads="$ads" />', ['ads' => $ads])))->toBe('')
        ->and(trim(Blade::render('<x-psobpsay::slot id="web.nowhere" :ads="$ads" />', ['ads' => $ads])))->toBe('')
        ->and(Blade::render('<x-psobpsay::slot id="web.home.rail" :ads="$ads" :labels="[\'paid\' => \'ដៃគូ\']" />', ['ads' => $ads]))->toContain('ដៃគូ');
});

/** @return array<string, mixed> */
function spaceAdShaped(string $id): array
{
    return [
        'id' => $id,
        'headline' => 'Headline',
        'body' => null,
        'cta' => null,
        'brand' => 'Brand',
        'image' => null,
        'impressionUrl' => 'https://ads.test/api/v1/imp/'.$id.'.gif',
        'clickUrl' => 'https://ads.test/api/v1/click/'.$id,
        'frequency' => null,
    ];
}

test('forwards only declared public page topics and preserves privacy signals', function (string $host) {
    psobpsayConfigured();
    $manifest = manifestBody();
    $manifest['sites'][0]['context_schema'] = ['city' => ['phnom-penh'], 'category' => ['music']];
    Http::fake([
        'https://ads.test/api/v1/publisher/manifest' => Http::response($manifest),
        'https://ads.test/api/v1/serve' => Http::response(['slots' => []]),
    ]);
    $request = Illuminate\Http\Request::create('https://'.$host.'/events', server: ['HTTP_SEC_GPC' => '1']);
    app(PsobPsayClient::class)->spacesWithContext($request, [
        'city' => ['phnom-penh', 'unknown', 'phnom-penh'],
        'category' => ['music'],
        'email' => ['someone@example.com'],
    ], 'web.grid.card');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://ads.test/api/v1/serve'
        && $request['context'] === ['city' => ['phnom-penh'], 'category' => ['music']]
        && $request['privacy'] === 'restricted');
})->with(['site.test', 'www.site.test']);

test('does not forward topics from an older manifest without a schema', function () {
    psobpsayConfigured();
    Http::fake([
        'https://ads.test/api/v1/publisher/manifest' => Http::response(manifestBody()),
        'https://ads.test/api/v1/serve' => Http::response(['slots' => []]),
    ]);
    app(PsobPsayClient::class)->spacesWithContext(Illuminate\Http\Request::create('https://site.test/events'),
        ['city' => ['phnom-penh']], 'web.grid.card');
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://ads.test/api/v1/serve'
        && ! array_key_exists('context', $request->data()));
});

test('bounds context and never borrows a sibling sites schema', function () {
    psobpsayConfigured();
    $context = [];
    foreach (range(1, 9) as $number) {
        $context['topic-'.$number] = ['one', 'two', 'three', 'four', 'five', 'six'];
    }
    $manifest = manifestBody();
    $manifest['sites'][0]['context_schema'] = $context;
    $manifest['sites'][] = ['domain' => 'sibling.test', 'context_schema' => ['private-topic' => ['value']], 'zones' => []];
    Http::fake([
        'https://ads.test/api/v1/publisher/manifest' => Http::response($manifest),
        'https://ads.test/api/v1/serve' => Http::response(['slots' => []]),
    ]);
    app(PsobPsayClient::class)->spacesWithContext(Illuminate\Http\Request::create('https://site.test/events'),
        ['private-topic' => ['value'], ...$context], 'web.grid.card');
    Http::assertSent(function (Request $request): bool {
        if ($request->url() !== 'https://ads.test/api/v1/serve') {
            return false;
        }
        expect($request['context'])->toHaveCount(8)->not->toHaveKey('private-topic');
        foreach ($request['context'] as $values) {
            expect($values)->toHaveCount(5);
        }

        return true;
    });
});
