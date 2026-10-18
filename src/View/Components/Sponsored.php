<?php

namespace PsobPsay\Client\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use PsobPsay\Client\PsobPsayClient;

/**
 * A sponsored card for Blade sites: `<x-psobpsay::sponsored :ad="$slots['feed'] ?? null" />`.
 *
 * Renders nothing for an empty slot, so a page can drop it in unguarded. The whole
 * card is one outbound link through the ad server's click redirect, and the impression
 * is counted the way the ad server bills it — half the card on screen for a second —
 * by a small script emitted once per page.
 */
class Sponsored extends Component
{
    /**
     * @param  array<string, mixed>|null  $ad  one shaped slot from {@see PsobPsayClient::slotsFor()}
     * @param  string|null  $nonce  the page's CSP nonce, when scripts need one
     */
    public function __construct(
        public ?array $ad = null,
        public string $label = 'Sponsored',
        public string $newTabHint = '(opens in a new tab)',
        public ?string $nonce = null,
    ) {}

    public function shouldRender(): bool
    {
        return is_string($this->ad['clickUrl'] ?? null) && is_string($this->ad['impressionUrl'] ?? null);
    }

    public function render(): View
    {
        return view('psobpsay::components.sponsored');
    }
}
