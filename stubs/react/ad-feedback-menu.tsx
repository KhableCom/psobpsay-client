import { useId, useState } from 'react';
import type { SpaceAd } from './types';

type FeedbackState = 'idle' | 'pending' | 'done' | 'error' | 'hidden';

/**
 * Hide / report / why-this-ad controls outside the billable click link.
 */
export function AdFeedbackMenu({ ad }: { ad: SpaceAd }) {
    const menuId = useId();
    const [open, setOpen] = useState(false);
    const [state, setState] = useState<FeedbackState>('idle');
    const [message, setMessage] = useState<string | null>(null);

    if (!ad.feedbackUrl || state === 'hidden') {
        return null;
    }

    async function submit(action: 'hide' | 'report', reason?: string) {
        if (!ad.feedbackUrl || state === 'pending') {
            return;
        }

        setState('pending');
        setMessage(null);

        try {
            const response = await fetch(ad.feedbackUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(
                    reason ? { action, reason } : { action },
                ),
            });
            const body = (await response.json().catch(() => null)) as {
                message?: string;
            } | null;

            if (!response.ok) {
                setState('error');
                setMessage(body?.message ?? 'Something went wrong. Try again.');
                return;
            }

            if (action === 'hide') {
                setState('hidden');
                return;
            }

            setState('done');
            setMessage(body?.message ?? 'Thanks — we will review this ad.');
            setOpen(false);
        } catch {
            setState('error');
            setMessage('Something went wrong. Try again.');
        }
    }

    return (
        <div className="relative mt-1 flex flex-col items-end gap-1 text-xs">
            <button
                type="button"
                className="rounded px-1.5 py-0.5 opacity-70 hover:opacity-100"
                aria-expanded={open}
                aria-controls={menuId}
                onClick={(event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    setOpen((value) => !value);
                }}
            >
                Ad options
            </button>
            {open ? (
                <div
                    id={menuId}
                    role="menu"
                    className="absolute right-0 z-10 mt-6 min-w-44 rounded-md border bg-background p-2 shadow-sm"
                >
                    {ad.why ? (
                        <div className="mb-2 border-b pb-2">
                            <p className="font-medium">Why this ad</p>
                            {ad.why.advertiser ? (
                                <p className="opacity-80">
                                    From {ad.why.advertiser}
                                </p>
                            ) : null}
                            <ul className="mt-1 list-inside list-disc opacity-80">
                                {ad.why.reasons.map((reason) => (
                                    <li key={reason}>{reason}</li>
                                ))}
                            </ul>
                        </div>
                    ) : null}
                    <button
                        type="button"
                        role="menuitem"
                        className="block w-full rounded px-2 py-1.5 text-left hover:bg-muted"
                        disabled={state === 'pending'}
                        onClick={(event) => {
                            event.preventDefault();
                            event.stopPropagation();
                            void submit('hide');
                        }}
                    >
                        Hide this ad
                    </button>
                    <button
                        type="button"
                        role="menuitem"
                        className="block w-full rounded px-2 py-1.5 text-left hover:bg-muted"
                        disabled={state === 'pending'}
                        onClick={(event) => {
                            event.preventDefault();
                            event.stopPropagation();
                            void submit('report', 'misleading');
                        }}
                    >
                        Report as misleading
                    </button>
                    <button
                        type="button"
                        role="menuitem"
                        className="block w-full rounded px-2 py-1.5 text-left hover:bg-muted"
                        disabled={state === 'pending'}
                        onClick={(event) => {
                            event.preventDefault();
                            event.stopPropagation();
                            void submit('report', 'offensive');
                        }}
                    >
                        Report as offensive
                    </button>
                </div>
            ) : null}
            {message ? (
                <p role="status" className="text-muted-foreground">
                    {message}
                </p>
            ) : null}
        </div>
    );
}
