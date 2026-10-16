@php
    $layout = match ($template) {
        'rail' => 'psobpsay-slot--layout-rail',
        'logo_grid' => 'psobpsay-slot--layout-logo-grid',
        default => 'psobpsay-slot--layout-stack',
    };
@endphp
<div {{ $attributes->class(['psobpsay-slot', 'psobpsay-slot--'.$template => $template !== null, $layout]) }} data-psobpsay-slot="{{ $id }}" @if($template) data-psobpsay-template="{{ $template }}" @endif>
    @foreach ($slotAds as $ad)
        <x-psobpsay::space-ad
            :ad="$ad"
            :template="$template ?? ($ad['template'] ?? null)"
            :labels="$labels"
            :nonce="$nonce"
        />
    @endforeach
</div>
