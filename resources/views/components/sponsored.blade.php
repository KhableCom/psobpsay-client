<article {{ $attributes->class(['psobpsay-ad']) }} data-ad-id="{{ $ad['id'] ?? '' }}" data-psobpsay-impression="{{ $ad['impressionUrl'] }}">
    <a class="psobpsay-ad__link" href="{{ $ad['clickUrl'] }}" target="_blank" rel="sponsored nofollow noopener">
        @if (! empty($ad['image']))
            {{-- The ad server checks a creative image for size, not shape: keep a fixed box and contain the image. --}}
            <img class="psobpsay-ad__image" src="{{ $ad['image'] }}" alt="" width="1200" height="630" loading="lazy">
        @endif
        <div class="psobpsay-ad__content">
            <div class="psobpsay-ad__meta">
                @if (! empty($ad['brand']))
                    <span class="psobpsay-ad__brand">{{ $ad['brand'] }}</span>
                @endif
                <span class="psobpsay-ad__label">{{ $label }}</span>
            </div>
            @if (! empty($ad['headline']))
                <p class="psobpsay-ad__headline">{{ $ad['headline'] }}</p>
            @endif
            @if (! empty($ad['body']))
                <p class="psobpsay-ad__body">{{ $ad['body'] }}</p>
            @endif
            @if (! empty($ad['cta']))
                <p class="psobpsay-ad__cta">{{ $ad['cta'] }}</p>
            @endif
            <span class="psobpsay-ad__hint" style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0">{{ $newTabHint }}</span>
        </div>
    </a>
</article>
@once
<script{!! $nonce !== null ? ' nonce="'.e($nonce).'"' : '' !!}>
/* Counts an impression the way the ad server bills it: half the card on screen for a full second, once. */
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
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', scan); } else { scan(); }
})();
</script>
@endonce
