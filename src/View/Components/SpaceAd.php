<?php

namespace Khable\PsobPsayClient\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * One templated space ad for Blade: assets, disclosure, and promo controls outside
 * the billable click link. Used by {@see Slot}; keep {@see Sponsored} for legacy
 * `slotsFor()` cards.
 */
class SpaceAd extends Component
{
    /**
     * @param  array<string, mixed>  $ad
     * @param  array<string, string>  $labels
     */
    public function __construct(
        public array $ad,
        public ?string $template = null,
        public array $labels = [],
        public string $newTabHint = '(opens in a new tab)',
        public ?string $nonce = null,
    ) {
        $this->template = $template
            ?? (is_string($ad['template'] ?? null) ? $ad['template'] : 'card_landscape');
    }

    public function shouldRender(): bool
    {
        return is_string($this->ad['clickUrl'] ?? null) && is_string($this->ad['impressionUrl'] ?? null);
    }

    public function label(): string
    {
        $disclosure = is_string($this->ad['disclosure'] ?? null) ? $this->ad['disclosure'] : 'sponsored';

        return $this->labels[$disclosure]
            ?? Slot::Labels[$disclosure]
            ?? Slot::Labels['sponsored'];
    }

    public function layoutClass(): string
    {
        return match ($this->template) {
            'rail' => 'psobpsay-space-ad--rail-card',
            'logo_grid' => 'psobpsay-space-ad--grid-card',
            'row_compact' => 'psobpsay-space-ad--row',
            'bar_logo' => 'psobpsay-space-ad--bar',
            'hero_cobrand' => 'psobpsay-space-ad--hero',
            'reward' => 'psobpsay-space-ad--reward',
            'card_portrait' => 'psobpsay-space-ad--portrait',
            default => 'psobpsay-space-ad--landscape',
        };
    }

    /**
     * @param  list<string>  $preferred
     * @return array{src: string, srcset: string|null, width: int|null, height: int|null}|null
     */
    public function picture(array $preferred = ['landscape', 'square', 'portrait']): ?array
    {
        $assets = is_array($this->ad['assets'] ?? null) ? $this->ad['assets'] : [];

        foreach ($preferred as $role) {
            if (is_array($assets[$role] ?? null) && is_string($assets[$role]['src'] ?? null)) {
                return [
                    'src' => $assets[$role]['src'],
                    'srcset' => is_string($assets[$role]['srcset'] ?? null) ? $assets[$role]['srcset'] : null,
                    'width' => is_int($assets[$role]['width'] ?? null) ? $assets[$role]['width'] : null,
                    'height' => is_int($assets[$role]['height'] ?? null) ? $assets[$role]['height'] : null,
                ];
            }
        }

        if (is_array($this->ad['logo'] ?? null) && is_string($this->ad['logo']['src'] ?? null)) {
            return [
                'src' => $this->ad['logo']['src'],
                'srcset' => is_string($this->ad['logo']['srcset'] ?? null) ? $this->ad['logo']['srcset'] : null,
                'width' => is_int($this->ad['logo']['width'] ?? null) ? $this->ad['logo']['width'] : null,
                'height' => is_int($this->ad['logo']['height'] ?? null) ? $this->ad['logo']['height'] : null,
            ];
        }

        if (is_string($this->ad['image'] ?? null)) {
            return ['src' => $this->ad['image'], 'srcset' => null, 'width' => null, 'height' => null];
        }

        return null;
    }

    public function render(): View
    {
        return view('psobpsay::components.space-ad');
    }
}
