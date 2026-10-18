<?php

namespace PsobPsay\Client\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use PsobPsay\Client\PsobPsayClient;
use PsobPsay\Client\Support\ReferencedSlots;
use PsobPsay\Client\Support\ReportCsv;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Host-mounted publisher dashboard. Routes are registered only when
 * `psobpsay.dashboard.enabled` is true; the host supplies authentication
 * middleware so guests never reach these actions or the upstream API.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, PsobPsayClient $psobpsay)
    {
        $from = $this->date($request->query('from'), now()->subDays(13)->toDateString());
        $to = $this->date($request->query('to'), now()->toDateString());
        $dimension = in_array($request->query('dimension'), ['day', 'zone', 'site'], true)
            ? (string) $request->query('dimension')
            : 'day';
        $site = filled($request->query('site')) ? (string) $request->query('site') : null;

        $inspect = $psobpsay->inspect($site, ReferencedSlots::scan());
        $report = $psobpsay->report($from, $to, $dimension, $site);
        $creatives = [];

        foreach ($inspect['remote']['diagnostics']['spaces'] ?? [] as $space) {
            $key = $space['zone_key'] ?? null;
            if (is_string($key) && $key !== '') {
                $creatives[$space['id']] = $psobpsay->creatives($key) ?? [];
            }
        }

        $payload = [
            'inspect' => $inspect,
            'report' => $report,
            'reportAvailable' => $report !== null,
            'from' => $from,
            'to' => $to,
            'dimension' => $dimension,
            'siteFilter' => $site,
            'creatives' => $creatives,
            'consoleUrl' => $inspect['local']['consoleUrl'],
            'blockScope' => 'Workspace API keys may change workspace brand blocks. Site-scoped keys cannot.',
            'csvUrl' => route('psobpsay.dashboard.csv', array_filter([
                'from' => $from,
                'to' => $to,
                'dimension' => $dimension,
                'site' => $site,
            ])),
            'blocksUrl' => route('psobpsay.dashboard.blocks'),
        ];

        if ($request->wantsJson()) {
            return response()->json($this->withoutSecrets($payload));
        }

        return view('psobpsay::dashboard.index', $this->withoutSecrets($payload));
    }

    public function csv(Request $request, PsobPsayClient $psobpsay): StreamedResponse
    {
        $from = $this->date($request->query('from'), now()->subDays(13)->toDateString());
        $to = $this->date($request->query('to'), now()->toDateString());
        $dimension = in_array($request->query('dimension'), ['day', 'zone', 'site'], true)
            ? (string) $request->query('dimension')
            : 'day';
        $site = filled($request->query('site')) ? (string) $request->query('site') : null;
        $report = $psobpsay->report($from, $to, $dimension, $site);
        $csv = ReportCsv::encode($report);

        return response()->streamDownload(function () use ($csv): void {
            echo $csv;
        }, 'psobpsay-report-'.$from.'_'.$to.'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function block(Request $request, PsobPsayClient $psobpsay)
    {
        $validated = $request->validate([
            'type' => ['required', 'in:category,domain'],
            'value' => ['required', 'string', 'max:100'],
        ]);

        $result = $psobpsay->block($validated['type'], $validated['value']);

        if ($result === null) {
            return back()->withErrors([
                'value' => 'Could not update brand blocks. Workspace keys are required; site keys cannot change sibling policy.',
            ]);
        }

        return back()->with('status', 'Brand block saved for the workspace.');
    }

    private function date(mixed $value, string $fallback): string
    {
        if (! is_string($value) || $value === '') {
            return $fallback;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return $fallback;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withoutSecrets(array $payload): array
    {
        $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
        $key = (string) config('psobpsay.api_key');

        if ($key !== '' && str_contains($encoded, $key)) {
            $encoded = str_replace($key, '[redacted]', $encoded);
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
