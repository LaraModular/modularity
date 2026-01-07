<?php

namespace LaraModularity\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use LaraModularity\ModuleRegistry;
use Throwable;

/**
 * Command to run module migrations in order.
 */
class ModuleMigrateCommand extends Command
{
    protected $signature = 'module:migrate {module? : Module name to migrate} {class? : Specific migration class} {--fresh : Drop all tables first} {--seed : Seed after migration}';

    protected $description = 'Run migrations module by module';

    public function handle(): int
    {
        $moduleArg = $this->argument('module');
        $classArg = $this->argument('class');
        $fresh = $this->option('fresh');
        $seed = $this->option('seed');

        if ($fresh) {
            return $this->handleFreshMigration($moduleArg, $classArg, $seed);
        }

        return $this->runMigrations($moduleArg, $classArg, $seed);
    }

    /**
     * Handle fresh migration (drop all tables first).
     */
    private function handleFreshMigration(?string $moduleArg, ?string $classArg, bool $seed): int
    {
        $this->info('Dropping all tables...');
        $this->dropAllTables();
        $this->info('All tables dropped. Preparing migrations table...');

        return $this->runMigrations($moduleArg, $classArg, $seed);
    }

    /**
     * Run migrations for specified module(s).
     */
    private function runMigrations(?string $moduleArg, ?string $classArg, bool $seed): int
    {
        $totalMigrated = 0;
        $modules = $moduleArg
            ? [$moduleArg => ModuleRegistry::getModuleInfo($moduleArg)]
            : ModuleRegistry::getAllModules();

        foreach ($modules as $moduleName => $module) {
            if ($module === null) {
                $this->warn("Module not found: $moduleName");

                continue;
            }

            $migratorPath = $module['path'].'/Database/Migrations/DatabaseMigrator.php';
            if (! file_exists($migratorPath)) {
                $this->warn("DatabaseMigrator not found for module: $moduleName");

                continue;
            }

            $this->info("Running migrations for module: $moduleName");

            try {
                $migrationClasses = require $migratorPath;
                if (! is_array($migrationClasses) || empty($migrationClasses)) {
                    $this->line('  <comment>No migrations defined.</comment>');

                    continue;
                }

                $toRun = $this->filterMigrations($migrationClasses, $classArg, $moduleName);
                if ($toRun === null) {
                    continue;
                }

                foreach ($toRun as $migrationClass) {
                    $fullClassName = $module['namespace']."\\Database\\Migrations\\$migrationClass";
                    if (! class_exists($fullClassName)) {
                        $this->error("  ✘ Migration class not found: $fullClassName");

                        continue;
                    }

                    /** @var Migration $migration */
                    $migration = new $fullClassName;
                    $connection = $migration->getConnection();
                    $migrationsTable = $this->ensureMigrationsTable($connection);

                    if ($migrationsTable->where('migration', $migrationClass)->exists()) {
                        $this->line("  <comment>Already migrated:</comment> $migrationClass");

                        continue;
                    }

                    try {
                        $this->runMigration($migration, $connection);
                        $migrationsTable->insert([
                            'migration' => $migrationClass,
                            'batch' => 1,
                        ]);

                        $this->line("  <info>✔ Migrated:</info> $migrationClass");
                        $totalMigrated++;
                    } catch (Throwable $e) {
                        $this->error("  ✘ Failed to migrate: $migrationClass");
                        $this->error('    '.$e->getMessage());
                        throw $e;
                    }
                }
            } catch (Throwable $e) {
                $this->error("  ✘ Failed to load DatabaseMigrator for module: $moduleName");
                $this->error('    '.$e->getMessage());
                throw $e;
            }
            $this->line('');
        }

        $this->info("All module migrations complete. Total migrated: $totalMigrated");

        if ($seed) {
            $this->runSeedAfterMigration($moduleArg, $classArg);
        }

        return 0;
    }

    /**
     * Filter migrations to run based on class argument.
     *
     * @param  array<int, string>  $migrationClasses
     * @return array<int, string>|null
     */
    private function filterMigrations(array $migrationClasses, ?string $classArg, string $moduleName): ?array
    {
        if ($classArg === null) {
            return $migrationClasses;
        }

        if (! in_array($classArg, $migrationClasses, true)) {
            $this->warn("  <comment>Class $classArg not found in module $moduleName.</comment>");

            return null;
        }

        return [$classArg];
    }

    private function ensureMigrationsTable(string $connection): Builder
    {
        $dbConnection = DB::connection($connection);
        $schemaBuilder = $dbConnection->getSchemaBuilder();

        if (! $schemaBuilder->hasTable('migrations')) {
            $schemaBuilder->create('migrations', function ($table) {
                $table->increments('id');
                $table->string('migration');
                $table->integer('batch');
            });
            $this->info('Migrations table created for connection: '.($connection ?: 'default'));
        }

        return $dbConnection->table('migrations');
    }

    private function runMigration(Migration $migration, string $connection): void
    {
        $originalConnection = DB::getDefaultConnection();
        DB::setDefaultConnection($connection);

        if (method_exists($migration, 'up')) {
            try {
                $migration->up();
            } finally {
                DB::setDefaultConnection($originalConnection);
            }
        }
    }

    private function runSeedAfterMigration(?string $moduleArg, ?string $classArg): void
    {
        $this->info('Seeding after migration...');
        $args = [];

        if ($moduleArg !== null) {
            $args['module'] = $moduleArg;
        }
        if ($classArg !== null) {
            $args['class'] = $classArg;
        }

        $this->call('module:seed', $args);
    }

    private function dropAllTables()
    {
        $schema = DB::getSchemaBuilder();
        $schema->dropAllTables();
        $schema->dropAllViews();
        $schema->dropAllTypes();
    }
}
