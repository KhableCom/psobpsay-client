<?php

use Illuminate\Support\Facades\Route;
use Khable\PsobPsayClient\Http\Controllers\DashboardController;

/*
|--------------------------------------------------------------------------
| Optional publisher dashboard
|--------------------------------------------------------------------------
|
| Registered only when psobpsay.dashboard.enabled is true. The host must
| supply authentication middleware in config; guests are denied before any
| upstream request is made.
|
*/

$middleware = array_values(array_filter((array) config('psobpsay.dashboard.middleware', [])));
$prefix = trim((string) config('psobpsay.dashboard.prefix', 'admin/psobpsay'), '/');

Route::middleware($middleware)
    ->prefix($prefix)
    ->name('psobpsay.dashboard.')
    ->group(function (): void {
        Route::get('/', DashboardController::class)->name('show');
        Route::get('/report.csv', [DashboardController::class, 'csv'])->name('csv');
        Route::post('/blocks', [DashboardController::class, 'block'])->name('blocks');
    });
