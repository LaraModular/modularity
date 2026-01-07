<?php

namespace LaraModularity\Bootstrap;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Bootstrap\LoadConfiguration as BaseLoader;
use LaraModularity\ModuleRegistry;

/**
 * Bootstrap class that loads module configurations and views.
 *
 * This class extends Laravel's configuration loader to support modular architecture.
 * It validates module dependencies, loads module configs, and registers view paths.
 */
class ModuleConfigBootstrap extends BaseLoader
{
    public function bootstrap(Application $app): void
    {
        parent::bootstrap($app);

        $this->loadModuleConfigs($app);
        $this->loadModuleViews($app);
    }

    /**
     * Load configuration files from all registered modules.
     */
    private function loadModuleConfigs(Application $app): void
    {
        $fs = new Filesystem;

        foreach (ModuleRegistry::getAllModules() as $module) {
            $moduleDir = $module['path'];

            $configDir = $moduleDir.DIRECTORY_SEPARATOR.'Configs';
            if (! $fs->isDirectory($configDir)) {
                continue;
            }

            foreach ($fs->files($configDir) as $file) {
                $filename = pathinfo($file, PATHINFO_FILENAME);
                $original = $app['config']->get($filename, []);
                $moduleCfg = $fs->getRequire($file);

                $app['config']->set(
                    $filename,
                    array_replace_recursive($original, $moduleCfg)
                );
            }
        }

    }

    /**
     * Load views from all registered modules.
     */
    private function loadModuleViews(Application $app): void
    {
        $fs = new Filesystem;
        $paths = $app['config']->get('view.paths', []);

        foreach (ModuleRegistry::getAllModules() as $module) {
            $moduleDir = $module['path'];
            $viewDir = $moduleDir.DIRECTORY_SEPARATOR.'Views';
            if ($fs->isDirectory($viewDir)) {
                $paths[] = $viewDir;
            }
        }

        $app['config']->set('view.paths', $paths);
    }
}
