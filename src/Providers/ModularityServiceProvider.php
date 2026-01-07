<?php

namespace LaraModularity\Providers;

use Illuminate\Support\ServiceProvider;
use LaraModularity\Bootstrap\ModuleConfigBootstrap;
use LaraModularity\Commands\ModuleMigrateCommand;
use LaraModularity\Commands\ModuleSeedCommand;

class ModularityServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Override the LoadConfiguration bootstrap with our ModuleConfigBootstrap
        $this->app->singleton(
            \Illuminate\Foundation\Bootstrap\LoadConfiguration::class,
            ModuleConfigBootstrap::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ModuleMigrateCommand::class,
                ModuleSeedCommand::class,
            ]);
        }
    }
}
