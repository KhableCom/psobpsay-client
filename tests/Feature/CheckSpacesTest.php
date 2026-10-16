<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    khadsConfigured();
    config()->set('khads.site', 'site.test');
    config()->set('khads.zones', ['feed' => 'zn_feed']);
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
    $this->artisan('khads:check')->expectsOutput('Publisher connection and 1 ad spaces are healthy.')->assertSuccessful();
});

test('check describes unhealthy placements', function (array $zone, string $message) {
    Http::fake(['https://ads.test/*' => Http::response(healthyCheckManifest($zone))]);
    $this->artisan('khads:check')->expectsOutput('feed: '.$message)->assertFailed();
})->with([
    [['key' => 'zn_other'], 'zone missing or key unknown'],
    [['is_active' => false], 'zone inactive'],
    [['template' => 'story_video'], 'template has no bundled renderer (story_video)'],
    [['requests_24h' => 0], 'no requests recorded in the last 24 hours'],
]);

test('check fails when the site cannot serve', function () {
    $manifest = healthyCheckManifest();
    $manifest['sites'][0]['can_serve'] = false;
    $manifest['sites'][0]['reason'] = 'site_unverified';
    Http::fake(['https://ads.test/*' => Http::response($manifest)]);
    $this->artisan('khads:check')->expectsOutput('Site cannot serve: site_unverified')->assertFailed();
});

test('check detects a literal slot referenced in React but absent from the manifest', function () {
    $path = resource_path('js/khads-check-test.tsx');
    File::ensureDirectoryExists(dirname($path));
    File::put($path, '<KhadsSlot id="web.missing" template="rail" />');
    Http::fake(['https://ads.test/*' => Http::response(healthyCheckManifest())]);
    try {
        $this->artisan('khads:check')->expectsOutput('web.missing: zone missing or key unknown')->assertFailed();
    } finally {
        File::delete($path);
    }
});
