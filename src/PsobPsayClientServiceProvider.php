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
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'psobpsay');
        Blade::componentNamespace('Khable\\PsobPsayClient\\View\\Components', 'psobpsay');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([SyncSpacesCommand::class, CheckSpacesCommand::class]);

        $this->publishes([__DIR__.'/../config/psobpsay.php' => config_path('psobpsay.php')], 'psobpsay-config');
        $this->publishes([__DIR__.'/../stubs/react' => resource_path('js/components/psobpsay')], 'psobpsay-react');
        $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/psobpsay')], 'psobpsay-views');
    }
}
