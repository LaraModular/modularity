<?php

namespace LaraModularity\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use LaraModularity\ModuleRegistry;
use Throwable;

/**
 * Command to run module seeders in order.
 */
class ModuleSeedCommand extends Command
{
    protected $signature = 'module:seed {module? : Module name to seed} {class? : Specific seeder class} {--fresh : Truncate all tables first} {--connection= : Database connection to use}';

    protected $description = 'Seed database module by module';

    private Connection $connection;

    public function handle(): int
    {
        $moduleArg = $this->argument('module');
        $classArg = $this->argument('class');
        $fresh = $this->option('fresh');
        $this->connection = DB::connection($this->option('connection'));

        if ($fresh) {
            $this->info('Truncating all tables...');
            $this->truncateAllTables();
            $this->info('All tables truncated.');
        }

        $totalSeeded = 0;
        $modules = $moduleArg
            ? [$moduleArg => ModuleRegistry::getModuleInfo($moduleArg)]
            : ModuleRegistry::getAllModules();

        foreach ($modules as $moduleName => $module) {
            if ($module === null) {
                $this->warn("Module not found: $moduleName");

                continue;
            }

            $namespace = $module['namespace'].'\\Database\\Seeders';
            $mainSeederClass = "$namespace\\DatabaseSeeder";

            if ($classArg !== null) {
                $seederClass = str_contains($classArg, '\\') ? $classArg : "$namespace\\$classArg";
                if (! class_exists($seederClass)) {
                    $this->warn("  <comment>Seeder class $seederClass not found in module $moduleName.</comment>");

                    continue;
                }
                $toRun = [$seederClass];
            } else {
                if (! class_exists($mainSeederClass)) {
                    $this->warn("DatabaseSeeder not found for module: $moduleName");

                    continue;
                }
                $toRun = [$mainSeederClass];
            }

            $this->info("Running seeders for module: $moduleName");

            foreach ($toRun as $seederClass) {
                try {
                    $seeder = new $seederClass;
                    $seeder->run();
                    $this->line("  <info>✔ Seeded:</info> $seederClass");
                    $totalSeeded++;
                } catch (Throwable $e) {
                    $this->error("  ✘ Failed to seed: $seederClass");
                    $this->error('    '.$e->getMessage());
                    throw $e;
                }
            }
            $this->line('');
        }

        $this->info("All module seeders complete. Total seeded: $totalSeeded");

        return 0;
    }

    /**
     * Truncate all tables in the database.
     */
    private function truncateAllTables(): void
    {
        $schema = $this->connection->getSchemaBuilder();
        $tableNames = array_map(
            fn (array $table): string => $table['name'],
            $schema->getTables()
        );

        foreach ($tableNames as $tableName) {
            try {
                $table = $this->connection->table($tableName);
                $table->truncate();
            } catch (Throwable $e) {
                $this->warn("  ⚠ Could not truncate table $tableName: ".$e->getMessage());
            }
        }
    }
}
