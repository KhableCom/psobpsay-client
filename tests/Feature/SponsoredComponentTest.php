<?php

use Illuminate\Support\Facades\Blade;

/** @return array<string, mixed> */
function shapedAd(array $overrides = []): array
{
    return [
        'id' => 'cr_feed',
        'headline' => 'Headline',
        'body' => 'Body',
        'cta' => 'Learn more',
        'brand' => 'Brand',
        'image' => 'https://cdn.test/a.png',
        'impressionUrl' => 'https://ads.test/api/v1/imp/cr_feed.gif',
        'clickUrl' => 'https://ads.test/api/v1/click/cr_feed',
        ...$overrides,
    ];
}

function renderCard(?array $ad, string $extra = ''): string
{
    return Blade::render('<x-khads::sponsored :ad="$ad" '.$extra.' />', ['ad' => $ad]);
}

test('the card is one sponsored link with the beacon and the creative on it', function () {
    $html = renderCard(shapedAd());

    expect($html)
        ->toContain('href="https://ads.test/api/v1/click/cr_feed"')
        ->toContain('target="_blank"')
        ->toContain('rel="sponsored nofollow noopener"')
        ->toContain('data-khads-impression="https://ads.test/api/v1/imp/cr_feed.gif"')
        ->toContain('data-ad-id="cr_feed"')
        ->toContain('src="https://cdn.test/a.png"')
        ->toContain('>Brand<')
        ->toContain('>Sponsored<')
        ->toContain('>Headline<')
        ->toContain('>Body<')
        ->toContain('>Learn more<')
        ->toContain('(opens in a new tab)')
        ->toContain('<script');
});

test('the new-tab hint is read out even without a call to action, and the image is optional', function () {
    $html = renderCard(shapedAd(['cta' => null, 'image' => null]));

    expect($html)->toContain('(opens in a new tab)')->not->toContain('<img')->not->toContain('khads-ad__cta');
});

test('an empty slot renders nothing at all', function () {
    expect(trim(renderCard(null)))->toBe('')
        ->and(trim(renderCard(['id' => 'x'])))->toBe('');
});

test('copy is escaped', function () {
    expect(renderCard(shapedAd(['headline' => '<b>x</b>'])))->toContain('&lt;b&gt;x&lt;/b&gt;')->not->toContain('<b>x</b>');
});

test('the beacon script is emitted once per page and carries the nonce it is given', function () {
    $html = Blade::render(
        '<x-khads::sponsored :ad="$first" nonce="abc" /><x-khads::sponsored :ad="$second" />',
        ['first' => shapedAd(), 'second' => shapedAd(['id' => 'cr_two'])],
    );

    expect($html)->toContain('data-ad-id="cr_feed"')->toContain('data-ad-id="cr_two"')
        ->and(substr_count($html, '<script'))->toBe(1)
        ->and($html)->toContain('<script nonce="abc">');
});

test('the label and hint are the host\'s to translate', function () {
    $html = renderCard(shapedAd(), 'label="ឧបត្ថម្ភ" new-tab-hint="បើកផ្ទាំងថ្មី"');

    expect($html)->toContain('ឧបត្ថម្ភ')->toContain('បើកផ្ទាំងថ្មី')->not->toContain('>Sponsored<');
});
