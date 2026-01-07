<?php

namespace LaraModularity\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

abstract class ModuleServiceProvider extends ServiceProvider
{
    /**
     * The module name (e.g., 'core', 'identity').
     * Auto-detected from class namespace if not set.
     */
    protected ?string $moduleName = null;

    /**
     * The module base path.
     * Auto-detected from provider location if not set.
     */
    protected ?string $basePath = null;

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->ensureModuleProperties();
        $this->registerConfigs();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->ensureModuleProperties();
        $this->bootMigrations();
        $this->bootViews();
        $this->bootPublishing();
    }

    /**
     * Ensure module name and base path are set.
     */
    protected function ensureModuleProperties(): void
    {
        if (! $this->moduleName) {
            $namespace = (new \ReflectionClass($this))->getNamespaceName();
            preg_match('/LaraModule\\\\(\w+)/', $namespace, $matches);
            $this->moduleName = Str::lower($matches[1] ?? 'unknown');
        }

        if (! $this->basePath) {
            $this->basePath = dirname((new \ReflectionClass($this))->getFileName(), 2);
        }
    }

    /**
     * Register (merge) all configs from src/Configs directory.
     */
    protected function registerConfigs(): void
    {
        $configPath = $this->basePath.'/Configs';

        if (! is_dir($configPath)) {
            return;
        }

        foreach (glob($configPath.'/*.php') as $configFile) {
            $configName = basename($configFile, '.php');
            $moduleConfig = require $configFile;
            $this->app->booted(function () use ($configName, $moduleConfig) {
                config([$configName => array_replace_recursive(
                    config($configName, []),
                    $moduleConfig
                )]);
            });
        }
    }

    /**
     * Load migrations from src/Database/Migrations directory.
     */
    protected function bootMigrations(): void
    {
        $migrationsPath = $this->basePath.'/Database/Migrations';

        if (is_dir($migrationsPath)) {
            $this->loadMigrationsFrom($migrationsPath);
        }
    }

    /**
     * Load and register views from src/Views directory.
     */
    protected function bootViews(): void
    {
        $viewsPath = $this->basePath.'/Views';

        if (is_dir($viewsPath)) {
            $this->loadViewsFrom($viewsPath, "laramodular-{$this->moduleName}");
        }
    }

    /**
     * Publish configs, migrations, views, and public assets.
     */
    protected function bootPublishing(): void
    {
        $this->publishConfigs();
        $this->publishMigrations();
        $this->publishViews();
        $this->publishAssets();
    }

    /**
     * Publish all config files.
     */
    protected function publishConfigs(): void
    {
        $configPath = $this->basePath.'/Configs';

        if (! is_dir($configPath)) {
            return;
        }

        foreach (glob($configPath.'/*.php') as $configFile) {
            $configName = basename($configFile, '.php');
            $this->publishes([
                $configFile => config_path("{$configName}.php"),
            ], "laramodular-{$this->moduleName}-config");
        }
    }

    /**
     * Publish migrations.
     */
    protected function publishMigrations(): void
    {
        $migrationsPath = $this->basePath.'/Database/Migrations';

        if (is_dir($migrationsPath)) {
            $this->publishes([
                $migrationsPath => database_path('migrations'),
            ], "laramodular-{$this->moduleName}-migrations");
        }
    }

    /**
     * Publish views.
     */
    protected function publishViews(): void
    {
        $viewsPath = $this->basePath.'/Views';

        if (is_dir($viewsPath)) {
            $this->publishes([
                $viewsPath => resource_path("views/vendor/laramodular-{$this->moduleName}"),
            ], "laramodular-{$this->moduleName}-views");
        }
    }

    /**
     * Publish public assets from src/Public directory.
     */
    protected function publishAssets(): void
    {
        $publicPath = $this->basePath.'/Public';

        if (! is_dir($publicPath)) {
            return;
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
