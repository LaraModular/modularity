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

        $this->registerModuleConfigs();
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

        $this->loadModuleMigrations();
        $this->loadModuleViews();
        $this->loadModuleRoutes();
        $this->publishModuleAssets();
    }

    /**
     * Register configs from all modules.
     */
    private function registerModuleConfigs(): void
    {
        $modules = ModuleRegistry::getAllModules();

        foreach ($modules as $moduleName => $module) {
            $configPath = $module['path'].'/Configs';

            if (! is_dir($configPath)) {
                continue;
            }

            foreach (glob($configPath.'/*.php') as $configFile) {
                $configName = basename($configFile, '.php');
                $moduleConfig = require $configFile;
                $existingConfig = config($configName, []);
                config([$configName => array_replace_recursive($existingConfig, $moduleConfig)]);
            }
        }
    }

    /**
     * Load migrations from all modules.
     */
    private function loadModuleMigrations(): void
    {
        $modules = ModuleRegistry::getAllModules();

        foreach ($modules as $moduleName => $module) {
            $migrationsPath = $module['path'].'/Database/Migrations';

            if (is_dir($migrationsPath)) {
                $this->loadMigrationsFrom($migrationsPath);
            }
        }
    }

    /**
     * Load views from all modules.
     */
    private function loadModuleViews(): void
    {
        $modules = ModuleRegistry::getAllModules();
        $viewFinder = $this->app['view']->getFinder();

        foreach ($modules as $module) {
            $viewsPath = $module['path'].'/Views';

            if (is_dir($viewsPath)) {
                $viewFinder->addLocation($viewsPath);
            }
        }
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
                Route::middleware('api')
                    ->group($apiRoutesPath);
            }

            $webRoutesPath = $modulePath.'/Routes/web.php';
            if (file_exists($webRoutesPath)) {
                Route::middleware('web')
                    ->group($webRoutesPath);
            }

            $consoleRoutesPath = $modulePath.'/Routes/console.php';
            if (file_exists($consoleRoutesPath) && $this->app->runningInConsole()) {
                require $consoleRoutesPath;
            }
        }
    }

    /**
     * Publish assets from all modules.
     */
    private function publishModuleAssets(): void
    {
        $modules = ModuleRegistry::getAllModules();

        foreach ($modules as $moduleName => $module) {
            $publicPath = $module['path'].'/Public';

            if (! is_dir($publicPath)) {
                continue;
            }

            // Publish each subdirectory in Public as a separate asset group
            foreach (glob($publicPath.'/*', GLOB_ONLYDIR) as $assetDir) {
                $assetName = basename($assetDir);
                $this->publishes([
                    $assetDir => public_path("vendor/{$assetName}"),
                ], "{$assetName}-assets");
            }
        }
    }
}
