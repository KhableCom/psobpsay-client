<?php

use Illuminate\Support\Facades\Blade;

/** @return array<string, mixed> */
function spaceAdPayload(array $overrides = []): array
{
    return [
        'id' => 'cr_space',
        'headline' => 'Headline',
        'body' => 'Body copy',
        'cta' => 'Learn more',
        'brand' => 'Brand',
        'image' => null,
        'impressionUrl' => 'https://ads.test/api/v1/imp/cr_space.gif',
        'clickUrl' => 'https://ads.test/api/v1/click/cr_space',
        'frequency' => null,
        'template' => 'card_landscape',
        'disclosure' => 'sponsored',
        'assets' => [
            'landscape' => [
                'src' => 'https://cdn.test/l.jpg',
                'srcset' => 'https://cdn.test/l.jpg 600w, https://cdn.test/l@2x.jpg 1200w',
                'width' => 600,
                'height' => 375,
            ],
            'square' => [
                'src' => 'https://cdn.test/s.jpg',
                'srcset' => null,
                'width' => 200,
                'height' => 200,
            ],
            'portrait' => [
                'src' => 'https://cdn.test/p.jpg',
                'srcset' => null,
                'width' => 400,
                'height' => 500,
            ],
        ],
        'logo' => [
            'src' => 'https://cdn.test/logo.png',
            'srcset' => null,
            'width' => 128,
            'height' => 128,
        ],
        'video' => null,
        'promo' => null,
        ...$overrides,
    ];
}

function renderSlot(string $template, array $ads, string $extra = ''): string
{
    return Blade::render(
        '<x-psobpsay::slot id="web.slot" template="'.$template.'" :ads="$ads" '.$extra.' />',
        ['ads' => ['web.slot' => $ads]],
    );
}

test('landscape cards use srcset assets and a single sponsored click link', function () {
    $html = renderSlot('card_landscape', [spaceAdPayload()]);

    expect($html)
        ->toContain('data-psobpsay-template="card_landscape"')
        ->toContain('srcset="https://cdn.test/l.jpg 600w, https://cdn.test/l@2x.jpg 1200w"')
        ->toContain('href="https://ads.test/api/v1/click/cr_space"')
        ->toContain('rel="sponsored nofollow noopener"')
        ->toContain('data-psobpsay-impression="https://ads.test/api/v1/imp/cr_space.gif"')
        ->toContain('>Sponsored<')
        ->and(substr_count($html, 'href="https://ads.test/api/v1/click/cr_space"'))->toBe(1);
});

test('rails render distinct advertiser cards with one impression hook each', function () {
    $html = renderSlot('rail', [
        spaceAdPayload([
            'id' => 'cr_a',
            'brand' => 'Alpha',
            'disclosure' => 'paid',
            'impressionUrl' => 'https://ads.test/api/v1/imp/cr_a.gif',
            'clickUrl' => 'https://ads.test/api/v1/click/cr_a',
        ]),
        spaceAdPayload([
            'id' => 'cr_b',
            'brand' => 'Beta',
            'disclosure' => 'paid',
            'impressionUrl' => 'https://ads.test/api/v1/imp/cr_b.gif',
            'clickUrl' => 'https://ads.test/api/v1/click/cr_b',
        ]),
    ]);

    expect($html)
        ->toContain('psobpsay-slot--rail')
        ->toContain('data-card-id="cr_a"')
        ->toContain('data-card-id="cr_b"')
        ->toContain('data-psobpsay-impression="https://ads.test/api/v1/imp/cr_a.gif"')
        ->toContain('data-psobpsay-impression="https://ads.test/api/v1/imp/cr_b.gif"')
        ->toContain('>Alpha<')
        ->toContain('>Beta<')
        ->toContain('>Paid partner<')
        ->and(substr_count($html, '<script'))->toBe(1);
});

test('reward keeps promo copy controls outside the billable click link', function () {
    $html = renderSlot('reward', [spaceAdPayload([
        'template' => 'reward',
        'promo' => ['code' => 'SAVE10', 'terms' => 'One use per visitor'],
        'assets' => [],
    ])]);

    expect($html)
        ->toContain('data-psobpsay-promo="SAVE10"')
        ->toContain('>One use per visitor<')
        ->toContain('type="button"')
        ->toContain('psobpsay-space-ad__actions');

    // The promo button must not be nested inside the click anchor.
    expect(preg_match(
        '/<a[^>]*href="https:\/\/ads\.test\/api\/v1\/click\/cr_space"[^>]*>[\s\S]*data-psobpsay-promo="SAVE10"[\s\S]*<\/a>/',
        $html,
    ))->toBe(0);
});

test('row compact prefers the square crop', function () {
    $html = renderSlot('row_compact', [spaceAdPayload(['template' => 'row_compact'])]);

    expect($html)
        ->toContain('src="https://cdn.test/s.jpg"')
        ->toContain('psobpsay-space-ad--row')
        ->not->toContain('https://cdn.test/l.jpg');
});

test('empty spaces collapse to nothing', function () {
    expect(trim(renderSlot('card_landscape', [])))->toBe('');
});

test('copy is escaped in templated space ads', function () {
    $html = renderSlot('card_landscape', [spaceAdPayload(['headline' => '<b>x</b>'])]);

    expect($html)->toContain('&lt;b&gt;x&lt;/b&gt;')->not->toContain('<b>x</b>');
});
