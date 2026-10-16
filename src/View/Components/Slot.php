<?php

namespace Khable\PsobPsayClient\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Khable\PsobPsayClient\PsobPsayClient;

/**
 * A templated space for Blade sites:
 * `<x-psobpsay::slot id="web.grid.card" template="card_landscape" :ads="$sponsored" />`.
 *
 * Takes the whole answer of {@see PsobPsayClient::spaces()} and draws its own slot, every ad
 * as a sponsored card labelled with the disclosure word the space was served with. An
 * unfilled space renders nothing, so the layout closes up around it. Publish the views to
 * give each template its own look.
 */
class Slot extends Component
{
    /** The label each disclosure word is shown as. Override with the `labels` attribute. */
    public const Labels = [
        'sponsored' => 'Sponsored',
        'promoted' => 'Promoted',
        'presented' => 'Presented by',
        'paid' => 'Paid partner',
        'ad' => 'Ad',
    ];

    /** @var list<array<string, mixed>> */
    public array $slotAds;

    /**
     * @param  array<string, list<array<string, mixed>>>|null  $ads  the page's spaces, by slot id
     * @param  array<string, string>  $labels
     */
    public function __construct(
        public string $id,
        public ?string $template = null,
        ?array $ads = null,
        public array $labels = [],
        public ?string $nonce = null,
    ) {
        $this->slotAds = array_values((array) ($ads[$id] ?? []));
    }

    public function shouldRender(): bool
    {
        return $this->slotAds !== [];
    }

    /** @param  array<string, mixed>  $ad */
    public function label(array $ad): string
    {
        $disclosure = is_string($ad['disclosure'] ?? null) ? $ad['disclosure'] : 'sponsored';

        return $this->labels[$disclosure] ?? self::Labels[$disclosure] ?? self::Labels['sponsored'];
    }

    public function render(): View
    {
        return view('psobpsay::components.slot');
    }
}
