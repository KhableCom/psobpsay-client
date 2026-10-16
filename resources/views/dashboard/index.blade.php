@php
    /** @var array $inspect */
    $local = $inspect['local'];
    $remote = $inspect['remote'];
    $diagnostics = $remote['diagnostics'];
    $site = $diagnostics['site'] ?? null;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PsobPsay — publisher setup</title>
    <style>
        :root {
            --ps-bg: #f6f4ef;
            --ps-ink: #1c1917;
            --ps-muted: #57534e;
            --ps-line: #d6d3d1;
            --ps-ok: #166534;
            --ps-warn: #92400e;
            --ps-bad: #991b1b;
            --ps-card: #fffdf8;
            --ps-focus: #0f766e;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Iowan Old Style", "Palatino Linotype", Palatino, Georgia, serif;
            color: var(--ps-ink);
            background:
                radial-gradient(1200px 500px at 10% -10%, #e7efe9 0%, transparent 55%),
                linear-gradient(180deg, #f8f5ee, var(--ps-bg));
            line-height: 1.45;
        }
        main { max-width: 1100px; margin: 0 auto; padding: 1.5rem 1rem 3rem; }
        h1, h2 { font-weight: 650; letter-spacing: -0.02em; }
        h1 { font-size: clamp(1.6rem, 3vw, 2.1rem); margin: 0 0 0.35rem; }
        h2 { font-size: 1.15rem; margin: 2rem 0 0.75rem; }
        p.lede { color: var(--ps-muted); margin: 0 0 1.25rem; max-width: 42rem; }
        .strip, .panel { background: var(--ps-card); border: 1px solid var(--ps-line); border-radius: 0.75rem; padding: 1rem; }
        .strip { display: grid; gap: 0.75rem; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); }
        .stat span { display: block; color: var(--ps-muted); font-size: 0.8rem; }
        .ok { color: var(--ps-ok); } .warn { color: var(--ps-warn); } .bad { color: var(--ps-bad); }
        table { width: 100%; border-collapse: collapse; font-size: 0.92rem; }
        th, td { text-align: left; padding: 0.55rem 0.4rem; border-bottom: 1px solid var(--ps-line); vertical-align: top; }
        th { color: var(--ps-muted); font-weight: 600; font-size: 0.8rem; }
        .findings { list-style: none; padding: 0; margin: 0 0 1rem; }
        .findings li { padding: 0.55rem 0.7rem; border-left: 3px solid var(--ps-line); margin-bottom: 0.4rem; background: #fff; }
        .findings li.error { border-color: var(--ps-bad); }
        .findings li.warning { border-color: var(--ps-warn); }
        .findings small { display: block; color: var(--ps-muted); margin-top: 0.2rem; }
        form.inline { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: end; margin-bottom: 0.75rem; }
        label { display: grid; gap: 0.2rem; font-size: 0.85rem; color: var(--ps-muted); }
        input, select, button, .button {
            font: inherit; padding: 0.45rem 0.65rem; border-radius: 0.45rem; border: 1px solid var(--ps-line); background: white;
        }
        button, .button {
            background: var(--ps-ink); color: white; border-color: var(--ps-ink); cursor: pointer; text-decoration: none; display: inline-block;
        }
        button:focus-visible, .button:focus-visible, input:focus-visible, select:focus-visible { outline: 2px solid var(--ps-focus); outline-offset: 2px; }
        pre {
            white-space: pre-wrap; word-break: break-word; background: #1c1917; color: #f5f5f4;
            padding: 0.75rem; border-radius: 0.5rem; font-size: 0.8rem; font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        }
        .muted { color: var(--ps-muted); }
        .grid-2 { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
        .creative { border: 1px solid var(--ps-line); border-radius: 0.5rem; padding: 0.65rem; background: white; }
        .creative img { max-width: 100%; height: auto; border-radius: 0.35rem; }
        details { margin-top: 0.5rem; }
        @media (max-width: 640px) {
            table, thead, tbody, th, td, tr { display: block; }
            thead { position: absolute; left: -9999px; }
            tr { border-bottom: 1px solid var(--ps-line); margin-bottom: 0.75rem; }
            td { border: 0; padding: 0.2rem 0; }
            td::before { content: attr(data-label); display: block; color: var(--ps-muted); font-size: 0.75rem; }
        }
    </style>
</head>
<body>
<main>
    <h1>PsobPsay</h1>
    <p class="lede">Publisher setup and delivery health for this site. Credentials stay on the server; this page never shows API keys or billable tracking links.</p>

    <section class="strip" aria-label="Health">
        <div class="stat"><span>Ads</span><strong class="{{ $local['enabled'] ? 'ok' : 'bad' }}">{{ $local['enabled'] ? 'Enabled' : 'Disabled' }}</strong></div>
        <div class="stat"><span>Configured</span><strong class="{{ $local['configured'] ? 'ok' : 'bad' }}">{{ $local['configured'] ? 'Yes' : 'No' }}</strong></div>
        <div class="stat"><span>Connection</span><strong class="{{ $local['circuitOpen'] ? 'bad' : 'ok' }}">{{ $local['circuitOpen'] ? 'Circuit open' : 'Ready' }}</strong></div>
        <div class="stat"><span>Key / request</span><strong class="{{ $local['refusedStatus'] ? 'bad' : 'ok' }}">{{ $local['refusedStatus'] ? 'HTTP '.$local['refusedStatus'] : 'Accepted' }}</strong></div>
        <div class="stat"><span>Manifest cache</span><strong class="{{ ($remote['stale'] ?? false) ? 'warn' : 'ok' }}">
            @if(!($remote['cached'] ?? false)) Fresh fetch / unavailable
            @elseif($remote['age_seconds'] === null) Cached
            @else {{ $remote['age_seconds'] }}s old{{ ($remote['stale'] ?? false) ? ' (stale)' : '' }}
            @endif
        </strong></div>
        <div class="stat"><span>Site</span><strong>{{ $site['domain'] ?? '—' }}</strong></div>
    </section>

    @if(($diagnostics['findings'] ?? []) !== [])
        <h2>What needs attention</h2>
        <ul class="findings">
            @foreach($diagnostics['findings'] as $finding)
                <li class="{{ $finding['severity'] }}">
                    <strong>{{ $finding['id'] ? $finding['id'].': ' : '' }}{{ $finding['message'] }}</strong>
                    <small>{{ $finding['next'] }}</small>
                </li>
            @endforeach
        </ul>
    @endif

    <h2>Setup checklist</h2>
    <div class="panel">
        <ul class="findings">
            @forelse(($diagnostics['checklist'] ?? []) as $item)
                <li class="{{ $item['ok'] ? 'ok' : 'error' }}">
                    <strong>{{ $item['ok'] ? 'Done' : 'Next' }} — {{ $item['label'] }}</strong>
                    @if($item['detail'])<small>{{ $item['detail'] }}</small>@endif
                </li>
            @empty
                <li class="warning"><strong>Checklist unavailable</strong><small>Fix connectivity first.</small></li>
            @endforelse
        </ul>
        @if($consoleUrl)
            <p><a class="button" href="{{ $consoleUrl }}">Open publisher console</a></p>
        @endif
    </div>

    <h2>Spaces</h2>
    <div class="panel" style="overflow-x:auto">
        <table>
            <thead>
            <tr>
                <th>Placement</th>
                <th>Template / format</th>
                <th>Active</th>
                <th>Reservation</th>
                <th>Requests 24h</th>
                <th>Fill rate</th>
                <th>Last seen</th>
                <th>Code</th>
            </tr>
            </thead>
            <tbody>
            @forelse(($diagnostics['spaces'] ?? []) as $space)
                <tr>
                    <td data-label="Placement"><strong>{{ $space['id'] }}</strong><div class="muted">{{ $space['name'] ?? $space['slot_id'] }}</div></td>
                    <td data-label="Template">{{ $space['template'] ?? '—' }} / {{ $space['format'] ?? '—' }}</td>
                    <td data-label="Active" class="{{ $space['is_active'] ? 'ok' : 'bad' }}">{{ $space['is_active'] === null ? '—' : ($space['is_active'] ? 'Yes' : 'No') }}</td>
                    <td data-label="Reservation">{{ $space['reservation']['campaign_brand'] ?? 'Auction' }}</td>
                    <td data-label="Requests">{{ $space['requests_24h'] ?? '—' }}</td>
                    <td data-label="Fill">{{ $space['fill_rate_24h'] === null ? '—' : $space['fill_rate_24h'].'%' }}</td>
                    <td data-label="Last seen">{{ $space['last_request_at'] ?? '—' }}</td>
                    <td data-label="Code">
                        @if(is_array($space['snippets'] ?? null))
                            <details>
                                <summary>Get code</summary>
                                @foreach($space['snippets'] as $kind => $snippet)
                                    <p class="muted">{{ $kind }}</p>
                                    <pre>{{ $snippet }}</pre>
                                @endforeach
                            </details>
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted">No configured or referenced spaces yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <h2>Analytics</h2>
    <div class="panel">
        <form class="inline" method="get">
            <label>From<input type="date" name="from" value="{{ $from }}"></label>
            <label>To<input type="date" name="to" value="{{ $to }}"></label>
            <label>Dimension
                <select name="dimension">
                    @foreach(['day','zone','site'] as $option)
                        <option value="{{ $option }}" @selected($dimension === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </label>
            <label>Site<input type="text" name="site" value="{{ $siteFilter ?? '' }}" placeholder="optional"></label>
            <button type="submit">Refresh</button>
            <a class="button" href="{{ $csvUrl }}">Download CSV</a>
        </form>

        @if(! $reportAvailable)
            <p class="warn">Report data is unavailable right now. This is not the same as zero revenue — try again when the connection is healthy.</p>
        @else
            <table>
                <thead>
                <tr>
                    <th>Dimension</th><th>Requests</th><th>Fills</th><th>Impressions</th><th>Clicks</th><th>Earnings (micros)</th><th>eCPM (micros)</th>
                </tr>
                </thead>
                <tbody>
                @forelse(($report['rows'] ?? []) as $row)
                    <tr>
                        <td data-label="Dimension">{{ $row['dimension'] ?? $row['day'] ?? $row['zone'] ?? $row['site'] ?? '—' }}</td>
                        <td data-label="Requests">{{ $row['requests'] ?? '—' }}</td>
                        <td data-label="Fills">{{ $row['fills'] ?? '—' }}</td>
                        <td data-label="Impressions">{{ $row['impressions'] ?? '—' }}</td>
                        <td data-label="Clicks">{{ $row['clicks'] ?? '—' }}</td>
                        <td data-label="Earnings">{{ $row['earnings_micros'] ?? '—' }}</td>
                        <td data-label="eCPM">{{ $row['ecpm_micros'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">No rows in this range. Totals below still reflect the period.</td></tr>
                @endforelse
                @if(is_array($report['totals'] ?? null))
                    <tr>
                        <td data-label="Dimension"><strong>Totals</strong></td>
                        <td data-label="Requests">{{ $report['totals']['requests'] ?? '—' }}</td>
                        <td data-label="Fills">{{ $report['totals']['fills'] ?? '—' }}</td>
                        <td data-label="Impressions">{{ $report['totals']['impressions'] ?? '—' }}</td>
                        <td data-label="Clicks">{{ $report['totals']['clicks'] ?? '—' }}</td>
                        <td data-label="Earnings">{{ $report['totals']['earnings_micros'] ?? '—' }}</td>
                        <td data-label="eCPM">{{ $report['totals']['ecpm_micros'] ?? '—' }}</td>
                    </tr>
                @endif
                </tbody>
            </table>
        @endif
    </div>

    <h2>Eligible creatives</h2>
    <p class="muted">Read-only previews. These are not live ads and do not include impression or click tracking URLs.</p>
    <div class="grid-2">
        @forelse($creatives as $placement => $ads)
            <div class="panel">
                <h3 style="margin-top:0">{{ $placement }}</h3>
                @forelse($ads as $ad)
                    <article class="creative">
                        @if(!empty($ad['image']))
                            <img src="{{ $ad['image'] }}" alt="" width="320" height="200" loading="lazy">
                        @endif
                        <strong>{{ $ad['brand'] ?? 'Advertiser' }}</strong>
                        <div>{{ $ad['headline'] ?? '' }}</div>
                        <div class="muted">{{ $ad['landing_host'] ?? '' }} · {{ $ad['category'] ?? '' }}</div>
                    </article>
                @empty
                    <p class="muted">No eligible creatives right now.</p>
                @endforelse
            </div>
        @empty
            <p class="muted">No spaces to preview.</p>
        @endforelse
    </div>

    <h2>Brand controls</h2>
    <div class="panel">
        <p>{{ $blockScope }}</p>
        <form class="inline" method="post" action="{{ $blocksUrl }}">
            @csrf
            <label>Type
                <select name="type">
                    <option value="category">Category</option>
                    <option value="domain">Advertiser domain</option>
                </select>
            </label>
            <label>Value<input name="value" required maxlength="100" placeholder="example.com"></label>
            <button type="submit">Add workspace block</button>
        </form>
        @if ($errors->any())
            <p class="bad" role="alert">{{ $errors->first() }}</p>
        @endif
        @if (session('status'))
            <p class="ok" role="status">{{ session('status') }}</p>
        @endif
    </div>

    <details>
        <summary>Integration details</summary>
        <p class="muted">Developer reference for support. Reason codes and cache age only — no secrets.</p>
        <pre>{{ json_encode([
            'structural_ok' => $diagnostics['structural_ok'] ?? null,
            'findings' => $diagnostics['findings'] ?? [],
            'local' => collect($local)->except([])->all(),
            'remote_meta' => collect($remote)->except('diagnostics')->all(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
    </details>
</main>
</body>
</html>
