<?php

namespace Khable\PsobPsayClient;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Khable\PsobPsayClient\Console\CheckSpacesCommand;
use Khable\PsobPsayClient\Console\SyncSpacesCommand;

class PsobPsayClientServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/psobpsay.php', 'psobpsay');

        // Stateless: every setting lives in config and the circuit in the cache.
        $this->app->singleton(PsobPsayClient::class);
        $this->app->singleton(SpaceDiagnostics::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'psobpsay');
        Blade::componentNamespace('Khable\\PsobPsayClient\\View\\Components', 'psobpsay');

        if ($this->dashboardEnabled()) {
            $this->loadRoutesFrom(__DIR__.'/../routes/dashboard.php');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([SyncSpacesCommand::class, CheckSpacesCommand::class]);
        }

        $this->publishes([__DIR__.'/../config/psobpsay.php' => config_path('psobpsay.php')], 'psobpsay-config');
        $this->publishes([__DIR__.'/../stubs/react' => resource_path('js/components/psobpsay')], 'psobpsay-react');
        $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/psobpsay')], 'psobpsay-views');
        $this->publishes([
            __DIR__.'/../stubs/dashboard/psobpsay-dashboard.tsx' => resource_path('js/pages/psobpsay-dashboard.tsx'),
            __DIR__.'/../resources/views/dashboard' => resource_path('views/vendor/psobpsay/dashboard'),
        ], 'psobpsay-dashboard');
    }

    private function dashboardEnabled(): bool
    {
        return (bool) config('psobpsay.dashboard.enabled')
            && (array) config('psobpsay.dashboard.middleware', []) !== [];
    }
}
