/**
 * Optional Inertia page stub for hosts that prefer React over the Blade dashboard.
 * Publish with `--tag=psobpsay-dashboard` and point a controller at this page using
 * the same props shape as `DashboardController` (`inspect`, `report`, …).
 *
 * Styling intentionally uses host design tokens; replace class names with your system.
 */

type Finding = {
    id: string | null;
    severity: 'error' | 'warning';
    code: string;
    message: string;
    next: string;
};

type Props = {
    inspect: {
        local: {
            enabled: boolean;
            configured: boolean;
            circuitOpen: boolean;
            refusedStatus: number | null;
            consoleUrl: string | null;
        };
        remote: {
            cached: boolean;
            age_seconds: number | null;
            stale: boolean;
            diagnostics: {
                findings: Finding[];
                spaces: Array<Record<string, unknown>>;
                checklist: Array<{ key: string; ok: boolean; label: string; detail: string | null }>;
            };
        };
    };
    report: { rows: Array<Record<string, unknown>>; totals: Record<string, unknown> } | null;
    reportAvailable: boolean;
    from: string;
    to: string;
    dimension: string;
    siteFilter: string | null;
    creatives: Record<string, Array<Record<string, unknown>>>;
    consoleUrl: string | null;
    blockScope: string;
    csvUrl: string;
    blocksUrl: string;
};

export default function PsobPsayDashboard(props: Props) {
    const { inspect, report, reportAvailable, creatives, csvUrl, consoleUrl } = props;
    const findings = inspect.remote.diagnostics.findings ?? [];

    return (
        <div>
            <h1>PsobPsay</h1>
            <p>Publisher setup and delivery health. Credentials stay server-side.</p>

            <section>
                <p>Enabled: {String(inspect.local.enabled)}</p>
                <p>Configured: {String(inspect.local.configured)}</p>
                <p>Circuit open: {String(inspect.local.circuitOpen)}</p>
                <p>
                    Manifest:{' '}
                    {inspect.remote.cached
                        ? `${inspect.remote.age_seconds ?? 'unknown'}s${inspect.remote.stale ? ' (stale)' : ''}`
                        : 'unavailable / fresh'}
                </p>
                {consoleUrl ? <a href={consoleUrl}>Open publisher console</a> : null}
            </section>

            {findings.length > 0 ? (
                <section>
                    <h2>What needs attention</h2>
                    <ul>
                        {findings.map((finding) => (
                            <li key={`${finding.code}-${finding.id ?? 'site'}`}>
                                <strong>
                                    {finding.id ? `${finding.id}: ` : ''}
                                    {finding.message}
                                </strong>
                                <div>{finding.next}</div>
                            </li>
                        ))}
                    </ul>
                </section>
            ) : null}

            <section>
                <h2>Analytics</h2>
                {!reportAvailable ? (
                    <p>Report data is unavailable — not the same as zero revenue.</p>
                ) : (
                    <pre>{JSON.stringify(report?.totals ?? {}, null, 2)}</pre>
                )}
                <a href={csvUrl}>Download CSV</a>
            </section>

            <section>
                <h2>Eligible creatives</h2>
                <pre>{JSON.stringify(creatives, null, 2)}</pre>
            </section>

            <p>{props.blockScope}</p>
        </div>
    );
}
