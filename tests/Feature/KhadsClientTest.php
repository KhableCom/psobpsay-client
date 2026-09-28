<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Khable\KhadsClient\KhadsClient;

const PAGE = 'https://site.test/';

function client(): KhadsClient
{
    return app(KhadsClient::class);
}

test('the client is one instance per application', function () {
    expect(client())->toBe(client());
});

test('every placement is asked for in one call, keyed to the site, and only rendered fields cross over', function () {
    khadsConfigured();
    Http::fake(['https://ads.test/api/v1/serve' => Http::response([
        'slots' => [
            'feed' => ['ad' => servedAd('cr_feed'), 'no_fill' => false],
            'sidebar' => ['ad' => null, 'no_fill' => true],
        ],
    ])]);

    $slots = client()->slots(PAGE, config('khads.zones'), 'KH');

    expect($slots['sidebar'])->toBeNull()
        ->and($slots['feed'])->toBe([
            'id' => 'cr_feed',
            'headline' => 'Headline',
            'body' => 'Body',
            'cta' => 'Learn more',
            'brand' => 'Brand',
            'image' => 'https://cdn.test/a.png',
            'impressionUrl' => 'https://ads.test/api/v1/imp/cr_feed.gif',
            'clickUrl' => 'https://ads.test/api/v1/click/cr_feed',
        ]);

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer pk_'.str_repeat('a', 32))
        && $request['page'] === PAGE
        && $request['country'] === 'KH'
        && $request['slots'] === [['id' => 'feed', 'zone' => 'zn_feed'], ['id' => 'sidebar', 'zone' => 'zn_side']]);
});

test('an unknown country is left out rather than sent empty', function () {
    khadsConfigured();
    Http::fake();

    client()->slots(PAGE, ['feed' => 'zn_feed']);

    Http::assertSent(fn (Request $request): bool => ! array_key_exists('country', $request->data()));
});

test('slotsFor resolves placements by name and reads the country from the edge header', function () {
    khadsConfigured();
    Http::fake();
    $request = Illuminate\Http\Request::create(PAGE, 'GET', server: ['HTTP_CF_IPCOUNTRY' => 'KH']);

    // An unknown placement is not asked for and is absent from the answer.
    expect(client()->slotsFor($request, PAGE, 'feed', 'nowhere'))->toBe(['feed' => null]);

    Http::assertSent(fn (Request $r): bool => $r['country'] === 'KH'
        && $r['slots'] === [['id' => 'feed', 'zone' => 'zn_feed']]);
});

test('an edge placeholder for the country is dropped rather than failing the page', function (?string $header, ?string $sent) {
    khadsConfigured();
    Http::fake();

    // The ad server refuses anything that is not two letters, which the client would
    // read as an outage: one Tor visitor must not blank ads for everyone after.
    client()->slotsFor(Illuminate\Http\Request::create(PAGE, 'GET', server: $header === null ? [] : ['HTTP_CF_IPCOUNTRY' => $header]), PAGE, 'feed');

    Http::assertSent(fn (Request $r): bool => ($r->data()['country'] ?? null) === $sent);
})->with([
    'Tor' => ['T1', null],
    'unknown' => ['XX', null],
    'lower case' => ['kh', 'KH'],
    'padded' => [' KH ', 'KH'],
    'missing' => [null, null],
]);

test('the country header is the site\'s to choose', function () {
    khadsConfigured();
    config()->set('khads.country_header', 'X-Country');
    Http::fake();
    $request = Illuminate\Http\Request::create(PAGE, 'GET', server: ['HTTP_X_COUNTRY' => 'TH', 'HTTP_CF_IPCOUNTRY' => 'KH']);

    client()->slotsFor($request, PAGE, 'feed');

    Http::assertSent(fn (Request $r): bool => $r['country'] === 'TH');
});

test('a failing ad server leaves the slots empty and is left alone until the circuit closes', function () {
    khadsConfigured();
    Http::fake(['*' => Http::response('upstream down', 503)]);

    expect(client()->slots(PAGE, config('khads.zones')))->toBe(['feed' => null, 'sidebar' => null])
        ->and(Cache::has(client()->circuitKey()))->toBeTrue();

    // One failure is the whole cost of a down ad server: the next page view does not pay it again.
    client()->slots(PAGE, config('khads.zones'));
    Http::assertSentCount(1);

    // ...until the window has passed, when it is asked once more.
    $this->travel(61)->seconds();
    client()->slots(PAGE, config('khads.zones'));
    Http::assertSentCount(2);
});

test('a host that cannot be reached is reported and opens the circuit like any other failure', function () {
    khadsConfigured();
    Exceptions::fake();
    Http::fake(fn () => throw new ConnectionException('cURL error 28'));

    expect(client()->slots(PAGE, ['feed' => 'zn_feed']))->toBe(['feed' => null])
        ->and(Cache::has(client()->circuitKey()))->toBeTrue();

    Exceptions::assertReported(ConnectionException::class);
});

test('sites sharing a cache keep their circuits apart', function () {
    khadsConfigured();
    Http::fake(['*' => Http::response('upstream down', 503)]);
    client()->slots(PAGE, ['feed' => 'zn_feed']);

    // A sister site on the same Redis, with its own key, is still asked for ads.
    config()->set('khads.api_key', 'pk_'.str_repeat('b', 32));
    client()->slots(PAGE, ['feed' => 'zn_feed']);
    Http::assertSentCount(2);

    // The first site is still left alone until its own circuit closes.
    config()->set('khads.api_key', 'pk_'.str_repeat('a', 32));
    client()->slots(PAGE, ['feed' => 'zn_feed']);
    Http::assertSentCount(2);
});

test('the connect budget never exceeds the whole budget and neither drops below 50ms', function () {
    khadsConfigured();

    config()->set('khads.timeout_ms', 500);
    config()->set('khads.connect_timeout_ms', 900);
    expect(client()->connectTimeoutSeconds())->toBe(0.5)->and(client()->timeoutSeconds())->toBe(0.5);

    config()->set('khads.timeout_ms', 10);
    config()->set('khads.connect_timeout_ms', 10);
    expect(client()->connectTimeoutSeconds())->toBe(0.05)->and(client()->timeoutSeconds())->toBe(0.05);

    config()->set('khads.timeout_ms', 500);
    config()->set('khads.connect_timeout_ms', 150);
    expect(client()->connectTimeoutSeconds())->toBe(0.15);
});

test('an ad missing the URLs that make it countable is dropped', function () {
    khadsConfigured();
    Http::fake(['*' => Http::response(['slots' => ['feed' => ['ad' => ['id' => 'cr_x', 'format' => 'native', 'headline' => 'No urls']]]])]);

    expect(client()->slots(PAGE, config('khads.zones')))->toBe(['feed' => null, 'sidebar' => null]);
});

test('display creatives are dropped instead of rendering as an empty native card', function () {
    khadsConfigured();
    Http::fake(['*' => Http::response(['slots' => [
        'feed' => ['ad' => [...servedAd('cr_display'), 'format' => 'medium_rectangle']],
    ]])]);

    expect(client()->slots(PAGE, ['feed' => 'zn_feed']))->toBe(['feed' => null]);
});

test('each supported native format is accepted', function (string $format) {
    khadsConfigured();
    Http::fake(['*' => Http::response(['slots' => [
        'feed' => ['ad' => [...servedAd('cr_native'), 'format' => $format]],
    ]])]);

    expect(client()->slots(PAGE, ['feed' => 'zn_feed'])['feed']['id'])->toBe('cr_native');
})->with(['native', 'in_feed', 'text']);

test('a native creative with no renderable content is dropped', function () {
    khadsConfigured();
    Http::fake(['*' => Http::response(['slots' => [
        'feed' => ['ad' => [
            'id' => 'cr_empty',
            'format' => 'native',
            'impression_url' => 'https://ads.test/api/v1/imp/cr_empty.gif',
            'click_url' => 'https://ads.test/api/v1/click/cr_empty',
        ]],
    ]])]);

    expect(client()->slots(PAGE, ['feed' => 'zn_feed']))->toBe(['feed' => null]);
});

test('an ad whose URLs are not plain http(s) never reaches the page', function () {
    khadsConfigured();
    config()->set('khads.zones.post', 'zn_post');
    Http::fake(['*' => Http::response(['slots' => [
        // A poisoned click or impression URL kills the whole ad; a poisoned image only loses the image.
        'feed' => ['ad' => [...servedAd('cr_feed'), 'click_url' => 'javascript:alert(1)']],
        'post' => ['ad' => [...servedAd('cr_post'), 'impression_url' => 'ftp://ads.test/imp']],
        'sidebar' => ['ad' => [...servedAd('cr_side'), 'image' => 'data:text/html,x']],
    ]])]);

    $slots = client()->slots(PAGE, config('khads.zones'));

    expect($slots['feed'])->toBeNull()
        ->and($slots['post'])->toBeNull()
        ->and($slots['sidebar']['id'])->toBe('cr_side')
        ->and($slots['sidebar']['image'])->toBeNull();
});

test('long copy is trimmed before it reaches the page', function () {
    khadsConfigured();
    Http::fake(['*' => Http::response(['slots' => [
        'feed' => ['ad' => [...servedAd('cr_long'), 'headline' => str_repeat('a', 200), 'body' => '  padded  ']],
    ]])]);

    $ad = client()->slots(PAGE, ['feed' => 'zn_feed'])['feed'];

    expect($ad['headline'])->toBe(str_repeat('a', 180).'…')
        ->and($ad['body'])->toBe('padded');
});

test('nothing is asked while ads are off, unconfigured, or a placement has no zone', function () {
    khadsConfigured();
    config()->set('khads.zones.sidebar', null);
    Http::fake();

    expect(client()->slots(PAGE, config('khads.zones')))->toHaveKeys(['feed'])->not->toHaveKey('sidebar');
    Http::assertSentCount(1);

    config()->set('khads.enabled', false);
    expect(client()->slots(PAGE, config('khads.zones')))->toBe(['feed' => null]);

    config()->set('khads.enabled', true);
    config()->set('khads.api_key', null);
    expect(client()->slots(PAGE, config('khads.zones')))->toBe(['feed' => null]);

    config()->set('khads.api_key', 'pk_x');
    config()->set('khads.base_url', '');
    expect(client()->slots(PAGE, config('khads.zones')))->toBe(['feed' => null]);

    Http::assertSentCount(1);
});

test('status reports configuration and the circuit without asking the ad server', function () {
    khadsConfigured();
    config()->set('khads.zones', ['feed' => 'zn_feed', 'sidebar' => null, 'post' => '']);
    config()->set('khads.console_url', 'https://ads.test/publisher');
    Cache::put(client()->circuitKey(), true, 60);
    Http::fake();

    expect(client()->status())->toBe([
        'enabled' => true,
        'configured' => true,
        'zones' => ['feed'],
        'circuitOpen' => true,
        'consoleUrl' => 'https://ads.test/publisher',
    ]);

    config()->set('khads.zones', []);
    config()->set('khads.console_url', 'javascript:alert(1)');
    expect(client()->status())->toMatchArray(['configured' => false, 'zones' => [], 'consoleUrl' => null]);

    Http::assertNothingSent();
});

test('the advertiser link exists only while acquisition is open and the destination is safe', function () {
    config()->set('khads.advertise_enabled', false);
    config()->set('khads.advertiser_url', 'https://ads.test/register');
    expect(client()->advertiserUrl())->toBeNull();

    config()->set('khads.advertise_enabled', true);
    expect(client()->advertiserUrl())->toBe('https://ads.test/register');

    foreach (['', 'javascript:alert(1)', 'not a url'] as $unsafe) {
        config()->set('khads.advertiser_url', $unsafe);
        expect(client()->advertiserUrl())->toBeNull();
    }
});
