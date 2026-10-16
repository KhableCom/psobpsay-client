/** One sponsored placement as the ad server returned it, already trimmed server side. */
export type SponsoredAd = {
    id: string;
    headline: string | null;
    body: string | null;
    cta: string | null;
    brand: string | null;
    image: string | null;
    impressionUrl: string;
    clickUrl: string;
    /** The campaign's daily cap for this visitor, counted in the browser; null when uncapped. */
    frequency: { key: string; cap: number } | null;
};

/**
 * The placements a page asked for, keyed by the names in `config/psobpsay.php`.
 * Narrow this to your own placement names once they are settled.
 */
export type SponsoredSlots = Record<string, SponsoredAd | null | undefined>;

/** One cropped picture of a creative, sized for the space it was served to. */
export type SpacePicture = {
    src: string;
    srcset: string | null;
    width: number | null;
    height: number | null;
};

/** The word a space discloses its ads with, set by the space, never the advertiser. */
export type SpaceDisclosure =
    'sponsored' | 'promoted' | 'presented' | 'paid' | 'ad';

/** An ad served to a templated space: the card's fields plus what the template draws with. */
export type SpaceAd = SponsoredAd & {
    template: string | null;
    disclosure: SpaceDisclosure;
    /** Keyed by crop: landscape, square, portrait, vertical. */
    assets: Partial<Record<string, SpacePicture>>;
    logo: SpacePicture | null;
    video: {
        src: string;
        poster: string | null;
        durationMs: number | null;
    } | null;
    promo: { code: string; terms: string | null } | null;
    /** Delivery-minted feedback endpoint; null when unavailable. */
    feedbackUrl: string | null;
    /** Safe “why this ad” explanation — never private targeting rules. */
    why: { advertiser: string | null; reasons: string[] } | null;
    /** Host-owned content pointer for promoted_content templates. */
    contentRef: string | null;
    contentUrl: string | null;
};

/**
 * `PsobPsayClient::spaces()` by slot id. Undefined while the deferred prop is loading; an
 * empty list is a space that went unfilled.
 */
export type SponsoredSpaces = Record<string, SpaceAd[] | undefined>;
