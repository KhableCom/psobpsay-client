import { http } from '@inertiajs/react';
import type { SponsoredAd } from './types';

/**
 * Frequency caps kept the way the psobpsay tag keeps them: in this site's own storage, as
 * today's count per campaign, and sent back only as the list of campaigns already
 * capped out. Nothing that identifies the visitor leaves the browser, and a visitor who
 * sends Global Privacy Control or Do Not Track is not counted at all.
 *
 * Published by khable/psobpsay-client. Keep this file as published: the storage format is
 * shared with the psobpsay tag, so a site running both counts one cap, not two.
 */
const STORE = 'kh_ad_freq';
const SEEN_HEADER = 'X-PsobPsay-Seen';

type Caps = Record<string, { d: string; n: number; c: number }>;

function isRestricted(): boolean {
    return (
        navigator.doNotTrack === '1' ||
        (navigator as Navigator & { globalPrivacyControl?: boolean })
            .globalPrivacyControl === true
    );
}

function today(): string {
    return new Date().toISOString().slice(0, 10);
}

function readCaps(): Caps {
    if (isRestricted()) {
        return {};
    }

    try {
        const raw: Caps = JSON.parse(localStorage.getItem(STORE) || '{}');
        const live: Caps = {};

        for (const key in raw) {
            // Yesterday's counts say nothing about today, and left in place they would
            // grow the record without bound.
            if (raw[key]?.d === today()) {
                live[key] = raw[key];
            }
        }

        return live;
    } catch {
        return {};
    }
}

/** Counts one viewable impression toward the campaign's cap for today. */
export function countImpression(frequency: SponsoredAd['frequency']): void {
    if (!frequency || isRestricted()) {
        return;
    }

    const caps = readCaps();
    const entry = caps[frequency.key] ?? { d: today(), n: 0, c: frequency.cap };
    caps[frequency.key] = { ...entry, n: entry.n + 1, c: frequency.cap };

    try {
        localStorage.setItem(STORE, JSON.stringify(caps));
    } catch {
        // Storage denied or full. The cap stops applying, which serves an extra
        // impression rather than losing the ad.
    }
}

/** Campaigns this visitor has seen enough of today, newest last, at most 50. */
export function cappedCampaigns(): string[] {
    return Object.entries(readCaps())
        .filter(([, entry]) => entry.c > 0 && entry.n >= entry.c)
        .map(([key]) => key)
        .slice(-50);
}

/**
 * Call once where the Inertia app is created. Every Inertia request, including the
 * deferred one that loads the ads, then names the campaigns this visitor has capped out
 * on, and `PsobPsayClient::slotsFor()` passes them to psobpsay. Returns a function that stops it.
 */
export function forwardCappedCampaigns(): () => void {
    return http.onRequest((config) => {
        const capped = cappedCampaigns();

        if (capped.length > 0) {
            config.headers = {
                ...config.headers,
                [SEEN_HEADER]: capped.join(','),
            };
        }

        return config;
    });
}
