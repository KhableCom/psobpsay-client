<div {{ $attributes->class(['psobpsay-slot', 'psobpsay-slot--'.$template => $template !== null]) }} data-psobpsay-slot="{{ $id }}">
    @foreach ($slotAds as $ad)
        <x-psobpsay::sponsored :ad="$ad" :label="$label($ad)" :nonce="$nonce" />
    @endforeach
</div>
