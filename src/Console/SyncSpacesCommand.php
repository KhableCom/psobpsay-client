<?php

namespace Khable\KhadsClient\Console;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Khable\KhadsClient\KhadsClient;

/**
 * Creates the spaces in `khads.spaces` that the ad console does not have yet. Safe to run
 * on every deploy: a space that exists is never changed, only reported when its template
 * or ad count there differs from this config, since the console is where it was changed.
 */
class SyncSpacesCommand extends Command
{
    protected $signature = 'khads:sync {--site= : The site\'s domain in the ad console}';

    protected $description = 'Create the ad spaces declared in config/khads.php on the ad server';

    public function handle(KhadsClient $khads): int
    {
        /** @var array<string, array<string, mixed>> $spaces */
        $spaces = (array) config('khads.spaces', []);
        $site = $this->site();

        if ($spaces === []) {
            $this->components->info('No spaces are declared in khads.spaces.');

            return self::SUCCESS;
        }

        if ($site === '' || blank(config('khads.base_url')) || blank(config('khads.api_key'))) {
            $this->components->error('Set ADS_BASE_URL, ADS_API_KEY and the site (ADS_SITE or app.url) first.');

            return self::FAILURE;
        }

        try {
            $answer = $khads->syncSpaces($site, $spaces);
        } catch (RequestException $exception) {
            $this->components->error(sprintf('The ad server refused the sync (%d): %s', $exception->response->status(), $exception->response->body()));

            return self::FAILURE;
        } catch (ConnectionException $exception) {
            $this->components->error('The ad server could not be reached: '.$exception->getMessage());

            return self::FAILURE;
        }

        foreach ((array) ($answer['created'] ?? []) as $slotId) {
            $this->components->twoColumnDetail((string) $slotId, '<fg=green>created</>');
        }

        $zones = collect((array) ($answer['site']['zones'] ?? []))->keyBy('slot_id');

        foreach ((array) ($answer['existing'] ?? []) as $slotId) {
            $zone = (array) $zones->get($slotId, []);
            $declared = $spaces[$slotId] ?? [];
            $differs = ($zone['template'] ?? null) !== ($declared['template'] ?? null)
                || (isset($declared['max_ads']) && ($zone['max_ads'] ?? null) !== (int) $declared['max_ads']);

            $this->components->twoColumnDetail(
                (string) $slotId,
                $differs ? sprintf('<fg=yellow>exists as %s × %s; left as the console has it</>', $zone['template'] ?? 'no template', $zone['max_ads'] ?? '?') : 'exists',
            );
        }

        return self::SUCCESS;
    }

    private function site(): string
    {
        $site = $this->option('site') ?: config('khads.site') ?: parse_url((string) config('app.url'), PHP_URL_HOST);

        return strtolower(preg_replace('/\Awww\./', '', trim((string) $site)) ?? '');
    }
}
