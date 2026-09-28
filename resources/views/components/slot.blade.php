<div {{ $attributes->class(['khads-slot', 'khads-slot--'.$template => $template !== null]) }} data-khads-slot="{{ $id }}">
    @foreach ($slotAds as $ad)
        <x-khads::sponsored :ad="$ad" :label="$label($ad)" :nonce="$nonce" />
    @endforeach
</div>
