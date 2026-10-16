@php
    $showLogo = $showLogo ?? true;
    $logo = is_array($ad['logo'] ?? null) && is_string($ad['logo']['src'] ?? null) ? $ad['logo'] : null;
@endphp
<div class="psobpsay-space-ad__meta">
    @if ($showLogo && $logo)
        <img class="psobpsay-space-ad__logo" src="{{ $logo['src'] }}" @if(!empty($logo['srcset'])) srcset="{{ $logo['srcset'] }}" @endif alt="" width="20" height="20" loading="lazy" decoding="async">
    @endif
    @if (! empty($ad['brand']))
        <span class="psobpsay-space-ad__brand">{{ $ad['brand'] }}</span>
    @endif
    <span class="psobpsay-space-ad__label">{{ $labelText }}</span>
</div>
