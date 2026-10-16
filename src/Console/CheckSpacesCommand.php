<?php

namespace Khable\PsobPsayClient\Console;

use Illuminate\Console\Command;
use Khable\PsobPsayClient\PsobPsayClient;
use Khable\PsobPsayClient\SpaceDiagnostics;
use Khable\PsobPsayClient\Support\ReferencedSlots;

class CheckSpacesCommand extends Command
{
    protected $signature = 'psobpsay:check
        {--site= : The site domain in the ad console}
        {--strict-traffic : Fail when a space has no requests in the last 24 hours}';

    protected $description = 'Check configured and referenced ad spaces against the publisher manifest';

    public function handle(PsobPsayClient $psobpsay, SpaceDiagnostics $diagnostics): int
    {
        $psobpsay->forgetManifest();
        $result = $diagnostics->build(
            $this->option('site') ?: null,
            ReferencedSlots::scan(),
            (bool) $this->option('strict-traffic'),
        );

        foreach ($result['findings'] as $finding) {
            $line = ($finding['id'] ? $finding['id'].': ' : '').$finding['message'];
            if ($finding['severity'] === 'warning') {
                $this->warn($line);
            } else {
                $this->error($line);
            }
        }

        if (! $result['ok']) {
            return self::FAILURE;
        }

        $count = count($result['spaces']);
        $warnings = count(array_filter($result['findings'], fn (array $f): bool => $f['severity'] === 'warning'));

        if ($warnings > 0) {
            $this->info("Publisher connection and {$count} ad spaces are structurally healthy ({$warnings} traffic warning(s)).");
        } else {
            $this->info("Publisher connection and {$count} ad spaces are healthy.");
        }

        return self::SUCCESS;
    }
}
