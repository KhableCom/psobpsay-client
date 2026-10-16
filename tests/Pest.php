<?php

use Illuminate\Support\Facades\Http;
use Khable\PsobPsayClient\Tests\TestCase;

pest()->extend(TestCase::class)
    ->beforeEach(fn () => Http::preventStrayRequests())
    ->in('Feature');

/** Enough to serve: the switch on, a server, a key, and two placements. */
function psobpsayConfigured(): void
{
    config()->set('psobpsay', [
        'enabled' => true,
        'base_url' => 'https://ads.test',
        'api_key' => 'pk_'.str_repeat('a', 32),
        'timeout_ms' => 500,
        'connect_timeout_ms' => 150,
        'circuit_seconds' => 60,
        'zones' => ['feed' => 'zn_feed', 'sidebar' => 'zn_side'],
    ]);
}

/** @return array<string, mixed> */
function servedAd(string $id): array
{
    return [
        'id' => $id,
        'format' => 'native',
        'headline' => 'Headline',
        'body' => 'Body',
        'cta' => 'Learn more',
        'brand' => 'Brand',
        'image' => 'https://cdn.test/a.png',
        'label' => 'Sponsored',
        'impression_url' => 'https://ads.test/api/v1/imp/'.$id.'.gif',
        'click_url' => 'https://ads.test/api/v1/click/'.$id,
        'render_url' => 'https://ads.test/api/v1/r/'.$id,
        'frequency' => null,
    ];
}
