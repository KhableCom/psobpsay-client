@php
    $template = $template ?? 'card_landscape';
    $labelText = $label();
    $logo = is_array($ad['logo'] ?? null) && is_string($ad['logo']['src'] ?? null) ? $ad['logo'] : null;
    $promo = is_array($ad['promo'] ?? null) && is_string($ad['promo']['code'] ?? null) ? $ad['promo'] : null;
    $image = match ($template) {
        'card_portrait' => $picture(['portrait', 'vertical', 'landscape']),
        'row_compact' => $picture(['square', 'landscape']),
        'bar_logo', 'logo_grid', 'reward' => $logo ? [
            'src' => $logo['src'],
            'srcset' => $logo['srcset'] ?? null,
            'width' => $logo['width'] ?? null,
            'height' => $logo['height'] ?? null,
        ] : $picture(['logo', 'square']),
        default => $picture(['landscape', 'square', 'portrait']),
    };
    $aria = 'Sponsored'.(! empty($ad['brand']) ? ' — '.$ad['brand'] : '').(! empty($ad['headline']) ? ': '.$ad['headline'] : '');
@endphp
<article
    {{ $attributes->class(['psobpsay-space-ad', $layoutClass()]) }}
    data-card-id="{{ $ad['id'] ?? '' }}"
    data-psobpsay-impression="{{ $ad['impressionUrl'] }}"
    data-psobpsay-template="{{ $template }}"
    aria-label="{{ $aria }}"
>
    <a class="psobpsay-space-ad__link" href="{{ $ad['clickUrl'] }}" target="_blank" rel="sponsored nofollow noopener">
        @if ($template === 'row_compact')
            <div class="psobpsay-space-ad__row">
                @if ($image)
                    <img class="psobpsay-space-ad__thumb" src="{{ $image['src'] }}" @if($image['srcset']) srcset="{{ $image['srcset'] }}" @endif alt="" @if($image['width']) width="{{ $image['width'] }}" @endif @if($image['height']) height="{{ $image['height'] }}" @endif loading="lazy" decoding="async">
                @endif
                <div class="psobpsay-space-ad__content">
                    @include('psobpsay::components.partials.space-meta', ['ad' => $ad, 'labelText' => $labelText, 'showLogo' => true])
                    @if (! empty($ad['headline']))<p class="psobpsay-space-ad__headline">{{ $ad['headline'] }}</p>@endif
                    @if (! empty($ad['cta']))<p class="psobpsay-space-ad__cta">{{ $ad['cta'] }}</p>@endif
                </div>
            </div>
        @elseif ($template === 'bar_logo')
            <div class="psobpsay-space-ad__bar">
                @if ($image)
                    <img class="psobpsay-space-ad__logo" src="{{ $image['src'] }}" @if($image['srcset']) srcset="{{ $image['srcset'] }}" @endif alt="" width="40" height="40" loading="lazy" decoding="async">
                @endif
                <div class="psobpsay-space-ad__content">
                    @include('psobpsay::components.partials.space-meta', ['ad' => $ad, 'labelText' => $labelText, 'showLogo' => false])
                    @if (! empty($ad['headline']))<p class="psobpsay-space-ad__headline">{{ $ad['headline'] }}</p>@endif
                </div>
                @if (! empty($ad['cta']))<span class="psobpsay-space-ad__cta">{{ $ad['cta'] }}</span>@endif
            </div>
        @elseif ($template === 'logo_grid')
            <div class="psobpsay-space-ad__grid-card">
                @if ($image)
                    <img class="psobpsay-space-ad__logo psobpsay-space-ad__logo--large" src="{{ $image['src'] }}" @if($image['srcset']) srcset="{{ $image['srcset'] }}" @endif alt="" width="64" height="64" loading="lazy" decoding="async">
                @endif
                <span class="psobpsay-space-ad__label">{{ $labelText }}</span>
                @if (! empty($ad['headline']))<p class="psobpsay-space-ad__headline">{{ $ad['headline'] }}</p>@endif
                @if (! empty($ad['body']))<p class="psobpsay-space-ad__body">{{ $ad['body'] }}</p>@endif
            </div>
        @elseif ($template === 'reward')
            <div class="psobpsay-space-ad__content">
                @include('psobpsay::components.partials.space-meta', ['ad' => $ad, 'labelText' => $labelText, 'showLogo' => true])
                @if (! empty($ad['headline']))<p class="psobpsay-space-ad__headline">{{ $ad['headline'] }}</p>@endif
                @if (! empty($ad['cta']))<p class="psobpsay-space-ad__cta">{{ $ad['cta'] }}</p>@endif
            </div>
        @elseif ($template === 'hero_cobrand')
            <div class="psobpsay-space-ad__hero">
                @if ($image)
                    <img class="psobpsay-space-ad__hero-image" src="{{ $image['src'] }}" @if($image['srcset']) srcset="{{ $image['srcset'] }}" @endif alt="" @if($image['width']) width="{{ $image['width'] }}" @endif @if($image['height']) height="{{ $image['height'] }}" @endif loading="lazy" decoding="async">
                @endif
                <div class="psobpsay-space-ad__hero-copy">
                    @include('psobpsay::components.partials.space-meta', ['ad' => $ad, 'labelText' => $labelText, 'showLogo' => true])
                    @if (! empty($ad['headline']))<p class="psobpsay-space-ad__headline">{{ $ad['headline'] }}</p>@endif
                    @if (! empty($ad['body']))<p class="psobpsay-space-ad__body">{{ $ad['body'] }}</p>@endif
                    @if (! empty($ad['cta']))<p class="psobpsay-space-ad__cta">{{ $ad['cta'] }}</p>@endif
                </div>
            </div>
        @else
            {{-- card_landscape, rail cards, card_portrait --}}
            <div class="psobpsay-space-ad__stack">
                @if ($image)
                    <img
                        class="psobpsay-space-ad__image {{ $template === 'card_portrait' ? 'psobpsay-space-ad__image--portrait' : 'psobpsay-space-ad__image--landscape' }}"
                        src="{{ $image['src'] }}"
                        @if($image['srcset']) srcset="{{ $image['srcset'] }}" @endif
                        alt=""
                        @if($image['width']) width="{{ $image['width'] }}" @endif
                        @if($image['height']) height="{{ $image['height'] }}" @endif
                        loading="lazy"
                        decoding="async"
                    >
                @endif
                <div class="psobpsay-space-ad__content">
                    @include('psobpsay::components.partials.space-meta', ['ad' => $ad, 'labelText' => $labelText, 'showLogo' => true])
                    @if (! empty($ad['headline']))<p class="psobpsay-space-ad__headline">{{ $ad['headline'] }}</p>@endif
                    @if ($template !== 'card_portrait' && $template !== 'rail' && ! empty($ad['body']))
                        <p class="psobpsay-space-ad__body">{{ $ad['body'] }}</p>
                    @endif
                    @if ($template !== 'card_portrait' && $template !== 'rail' && ! empty($ad['cta']))
                        <p class="psobpsay-space-ad__cta">{{ $ad['cta'] }}</p>
                    @endif
                </div>
            </div>
        @endif
        <span class="psobpsay-space-ad__hint" style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0">{{ $newTabHint }}</span>
    </a>

    @if ($template === 'reward' && $promo)
        <div class="psobpsay-space-ad__actions">
            <code class="psobpsay-space-ad__promo-code">{{ $promo['code'] }}</code>
            <button
                type="button"
                class="psobpsay-space-ad__promo-copy"
                data-psobpsay-promo="{{ $promo['code'] }}"
                aria-live="polite"
            >Copy code</button>
            @if (! empty($promo['terms']))
                <p class="psobpsay-space-ad__promo-terms">{{ $promo['terms'] }}</p>
            @endif
        </div>
    @endif
</article>
@once
<script{!! $nonce !== null ? ' nonce="'.e($nonce).'"' : '' !!}>
(function () {
    if (typeof IntersectionObserver === 'undefined') { return; }
    var attribute = 'data-psobpsay-impression';
    function watch(element) {
        if (element.__psobpsay) { return; }
        element.__psobpsay = true;
        var url = element.getAttribute(attribute), timer = null, sent = false, entry = null;
        function viewable() {
            return !!entry && entry.isIntersecting && entry.intersectionRatio >= 0.5 && document.visibilityState === 'visible';
        }
        function clear() { if (timer) { clearTimeout(timer); timer = null; } }
        function start() {
            if (sent || timer || !viewable()) { return; }
            timer = setTimeout(function () {
                timer = null;
                if (!viewable()) { return; }
                sent = true;
                new Image().src = url;
                observer.disconnect();
                document.removeEventListener('visibilitychange', onVisibility);
            }, 1000);
        }
        function onVisibility() { if (document.visibilityState === 'visible') { start(); } else { clear(); } }
        var observer = new IntersectionObserver(function (entries) {
            entry = entries[0];
            if (sent) { return; }
            if (viewable()) { start(); } else { clear(); }
        }, { threshold: 0.5 });
        observer.observe(element);
        document.addEventListener('visibilitychange', onVisibility);
    }
    function scan() {
        var elements = document.querySelectorAll('[' + attribute + ']');
        for (var i = 0; i < elements.length; i++) { watch(elements[i]); }
        var buttons = document.querySelectorAll('[data-psobpsay-promo]');
        for (var j = 0; j < buttons.length; j++) {
            if (buttons[j].__psobpsayPromo) { continue; }
            buttons[j].__psobpsayPromo = true;
            buttons[j].addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                var button = event.currentTarget;
                var code = button.getAttribute('data-psobpsay-promo') || '';
                if (!navigator.clipboard || !navigator.clipboard.writeText) {
                    button.textContent = 'Could not copy';
                    return;
                }
                navigator.clipboard.writeText(code).then(function () {
                    button.textContent = 'Copied';
                }).catch(function () {
                    button.textContent = 'Could not copy';
                });
            });
        }
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', scan); } else { scan(); }
})();
</script>
@endonce
