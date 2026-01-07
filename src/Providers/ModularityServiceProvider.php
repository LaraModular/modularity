<?php

namespace LaraModularity\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use LaraModularity\Bootstrap\ModuleConfigBootstrap;
use LaraModularity\Commands\ModuleMigrateCommand;
use LaraModularity\Commands\ModuleSeedCommand;
use LaraModularity\ModuleRegistry;

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

        $this->loadModuleRoutes();
    }

    /**
     * Load routes from all registered modules.
     */
    private function loadModuleRoutes(): void
    {
        $modules = ModuleRegistry::getAllModules();

        foreach ($modules as $moduleName => $module) {
            $modulePath = $module['path'];

            $apiRoutesPath = $modulePath.'/Routes/api.php';
            if (file_exists($apiRoutesPath)) {
                Route::prefix('api')
                    ->middleware('api')
                    ->name("{$moduleName}.")
                    ->group($apiRoutesPath);
            }

            $webRoutesPath = $modulePath.'/Routes/web.php';
            if (file_exists($webRoutesPath)) {
                Route::middleware('web')
                    ->name("{$moduleName}.")
                    ->group($webRoutesPath);
            }

            $consoleRoutesPath = $modulePath.'/Routes/console.php';
            if (file_exists($consoleRoutesPath) && $this->app->runningInConsole()) {
                require $consoleRoutesPath;
            }
        }
    }
}
