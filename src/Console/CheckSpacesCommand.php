<?php

namespace Khable\KhadsClient\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Khable\KhadsClient\KhadsClient;

class CheckSpacesCommand extends Command
{
    protected $signature = 'khads:check {--site= : The site domain in the ad console}';

    protected $description = 'Check configured and referenced ad spaces against the publisher manifest';

    public function handle(KhadsClient $khads): int
    {
        $khads->forgetManifest();
        $manifest = $khads->manifest();

        if ($manifest === []) {
            $this->error('Cannot read the publisher manifest. Check ADS_ENABLED, ADS_BASE_URL, ADS_API_KEY and connectivity.');

            return self::FAILURE;
        }

        $domain = strtolower((string) ($this->option('site') ?: config('khads.site') ?: parse_url((string) config('app.url'), PHP_URL_HOST)));
        $domain = preg_replace('/\Awww\./', '', rtrim($domain, '.'));
        $site = collect($manifest['sites'])->first(fn (array $site): bool => ($site['domain'] ?? null) === $domain);

        if (! is_array($site)) {
            $this->error('The key has no access to site '.$domain.'.');

            return self::FAILURE;
        }

        $problems = [];
        if (($site['can_serve'] ?? false) !== true) {
            $problems[] = 'Site cannot serve: '.($site['reason'] ?? 'site_inactive');
        }

        $zones = collect($site['zones'] ?? []);
        $configured = (array) config('khads.zones', []);
        $spaces = (array) config('khads.spaces', []);
        $ids = array_unique([...array_keys($configured), ...array_keys($spaces), ...$this->referencedSlots()]);

        foreach ($ids as $id) {
            $key = $configured[$id] ?? null;
            $zone = filled($key) ? $zones->firstWhere('key', $key) : $zones->firstWhere('slot_id', $id);

            if (! is_array($zone)) {
                $problems[] = $id.': zone missing or key unknown';

                continue;
            }
            if (($zone['is_active'] ?? false) !== true) {
                $problems[] = $id.': zone inactive';
            }
            $template = $zone['template'] ?? null;
            if ($template !== null && ! in_array($template, KhadsClient::SupportedTemplates, true)) {
                $problems[] = $id.': template has no bundled renderer ('.$template.')';
            } elseif ($template === null && ! in_array($zone['format'] ?? null, ['native', 'in_feed', 'text'], true)) {
                $problems[] = $id.': unsupported format';
            }
            if (isset($spaces[$id]['template']) && $template !== $spaces[$id]['template']) {
                $problems[] = $id.': template differs from khads.spaces';
            }
            if (($zone['requests_24h'] ?? 0) < 1) {
                $problems[] = $id.': no requests recorded in the last 24 hours';
            }
        }

        foreach ($problems as $problem) {
            $this->error($problem);
        }

        if ($problems !== []) {
            return self::FAILURE;
        }

        $this->info('Publisher connection and '.count($ids).' ad spaces are healthy.');

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function referencedSlots(): array
    {
        $slots = [];
        foreach ([resource_path('js'), resource_path('views')] as $directory) {
            if (! File::isDirectory($directory)) {
                continue;
            }
            foreach (File::allFiles($directory) as $file) {
                if (! in_array($file->getExtension(), ['tsx', 'jsx', 'php'], true)) {
                    continue;
                }
                preg_match_all('/<(?:KhadsSlot|x-khads::slot)\b[^>]*\bid\s*=\s*[\x22\x27]([a-zA-Z0-9_.-]+)[\x22\x27]/s', $file->getContents(), $matches);
                $slots = [...$slots, ...$matches[1]];
            }
        }

        return array_values(array_unique($slots));
    }
}
