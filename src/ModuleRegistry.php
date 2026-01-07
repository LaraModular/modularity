<?php

namespace LaraModularity;

use RuntimeException;

class ModuleRegistry
{
    private const string COMPOSER_JSON_FILE = 'composer.json';

    private const string COMPOSER_INSTALLED_FILE = 'vendor/composer/installed.json';

    private static function basePath(string $path = ''): string
    {
        static $base = null;

        if ($base === null) {
            if (function_exists('app') && app()->bound('path.base')) {
                $base = app()->basePath();
            } else {
                $base = dirname(__DIR__);
            }
        }

        return $path !== '' ? $base.'/'.ltrim($path, '/') : $base;
    }

    private static function appPath(string $path = ''): string
    {
        static $appBase = null;

        if ($appBase === null) {
            if (function_exists('app') && app()->bound('path')) {
                $appBase = app()->path();
            } else {
                $appBase = __DIR__;
            }
        }

        return $path !== '' ? $appBase.'/'.ltrim($path, '/') : $appBase;
    }

    /**
     * Get the list of module names in order.
     *
     * @return array<int, string>
     */
    public static function getModuleOrder(): array
    {
        static $order = null;

        if ($order === null) {
            $composerPath = self::basePath(self::COMPOSER_JSON_FILE);

            if (! file_exists($composerPath)) {
                return $order = [];
            }

            $composer = json_decode(file_get_contents($composerPath), true);
            $order = $composer['extra']['laravel-modules']['order'] ?? [];
        }

        return $order;
    }

    /**
     * Get module information including name, namespace, and base path.
     *
     * @return array{name: string, namespace: string, path: string}|null
     */
    public static function getModuleInfo(string $moduleName): ?array
    {
        static $infoCache = [];

        if (isset($infoCache[$moduleName])) {
            return $infoCache[$moduleName];
        }

        // First check if it's a local module in app/
        $localPath = self::appPath($moduleName);
        if (is_dir($localPath)) {
            return $infoCache[$moduleName] = [
                'name' => $moduleName,
                'namespace' => "App\\$moduleName",
                'path' => $localPath,
            ];
        }

        // Then check if it's a composer package
        $packages = self::getInstalledPackages();
        if (empty($packages)) {
            return $infoCache[$moduleName] = null;
        }

        foreach ($packages as $package) {
            $extra = $package['extra']['laravel-module'] ?? null;
            if (! $extra) {
                continue;
            }

            // Match module name (case-insensitive)
            if (strcasecmp($extra['name'] ?? '', $moduleName) === 0) {
                $vendorPath = self::basePath('vendor/'.$package['name']);

                return $infoCache[$moduleName] = [
                    'name' => $moduleName,
                    'namespace' => $extra['namespace'] ?? '',
                    'path' => $vendorPath.'/'.$extra['root'],
                ];
            }
        }

        return $infoCache[$moduleName] = null;
    }

    /**
     * Get all registered modules information.
     *
     * @return array<string, array{name: string, namespace: string, path: string}>
     */
    public static function getAllModules(): array
    {
        static $modules = null;

        if ($modules === null) {
            $modules = [];

            foreach (self::getModuleOrder() as $moduleName) {
                $info = self::getModuleInfo($moduleName);
                if ($info !== null) {
                    $modules[$moduleName] = $info;
                }
            }
        }

        return $modules;
    }

    /**
     * Get list of installed composer packages.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function getInstalledPackages(): array
    {
        static $installedPackages = null;

        if ($installedPackages !== null) {
            return $installedPackages;
        }

        $composerPath = self::basePath(self::COMPOSER_INSTALLED_FILE);
        if (! file_exists($composerPath)) {
            return $installedPackages = [];
        }

        $installed = json_decode(file_get_contents($composerPath), true);
        $packages = [];

        foreach ($installed['packages'] ?? [] as $package) {
            $packages[$package['name']] = $package;
        }

        return $installedPackages = $packages;
    }

    /**
     * Get module's required dependencies from its composer.json.
     *
     * @return array<string, string>
     */
    public static function getModuleDependencies(string $moduleName): array
    {
        static $depsCache = [];

        if (isset($depsCache[$moduleName])) {
            return $depsCache[$moduleName];
        }

        $packages = self::getInstalledPackages();

        foreach ($packages as $package) {
            $extra = $package['extra']['laravel-module'] ?? null;
            if (! $extra) {
                continue;
            }

            if (strcasecmp($extra['name'] ?? '', $moduleName) === 0) {
                $require = $package['require'] ?? [];

                return $depsCache[$moduleName] = array_filter(
                    $require,
                    fn (string $key): bool => ! in_array($key, ['php', 'laravel/framework'], true),
                    ARRAY_FILTER_USE_KEY
                );
            }
        }

        return $depsCache[$moduleName] = [];
    }

    /**
     * Validate that all module dependencies are installed.
     *
     * @throws RuntimeException
     */
    public static function validateModuleDependencies(string $moduleName): void
    {
        $dependencies = self::getModuleDependencies($moduleName);
        if (empty($dependencies)) {
            return;
        }

        $installed = self::getInstalledPackages();
        $missing = [];

        foreach ($dependencies as $package => $version) {
            if (! isset($installed[$package])) {
                $missing[] = "$package ($version)";
            }
        }

        if (! empty($missing)) {
            $packages = implode("\n", array_map(fn (string $pkg): string => "  - $pkg", $missing));
            $installCmd = implode(' ', array_map(fn (string $pkg): string => explode(' ', $pkg)[0], $missing));

            throw new RuntimeException(
                "Module '$moduleName' requires the following packages that are not installed:\n".
                $packages."\n\n".
                'Install them with: composer require '.$installCmd
            );
        }
    }

    /**
     * Validate all registered modules have their dependencies installed.
     *
     * @throws RuntimeException
     */
    public static function validateAllModules(): void
    {
        $moduleOrder = self::getModuleOrder();
        foreach ($moduleOrder as $moduleName) {
            self::validateModuleDependencies($moduleName);
        }
    }
}
