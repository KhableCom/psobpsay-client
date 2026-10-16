import { useState } from 'react';

export type PromoCopyState = 'idle' | 'copied' | 'failed';

/**
 * Copy a reward promo code without navigating. Keeps the control outside the
 * billable click link so a copy never fires a click event.
 */
export function usePromoCopy(code: string): {
    state: PromoCopyState;
    copy: () => Promise<void>;
    label: string;
} {
    const [state, setState] = useState<PromoCopyState>('idle');

    const copy = async () => {
        try {
            if (!navigator.clipboard?.writeText) {
                throw new Error('clipboard unavailable');
            }

            await navigator.clipboard.writeText(code);
            setState('copied');
        } catch {
            setState('failed');
        }
    };

    const label =
        state === 'copied'
            ? 'Copied'
            : state === 'failed'
              ? 'Could not copy'
              : 'Copy code';

    return { state, copy, label };
}
