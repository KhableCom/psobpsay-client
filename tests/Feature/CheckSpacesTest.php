<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PsobPsay\Client\PsobPsayClient;
use PsobPsay\Client\SpaceDiagnostics;
use PsobPsay\Client\Support\ReferencedSlots;

beforeEach(function () {
    psobpsayConfigured();
    config()->set('psobpsay.site', 'site.test');
    config()->set('psobpsay.zones', ['feed' => 'zn_feed']);
});

function healthyCheckManifest(array $zone = []): array
{
    return ['sites' => [['domain' => 'site.test', 'can_serve' => true, 'zones' => [[
        'key' => 'zn_feed', 'slot_id' => 'feed', 'template' => 'card_landscape',
        'format' => 'native', 'is_active' => true, 'requests_24h' => 10, ...$zone,
    ]]]]];
}

test('check succeeds for a serving site and configured placement', function () {
    Http::fake(['https://ads.test/*' => Http::response(healthyCheckManifest())]);
    $this->artisan('psobpsay:check')->expectsOutput('Publisher connection and 1 ad spaces are healthy.')->assertSuccessful();
});

test('check describes unhealthy placements', function (array $zone, string $message) {
    Http::fake(['https://ads.test/*' => Http::response(healthyCheckManifest($zone))]);
    $this->artisan('psobpsay:check')->expectsOutput('feed: '.$message)->assertFailed();
})->with([
    [['key' => 'zn_other'], 'zone missing or key unknown'],
    [['is_active' => false], 'zone inactive'],
    [['template' => 'story_video'], 'template has no bundled renderer (story_video)'],
    [['template' => 'interstitial'], 'template has no bundled renderer (interstitial)'],
]);

test('reward is an advertised web template and passes structural checks', function () {
    Http::fake(['https://ads.test/*' => Http::response(healthyCheckManifest([
        'template' => 'reward',
        'requests_24h' => 3,
    ]))]);
    $this->artisan('psobpsay:check')->assertSuccessful();
    expect(PsobPsayClient::SupportedTemplates)->toContain('reward')
        ->and(PsobPsayClient::SupportedTemplates)->toContain('promoted_content')
        ->and(PsobPsayClient::UnsupportedWebTemplates)
        ->toContain('story_video')
        ->toContain('interstitial')
        ->not->toContain('promoted_content');
});

test('missing traffic is a warning by default and a failure with --strict-traffic', function () {
    Http::fake(['https://ads.test/*' => Http::response(healthyCheckManifest(['requests_24h' => 0]))]);
    $this->artisan('psobpsay:check')
        ->expectsOutput('feed: no requests recorded in the last 24 hours')
        ->expectsOutputToContain('structurally healthy')
        ->assertSuccessful();
    $this->artisan('psobpsay:check', ['--strict-traffic' => true])
        ->expectsOutput('feed: no requests recorded in the last 24 hours')
        ->assertFailed();
});

test('check fails when the site cannot serve', function () {
    $manifest = healthyCheckManifest();
    $manifest['sites'][0]['can_serve'] = false;
    $manifest['sites'][0]['reason'] = 'site_unverified';
    Http::fake(['https://ads.test/*' => Http::response($manifest)]);
    $this->artisan('psobpsay:check')->expectsOutput('Site cannot serve: site_unverified')->assertFailed();
});

test('check detects a literal slot referenced in React but absent from the manifest', function () {
    $path = resource_path('js/psobpsay-check-test.tsx');
    File::ensureDirectoryExists(dirname($path));
    File::put($path, '<PsobPsaySlot id="web.missing" template="rail" />');
    Http::fake(['https://ads.test/*' => Http::response(healthyCheckManifest())]);
    try {
        $this->artisan('psobpsay:check')->expectsOutput('web.missing: zone missing or key unknown')->assertFailed();
    } finally {
        File::delete($path);
    }
});

test('command and diagnostics builder agree on each finding including scanned slots', function () {
    $path = resource_path('js/psobpsay-check-agree.tsx');
    File::ensureDirectoryExists(dirname($path));
    File::put($path, '<PsobPsaySlot id="web.missing" template="rail" />');
    Http::fake(['https://ads.test/*' => Http::response(healthyCheckManifest(['requests_24h' => 0]))]);

    try {
        $diagnostics = app(SpaceDiagnostics::class)->build('site.test', ReferencedSlots::scan());
        $lines = app(SpaceDiagnostics::class)->lines($diagnostics);

        expect($lines)->toContain('feed: no requests recorded in the last 24 hours')
            ->and($lines)->toContain('web.missing: zone missing or key unknown')
            ->and($diagnostics['ok'])->toBeFalse()
            ->and($diagnostics['structural_ok'])->toBeFalse();

        $this->artisan('psobpsay:check')
            ->expectsOutput('feed: no requests recorded in the last 24 hours')
            ->expectsOutput('web.missing: zone missing or key unknown')
            ->assertFailed();
    } finally {
        File::delete($path);
    }
});

test('inspect keeps status local-compatible and reports cache age without calling serve', function () {
    Http::fake(['https://ads.test/api/v1/publisher/manifest' => Http::response(healthyCheckManifest())]);
    $client = app(PsobPsayClient::class);
    $first = $client->inspect('site.test');
    $second = $client->inspect('site.test');

    expect($first['local'])->toMatchArray($client->status())
        ->and($first['remote']['reachable'])->toBeTrue()
        ->and($second['remote']['cached'])->toBeTrue()
        ->and($second['remote']['age_seconds'])->toBeGreaterThanOrEqual(0)
        ->and(json_encode($first))->not->toContain((string) config('psobpsay.api_key'));

    Http::assertSentCount(1);
});
