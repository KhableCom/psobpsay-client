<?php

use Illuminate\Support\Facades\File;

afterEach(function () {
    File::delete(config_path('khads.php'));
    File::deleteDirectory(resource_path('js/components/khads'));
    File::deleteDirectory(resource_path('views/vendor/khads'));
});

test('the config publishes under its own name', function () {
    $this->artisan('vendor:publish', ['--tag' => 'khads-config'])->assertSuccessful();

    expect(config_path('khads.php'))->toBeFile();
});

test('the React card publishes with its impression and frequency-cap helpers', function () {
    $this->artisan('vendor:publish', ['--tag' => 'khads-react'])->assertSuccessful();

    foreach (['sponsored-row.tsx', 'use-viewable-impression.ts', 'frequency-caps.ts', 'types.ts'] as $file) {
        expect(resource_path('js/components/khads/'.$file))->toBeFile();
    }
});

test('the Blade view publishes for restyling', function () {
    $this->artisan('vendor:publish', ['--tag' => 'khads-views'])->assertSuccessful();

    expect(resource_path('views/vendor/khads/components/sponsored.blade.php'))->toBeFile();
});
