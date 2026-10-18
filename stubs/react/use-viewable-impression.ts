import { useEffect, useRef } from 'react';
import { countImpression } from './frequency-caps';
import type { SponsoredAd } from './types';

/**
 * Counts an impression the way the ad server bills it: at least half the card on
 * screen for a full second, once per mount. A card scrolled past too fast to read is
 * not an impression and is not sent. The same moment counts toward the campaign's daily
 * frequency cap, so a card that was never seen does not use up the visitor's allowance.
 *
 * Published by psobpsay/client. Keep this file as published: it is the part of the
 * card the ad server's billing depends on, and a diff against the package stub is the
 * check that it has not drifted.
 */
export function useViewableImpression(
    url: string,
    frequency: SponsoredAd['frequency'] = null,
) {
    const ref = useRef<HTMLElement | null>(null);
    const frequencyKey = frequency?.key ?? null;
    const frequencyCap = frequency?.cap ?? 0;

    useEffect(() => {
        const element = ref.current;

        if (!element || typeof IntersectionObserver === 'undefined') {
            return;
        }

        let timer: ReturnType<typeof setTimeout> | null = null;
        let sent = false;
        let latestEntry: IntersectionObserverEntry | null = null;

        const clearViewabilityTimer = () => {
            if (timer) {
                clearTimeout(timer);
                timer = null;
            }
        };

        const isViewable = () =>
            latestEntry?.isIntersecting === true &&
            latestEntry.intersectionRatio >= 0.5 &&
            document.visibilityState === 'visible';

        const startViewabilityTimer = () => {
            if (sent || timer || !isViewable()) {
                return;
            }

            timer = setTimeout(() => {
                timer = null;

                if (!isViewable()) {
                    return;
                }

                sent = true;
                // A plain image request needs no CORS and survives navigation.
                const beacon = new Image();
                beacon.src = url;
                countImpression(
                    frequencyKey
                        ? { key: frequencyKey, cap: frequencyCap }
                        : null,
                );
                observer.disconnect();
                document.removeEventListener(
                    'visibilitychange',
                    handleVisibilityChange,
                );
            }, 1000);
        };

        const handleVisibilityChange = () => {
            if (document.visibilityState === 'visible') {
                startViewabilityTimer();
            } else {
                clearViewabilityTimer();
            }
        };

        const observer = new IntersectionObserver(
            ([entry]) => {
                latestEntry = entry;

                if (sent) {
                    return;
                }

                if (isViewable()) {
                    startViewabilityTimer();
                } else {
                    clearViewabilityTimer();
                }
            },
            { threshold: 0.5 },
        );

        observer.observe(element);
        document.addEventListener('visibilitychange', handleVisibilityChange);

        return () => {
            clearViewabilityTimer();
            observer.disconnect();
            document.removeEventListener(
                'visibilitychange',
                handleVisibilityChange,
            );
        };
    }, [url, frequencyKey, frequencyCap]);

    return ref;
}
