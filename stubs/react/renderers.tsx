import type { ReactNode } from 'react';
import type { SpaceAd, SpacePicture } from './types';
import { usePromoCopy } from './use-promo-copy';

/** Result of a template renderer: clickable body plus optional controls outside the link. */
export type SpaceRendererResult = {
    body: ReactNode;
    actions?: ReactNode;
};

export type SpaceRenderer = (
    ad: SpaceAd,
    label: string,
) => ReactNode | SpaceRendererResult;

function picture(
    ad: SpaceAd,
    preferred: string[],
): SpacePicture | null {
    for (const role of preferred) {
        const asset = ad.assets[role];
        if (asset) {
            return asset;
        }
    }

    return ad.logo;
}

function Meta({
    ad,
    label,
    logoSize = 20,
    showLogo = true,
}: {
    ad: SpaceAd;
    label: string;
    logoSize?: number;
    showLogo?: boolean;
}) {
    return (
        <div className="flex flex-wrap items-center gap-x-1.5 text-sm">
            {showLogo && ad.logo ? (
                <img
                    src={ad.logo.src}
                    srcSet={ad.logo.srcset ?? undefined}
                    alt=""
                    width={logoSize}
                    height={logoSize}
                    className="rounded-sm object-contain"
                    style={{ width: logoSize, height: logoSize }}
                />
            ) : null}
            {ad.brand ? (
                <span className="truncate font-semibold">{ad.brand}</span>
            ) : null}
            <span className="text-xs uppercase opacity-70">{label}</span>
        </div>
    );
}

function Picture({
    asset,
    className,
}: {
    asset: SpacePicture;
    className?: string;
}) {
    return (
        <img
            src={asset.src}
            srcSet={asset.srcset ?? undefined}
            width={asset.width ?? undefined}
            height={asset.height ?? undefined}
            alt=""
            loading="lazy"
            decoding="async"
            className={className}
        />
    );
}

export const cardLandscape: SpaceRenderer = (ad, label) => {
    const asset = picture(ad, ['landscape']);

    return {
        body: (
            <div className="flex h-full flex-col">
                {asset ? (
                    <Picture
                        asset={asset}
                        className="aspect-[16/10] w-full object-cover"
                    />
                ) : null}
                <div className="flex min-w-0 flex-1 flex-col p-4">
                    <Meta ad={ad} label={label} />
                    {ad.headline ? (
                        <p className="mt-1 text-sm font-semibold">{ad.headline}</p>
                    ) : null}
                    {ad.body ? (
                        <p className="mt-1 text-sm opacity-80">{ad.body}</p>
                    ) : null}
                    {ad.cta ? (
                        <p className="mt-auto pt-3 text-sm font-medium">{ad.cta}</p>
                    ) : null}
                </div>
            </div>
        ),
    };
};

export const cardPortrait: SpaceRenderer = (ad, label) => {
    const asset = picture(ad, ['portrait', 'vertical']);

    return {
        body: (
            <div className="flex h-full flex-col">
                {asset ? (
                    <Picture
                        asset={asset}
                        className="aspect-[4/5] w-full object-cover"
                    />
                ) : null}
                <div className="p-3">
                    <Meta ad={ad} label={label} />
                    {ad.headline ? (
                        <p className="mt-1 text-sm font-semibold">{ad.headline}</p>
                    ) : null}
                </div>
            </div>
        ),
    };
};

export const rowCompact: SpaceRenderer = (ad, label) => {
    const asset = picture(ad, ['square', 'landscape']);

    return {
        body: (
            <div className="flex items-center gap-3 p-3">
                {asset ? (
                    <Picture
                        asset={asset}
                        className="size-14 shrink-0 rounded object-cover"
                    />
                ) : null}
                <div className="min-w-0 flex-1">
                    <Meta ad={ad} label={label} />
                    {ad.headline ? (
                        <p className="mt-0.5 truncate text-sm font-semibold">
                            {ad.headline}
                        </p>
                    ) : null}
                    {ad.cta ? (
                        <p className="mt-0.5 text-sm font-medium">{ad.cta}</p>
                    ) : null}
                </div>
            </div>
        ),
    };
};

export const barLogo: SpaceRenderer = (ad, label) => ({
    body: (
        <div className="flex items-center gap-3 px-4 py-3">
            {ad.logo ? (
                <Picture
                    asset={ad.logo}
                    className="size-10 shrink-0 object-contain"
                />
            ) : null}
            <div className="min-w-0 flex-1">
                <Meta ad={ad} label={label} showLogo={false} />
                {ad.headline ? (
                    <p className="truncate text-sm font-semibold">{ad.headline}</p>
                ) : null}
            </div>
            {ad.cta ? (
                <span className="shrink-0 text-sm font-medium">{ad.cta}</span>
            ) : null}
        </div>
    ),
});

export const railCard: SpaceRenderer = (ad, label) => {
    const asset = picture(ad, ['landscape']);

    return {
        body: (
            <div className="flex w-44 shrink-0 flex-col">
                {asset ? (
                    <Picture
                        asset={asset}
                        className="aspect-[16/10] w-full rounded object-cover"
                    />
                ) : null}
                <div className="mt-2 min-w-0">
                    <Meta ad={ad} label={label} />
                    {ad.headline ? (
                        <p className="mt-0.5 truncate text-sm font-semibold">
                            {ad.headline}
                        </p>
                    ) : null}
                </div>
            </div>
        ),
    };
};

export const logoGridCard: SpaceRenderer = (ad, label) => ({
    body: (
        <div className="flex flex-col items-center gap-2 p-3 text-center">
            {ad.logo ? (
                <Picture
                    asset={ad.logo}
                    className="size-16 object-contain"
                />
            ) : null}
            <span className="text-xs uppercase opacity-70">{label}</span>
            {ad.headline ? (
                <p className="text-sm font-semibold">{ad.headline}</p>
            ) : null}
            {ad.body ? (
                <p className="text-xs opacity-80">{ad.body}</p>
            ) : null}
        </div>
    ),
});

export const heroCobrand: SpaceRenderer = (ad, label) => {
    const asset = picture(ad, ['landscape']);

    return {
        body: (
            <div className="relative flex min-h-[380px] flex-col justify-end overflow-hidden">
                {asset ? (
                    <Picture
                        asset={asset}
                        className="absolute inset-0 size-full object-cover"
                    />
                ) : null}
                <div className="relative bg-gradient-to-t from-black/70 to-transparent p-6 text-white">
                    <Meta ad={ad} label={label} logoSize={28} />
                    {ad.headline ? (
                        <p className="mt-2 text-xl font-semibold">{ad.headline}</p>
                    ) : null}
                    {ad.body ? (
                        <p className="mt-1 text-sm opacity-90">{ad.body}</p>
                    ) : null}
                    {ad.cta ? (
                        <p className="mt-3 text-sm font-medium">{ad.cta}</p>
                    ) : null}
                </div>
            </div>
        ),
    };
};

function RewardActions({ code, terms }: { code: string; terms: string | null }) {
    const { state, copy, label } = usePromoCopy(code);

    return (
        <div className="flex flex-col gap-2 border-t px-4 py-3">
            <div className="flex flex-wrap items-center gap-2">
                <code className="rounded bg-black/5 px-2 py-1 font-mono text-sm">
                    {code}
                </code>
                <button
                    type="button"
                    onClick={() => void copy()}
                    className="rounded border px-2 py-1 text-sm"
                    aria-live="polite"
                >
                    {label}
                </button>
            </div>
            {terms ? <p className="text-xs opacity-80">{terms}</p> : null}
            {state === 'failed' ? (
                <p className="text-xs" role="alert">
                    Copy failed. Select the code and copy it manually.
                </p>
            ) : null}
        </div>
    );
}

export const reward: SpaceRenderer = (ad, label) => ({
    body: (
        <div className="flex flex-col p-4">
            <Meta ad={ad} label={label} logoSize={32} />
            {ad.headline ? (
                <p className="mt-2 text-sm font-semibold">{ad.headline}</p>
            ) : null}
            {ad.cta ? (
                <p className="mt-2 text-sm font-medium">{ad.cta}</p>
            ) : null}
        </div>
    ),
    actions: ad.promo ? (
        <RewardActions code={ad.promo.code} terms={ad.promo.terms} />
    ) : null,
});

/**
 * Default promoted-content card. Hosts usually override this renderer to look up
 * local content by `contentRef` and draw their own card.
 */
export const promotedContent: SpaceRenderer = (ad, label) => (
    <div className="flex flex-col gap-2 p-4">
        <Meta ad={ad} label={label} showLogo={false} />
        {ad.contentRef ? (
            <p className="font-mono text-xs opacity-70">{ad.contentRef}</p>
        ) : null}
        {ad.headline ? (
            <p className="text-sm font-semibold">{ad.headline}</p>
        ) : null}
    </div>
);

/** Built-in renderers for templates this web client advertises in SupportedTemplates. */
export const defaultRenderers: Record<string, SpaceRenderer> = {
    card_landscape: cardLandscape,
    card_portrait: cardPortrait,
    row_compact: rowCompact,
    bar_logo: barLogo,
    rail: railCard,
    logo_grid: logoGridCard,
    hero_cobrand: heroCobrand,
    reward,
    promoted_content: promotedContent,
};

/** Multi-ad container classes. One viewability beacon still fires per ad card. */
export const templateLayouts: Record<string, string> = {
    rail: 'flex gap-3 overflow-x-auto',
    logo_grid: 'grid grid-cols-2 gap-3 sm:grid-cols-4',
};

export function normalizeRendererResult(
    result: ReactNode | SpaceRendererResult,
): SpaceRendererResult {
    if (
        result !== null &&
        typeof result === 'object' &&
        'body' in (result as SpaceRendererResult)
    ) {
        return result as SpaceRendererResult;
    }

    return { body: result };
}
