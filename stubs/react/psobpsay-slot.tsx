import type { ReactNode } from 'react';
import type { SpaceAd, SpaceDisclosure, SponsoredSpaces } from './types';
import { useViewableImpression } from './use-viewable-impression';

/** The label each disclosure word is shown as. Pass `labels` to translate. */
export const disclosureLabels: Record<SpaceDisclosure, string> = {
    sponsored: 'Sponsored',
    promoted: 'Promoted',
    presented: 'Presented by',
    paid: 'Paid partner',
    ad: 'Ad',
};

/**
 * The box a space keeps while its ads load, so the page does not jump when they arrive.
 * Mirrors the `skeleton` of each template in the ad server's catalogue (`templates` in
 * the manifest); keep them in step when a template is added there.
 */
const skeletons: Record<string, { aspectRatio?: string; height?: number }> = {
    card_landscape: { aspectRatio: '16/10' },
    card_portrait: { aspectRatio: '4/5' },
    row_compact: { height: 84 },
    bar_logo: { height: 78 },
    rail: { height: 220 },
    logo_grid: { height: 180 },
    story_video: { aspectRatio: '9/16' },
    interstitial: { aspectRatio: '9/16' },
    reward: { height: 220 },
    hero_cobrand: { height: 380 },
    promoted_content: { aspectRatio: '16/10' },
};

export type SpaceRenderer = (ad: SpaceAd, label: string) => ReactNode;

/**
 * A templated space: `<PsobPsaySlot id="web.grid.card" template="card_landscape" ads={sponsored} />`.
 *
 * Published by khable/psobpsay-client. Draws a skeleton while the deferred prop loads, nothing
 * at all when the space went unfilled, and otherwise each ad through the renderer given
 * for its template (your design system's card), falling back to a plain card. Every ad is
 * framed the same way whatever draws it: one link through the click redirect with
 * `rel="sponsored nofollow noopener"`, a new-tab hint that is always read out, and its own
 * viewable-impression count. A renderer only draws the inside and must show the label.
 */
export function PsobPsaySlot({
    id,
    template,
    ads,
    renderers = {},
    labels = disclosureLabels,
    newTabHint = '(opens in a new tab)',
    className,
}: {
    id: string;
    template: string;
    ads: SponsoredSpaces | undefined;
    renderers?: Partial<Record<string, SpaceRenderer>>;
    labels?: Record<SpaceDisclosure, string>;
    newTabHint?: string;
    className?: string;
}) {
    const slotAds = ads?.[id];

    if (ads === undefined) {
        return (
            <div
                aria-hidden="true"
                data-psobpsay-slot={id}
                className={`bg-current/5 animate-pulse rounded-md ${className ?? ''}`}
                style={skeletons[template] ?? { height: 120 }}
            />
        );
    }

    if (!slotAds || slotAds.length === 0) {
        return null;
    }

    const render = renderers[template] ?? defaultRenderer;

    return (
        <div data-psobpsay-slot={id} className={className}>
            {slotAds.map((ad) => (
                <SpaceAdFrame key={ad.id} ad={ad} newTabHint={newTabHint}>
                    {render(ad, labels[ad.disclosure] ?? labels.sponsored)}
                </SpaceAdFrame>
            ))}
        </div>
    );
}

function SpaceAdFrame({
    ad,
    newTabHint,
    children,
}: {
    ad: SpaceAd;
    newTabHint: string;
    children: ReactNode;
}) {
    const ref = useViewableImpression(ad.impressionUrl, ad.frequency);

    return (
        <article ref={ref} data-card-id={ad.id}>
            <a
                href={ad.clickUrl}
                target="_blank"
                rel="sponsored nofollow noopener"
                className="block h-full"
            >
                {children}
                <span className="sr-only">{newTabHint}</span>
            </a>
        </article>
    );
}

const defaultRenderer: SpaceRenderer = (ad, label) => {
    const picture =
        ad.assets.landscape ?? ad.assets.square ?? ad.assets.portrait ?? null;

    return (
        <div className="flex h-full flex-col">
            {picture ? (
                <img
                    src={picture.src}
                    srcSet={picture.srcset ?? undefined}
                    width={picture.width ?? undefined}
                    height={picture.height ?? undefined}
                    alt=""
                    loading="lazy"
                    className="w-full object-cover"
                />
            ) : null}
            <div className="flex min-w-0 flex-1 flex-col p-4">
                <div className="flex flex-wrap items-center gap-x-1.5 text-sm">
                    {ad.logo ? (
                        <img
                            src={ad.logo.src}
                            alt=""
                            width={20}
                            height={20}
                            className="size-5 rounded-sm object-contain"
                        />
                    ) : null}
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
                {ad.promo ? (
                    <p className="mt-2 font-mono text-sm">{ad.promo.code}</p>
                ) : null}
                {ad.cta ? (
                    <p className="mt-auto pt-3 text-sm font-medium">{ad.cta}</p>
                ) : null}
            </div>
        </div>
    );
};
