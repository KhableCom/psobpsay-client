<?php

namespace Khable\KhadsClient;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Khable\KhadsClient\Console\CheckSpacesCommand;
use Khable\KhadsClient\Console\SyncSpacesCommand;

class KhadsClientServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/khads.php', 'khads');

        // Stateless: every setting lives in config and the circuit in the cache.
        $this->app->singleton(KhadsClient::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'khads');
        Blade::componentNamespace('Khable\\KhadsClient\\View\\Components', 'khads');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([SyncSpacesCommand::class, CheckSpacesCommand::class]);

        $this->publishes([__DIR__.'/../config/khads.php' => config_path('khads.php')], 'khads-config');
        $this->publishes([__DIR__.'/../stubs/react' => resource_path('js/components/khads')], 'khads-react');
        $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/khads')], 'khads-views');
    }
}
