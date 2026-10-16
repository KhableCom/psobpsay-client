<?php

use Khable\PsobPsayClient\Support\SiteMatcher;

$sites = [
    ['domain' => 'site.test', 'context_schema' => ['city' => ['phnom-penh']], 'zones' => [['slot_id' => 'a', 'key' => 'zn_a']]],
    ['domain' => 'events.site.test', 'context_schema' => ['city' => ['siem-reap']], 'zones' => [['slot_id' => 'b', 'key' => 'zn_b']]],
    ['domain' => 'sibling.test', 'context_schema' => ['secret' => ['x']], 'zones' => [['slot_id' => 'a', 'key' => 'zn_sibling']]],
];

test('matches bare domains and canonical www hosts only', function (string $host, string $domain, bool $matches) {
    expect(SiteMatcher::hostMatchesDomain($host, $domain))->toBe($matches);
})->with([
    ['site.test', 'site.test', true],
    ['www.site.test', 'site.test', true],
    ['SITE.TEST', 'site.test', true],
    ['events.site.test', 'site.test', false],
    ['site.test.evil', 'site.test', false],
    ['not-site.test', 'site.test', false],
    ['events.site.test', 'events.site.test', true],
    ['www.events.site.test', 'events.site.test', true],
    ['', 'site.test', false],
]);

test('resolves the correct site and never borrows a sibling schema', function () use ($sites) {
    expect(SiteMatcher::siteForPage($sites, 'https://www.site.test/events')['domain'])->toBe('site.test')
        ->and(SiteMatcher::siteForPage($sites, 'https://events.site.test/')['domain'])->toBe('events.site.test')
        ->and(SiteMatcher::siteForPage($sites, 'https://other.test/'))->toBeNull()
        ->and(SiteMatcher::siteForHost($sites, 'sibling.test')['domain'])->toBe('sibling.test');

    $matched = SiteMatcher::siteForConfiguredDomain($sites, 'www.site.test');
    expect($matched['context_schema'])->toBe(['city' => ['phnom-penh']])
        ->and($matched)->not->toHaveKey('secret');
});

test('ignores malformed manifest site rows', function () {
    $sites = [null, 'nope', ['domain' => 1], ['domain' => 'ok.test']];
    expect(SiteMatcher::siteForHost($sites, 'ok.test')['domain'])->toBe('ok.test')
        ->and(SiteMatcher::siteForHost($sites, 'missing.test'))->toBeNull();
});
