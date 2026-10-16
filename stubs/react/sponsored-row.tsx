import type { SponsoredAd } from './types';
import { useViewableImpression } from './use-viewable-impression';

/**
 * A sponsored card in a list. Published by khable/psobpsay-client as a starting point:
 * restyle it freely to match your own cards, keep three things — the whole card is one
 * link through the ad server's click redirect with `rel="sponsored nofollow noopener"`,
 * the label is always visible so nobody mistakes it for content, and the new-tab hint
 * is always read out, not only when there is a call to action.
 */
export function SponsoredRow({
    ad,
    label = 'Sponsored',
    newTabHint = '(opens in a new tab)',
    className,
}: {
    ad: SponsoredAd;
    label?: string;
    newTabHint?: string;
    className?: string;
}) {
    const ref = useViewableImpression(ad.impressionUrl, ad.frequency);

    return (
        <article ref={ref} data-ad-id={ad.id} className={className}>
            <a
                href={ad.clickUrl}
                target="_blank"
                rel="sponsored nofollow noopener"
                className="flex h-full flex-col"
            >
                {ad.image ? (
                    /*
                     * The ad server checks a creative image for size, not shape, so
                     * the ratio is unknown: keep a fixed box and contain the image
                     * rather than crop half the creative away.
                     */
                    <img
                        src={ad.image}
                        alt=""
                        width={1200}
                        height={630}
                        loading="lazy"
                        className="aspect-[1200/630] w-full object-contain"
                    />
                ) : null}
                <div className="flex min-w-0 flex-1 flex-col p-4">
                    <div className="flex flex-wrap items-center gap-x-1.5 text-sm">
                        {ad.brand ? (
                            <span className="truncate font-semibold">
                                {ad.brand}
                            </span>
                        ) : null}
                        <span className="text-xs uppercase opacity-70">
                            {label}
                        </span>
                    </div>
                    {ad.headline ? (
                        <p className="mt-1 text-sm font-semibold">{ad.headline}</p>
                    ) : null}
                    {ad.body ? (
                        <p className="mt-1 text-sm opacity-80">{ad.body}</p>
                    ) : null}
                    {ad.cta ? (
                        <p className="mt-auto pt-3 text-sm font-medium">{ad.cta}</p>
                    ) : null}
                    <span className="sr-only">{newTabHint}</span>
                </div>
            </a>
        </article>
    );
}
