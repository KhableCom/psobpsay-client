<?php

use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Khable\PsobPsayClient\PsobPsayClient;

beforeEach(fn () => psobpsayConfigured());

test('reports are cached by query and key and expire after sixty seconds', function () {
    Http::fake(['https://ads.test/api/v1/publisher/report*' => Http::response(['rows' => [], 'totals' => ['clicks' => 4]])]);
    $client = app(PsobPsayClient::class);
    expect($client->report('2026-10-01', '2026-10-15'))->toBe(['rows' => [], 'totals' => ['clicks' => 4]]);
    $client->report('2026-10-01', '2026-10-15');
    Http::assertSentCount(1);
    $client->report('2026-10-01', '2026-10-15', 'zone');
    $client->report('2026-10-01', '2026-10-15', 'zone', 'site.test');
    Http::assertSentCount(3);
    $this->travel(61)->seconds();
    $client->report('2026-10-01', '2026-10-15');
    Http::assertSentCount(4);
    config()->set('psobpsay.api_key', 'pk_'.str_repeat('a', 31).'b');
    $client->report('2026-10-01', '2026-10-15');
    Http::assertSentCount(5);
});

test('report failures open the circuit only for outages', function (int $status, bool $opensCircuit) {
    Exceptions::fake();
    Http::fake(['https://ads.test/*' => Http::response([], $status)]);
    $client = app(PsobPsayClient::class);
    expect($client->report('2026-10-01', '2026-10-15'))->toBeNull()
        ->and($client->status()['circuitOpen'])->toBe($opensCircuit);
})->with([[401, false], [422, false], [429, true], [503, true]]);

test('malformed reports are not cached', function () {
    Http::fake(['https://ads.test/*' => Http::sequence()->push(['rows' => null])->push(['rows' => [], 'totals' => []])]);
    $client = app(PsobPsayClient::class);
    expect($client->report('2026-10-01', '2026-10-15'))->toBeNull()
        ->and($client->report('2026-10-01', '2026-10-15'))->toBe(['rows' => [], 'totals' => []]);
    Http::assertSentCount(2);
});

test('report connection failures fail empty and stop repeated requests', function () {
    Exceptions::fake();
    Http::fake(['https://ads.test/*' => Http::failedConnection()]);
    $client = app(PsobPsayClient::class);
    expect($client->report('2026-10-01', '2026-10-15'))->toBeNull()
        ->and($client->status()['circuitOpen'])->toBeTrue()
        ->and($client->report('2026-10-01', '2026-10-15'))->toBeNull();
});
