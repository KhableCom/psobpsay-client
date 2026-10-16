<?php

use Illuminate\Support\Facades\File;

afterEach(function () {
    File::delete(config_path('psobpsay.php'));
    File::deleteDirectory(resource_path('js/components/psobpsay'));
    File::deleteDirectory(resource_path('views/vendor/psobpsay'));
});

test('the config publishes under its own name', function () {
    $this->artisan('vendor:publish', ['--tag' => 'psobpsay-config'])->assertSuccessful();

    expect(config_path('psobpsay.php'))->toBeFile();
});

test('the React card publishes with its impression and frequency-cap helpers', function () {
    $this->artisan('vendor:publish', ['--tag' => 'psobpsay-react'])->assertSuccessful();

    foreach (['psobpsay-slot.tsx', 'sponsored-row.tsx', 'use-viewable-impression.ts', 'frequency-caps.ts', 'types.ts'] as $file) {
        expect(resource_path('js/components/psobpsay/'.$file))->toBeFile();
    }
});

test('the Blade view publishes for restyling', function () {
    $this->artisan('vendor:publish', ['--tag' => 'psobpsay-views'])->assertSuccessful();

    expect(resource_path('views/vendor/psobpsay/components/sponsored.blade.php'))->toBeFile();
});
