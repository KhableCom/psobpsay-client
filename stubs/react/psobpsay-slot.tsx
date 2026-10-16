import type { ReactNode } from 'react';
import { AdFeedbackMenu } from './ad-feedback-menu';
import {
    defaultRenderers,
    normalizeRendererResult,
    templateLayouts,
    type SpaceRenderer,
} from './renderers';
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
 *
 * Mobile-only catalogue templates (`story_video`, `interstitial`) keep skeleton entries
 * for hosts that render them natively, but this web client does not advertise them in
 * `SupportedTemplates`.
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

export type { SpaceRenderer };

/**
 * A templated space: `<PsobPsaySlot id="web.grid.card" template="card_landscape" ads={sponsored} />`.
 *
 * Published by khable/psobpsay-client. Draws a skeleton while the deferred prop loads, nothing
 * at all when the space went unfilled, and otherwise each ad through the renderer for its
 * template (host override or the bundled layout). Every ad gets one click destination with
 * `rel="sponsored nofollow noopener"`, a new-tab hint, and its own viewable-impression count.
 * Interactive controls (promo copy, dismiss, playback) must be returned as `actions` so they
 * sit outside the billable link.
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
                className={`bg-current/5 animate-pulse rounded-md motion-reduce:animate-none ${className ?? ''}`}
                style={skeletons[template] ?? { height: 120 }}
            />
        );
    }

    if (!slotAds || slotAds.length === 0) {
        return null;
    }

    const render =
        renderers[template] ?? defaultRenderers[template] ?? defaultRenderers.card_landscape;
    const layout = templateLayouts[template] ?? 'flex flex-col gap-3';

    return (
        <div
            data-psobpsay-slot={id}
            data-psobpsay-template={template}
            className={[layout, className].filter(Boolean).join(' ')}
        >
            {slotAds.map((ad) => {
                const { body, actions } = normalizeRendererResult(
                    render(ad, labels[ad.disclosure] ?? labels.sponsored),
                );

                return (
                    <SpaceAdFrame
                        key={ad.id}
                        ad={ad}
                        newTabHint={newTabHint}
                        actions={actions}
                    >
                        {body}
                    </SpaceAdFrame>
                );
            })}
        </div>
    );
}

function SpaceAdFrame({
    ad,
    newTabHint,
    actions,
    children,
}: {
    ad: SpaceAd;
    newTabHint: string;
    actions?: ReactNode;
    children: ReactNode;
}) {
    const ref = useViewableImpression(ad.impressionUrl, ad.frequency);

    return (
        <article
            ref={ref}
            data-card-id={ad.id}
            data-psobpsay-template={ad.template ?? undefined}
            aria-label={`Sponsored${ad.brand ? ` — ${ad.brand}` : ''}${ad.headline ? `: ${ad.headline}` : ''}`}
        >
            <a
                href={ad.clickUrl}
                target="_blank"
                rel="sponsored nofollow noopener"
                className="block h-full"
            >
                {children}
                <span className="sr-only">{newTabHint}</span>
            </a>
            {actions ?? null}
            <AdFeedbackMenu ad={ad} />
        </article>
    );
}
