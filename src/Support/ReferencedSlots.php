<?php

namespace PsobPsay\Client\Support;

use Illuminate\Support\Facades\File;

/**
 * Literal React/Blade slot ids referenced in the host application. Used by
 * deploy-time checks so a hard-coded placement missing from the manifest fails
 * the pipeline even when it is absent from config.
 */
final class ReferencedSlots
{
    /**
     * @param  list<string>|null  $directories
     * @return list<string>
     */
    public static function scan(?array $directories = null): array
    {
        $slots = [];
        $directories ??= [resource_path('js'), resource_path('views')];

        foreach ($directories as $directory) {
            if (! File::isDirectory($directory)) {
                continue;
            }

            foreach (File::allFiles($directory) as $file) {
                if (! in_array($file->getExtension(), ['tsx', 'jsx', 'php'], true)) {
                    continue;
                }

                preg_match_all(
                    '/<(?:PsobPsaySlot|x-psobpsay::slot)\b[^>]*\bid\s*=\s*[\x22\x27]([a-zA-Z0-9_.-]+)[\x22\x27]/s',
                    $file->getContents(),
                    $matches,
                );
                $slots = [...$slots, ...$matches[1]];
            }
        }

        return array_values(array_unique($slots));
    }
}
