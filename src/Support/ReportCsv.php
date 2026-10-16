<?php

namespace Khable\PsobPsayClient\Support;

/**
 * Fixed-schema CSV for publisher reports. Cells are escaped so a malicious
 * dimension label cannot break columns or inject spreadsheet formulas.
 */
final class ReportCsv
{
    /** @var list<string> */
    public const Columns = [
        'dimension',
        'requests',
        'fills',
        'impressions',
        'viewable_rate',
        'clicks',
        'ctr',
        'earnings_micros',
        'ecpm_micros',
    ];

    /**
     * @param  array{rows?: mixed, totals?: mixed}|null  $report
     */
    public static function encode(?array $report): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            return '';
        }

        fputcsv($handle, self::Columns);

        foreach (is_array($report['rows'] ?? null) ? $report['rows'] : [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            fputcsv($handle, self::cells($row));
        }

        if (is_array($report['totals'] ?? null)) {
            fputcsv($handle, self::cells(['dimension' => 'totals', ...$report['totals']]));
        }

        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $csv;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    private static function cells(array $row): array
    {
        return array_map(
            fn (string $column): string => self::cell($row[$column] ?? ''),
            self::Columns,
        );
    }

    private static function cell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        $text = is_scalar($value) ? (string) $value : '';

        // Neutralize formula injection when the file is opened in a spreadsheet.
        if ($text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$text;
        }

        return $text;
    }
}
