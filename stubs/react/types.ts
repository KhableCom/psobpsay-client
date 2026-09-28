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
 * The placements a page asked for, keyed by the names in `config/khads.php`.
 * Narrow this to your own placement names once they are settled.
 */
export type SponsoredSlots = Record<string, SponsoredAd | null | undefined>;
