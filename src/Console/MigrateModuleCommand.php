<?php

declare(strict_types=1);

namespace Albaraa\Aztec\Console;

use Albaraa\Aztec\Services\MigrationExecutor;
use Albaraa\Aztec\Services\MigrationService;
use Albaraa\Aztec\Services\ModuleOrderService;
use Illuminate\Console\Command;

class MigrateModuleCommand extends Command
{
    protected $signature = 'aztec:migrate 
                            {module? : The module name to migrate (optional)}
                            {--fresh : Drop all tables and re-run migrations}
                            {--rollback : Rollback the last migration}
                            {--reset : Rollback all migrations}
                            {--step=0 : Number of steps to rollback}
                            {--database= : The database connection to use}
                            {--force : Force the operation to run when not in production}';

    protected $description = 'Run migrations for a specific module or all modules in order';

    private MigrationExecutor $executor;
    private MigrationService $migrationService;
    private ModuleOrderService $orderService;

    public function __construct(
        MigrationExecutor $executor,
        MigrationService $migrationService,
        ModuleOrderService $orderService
    ) {
        parent::__construct();
        $this->executor = $executor;
        $this->migrationService = $migrationService;
        $this->orderService = $orderService;
    }

    public function handle(): int
    {
        $module = $this->argument('module');

        // If module is specified, migrate only that module
        if (!empty($module)) {
            return $this->migrateSpecificModule($module);
        }

        // If no module specified, use ordered migration
        return $this->migrateInOrder();
    }

    /**
     * Migrate a specific module
     *
     * @param string $module
     * @return int
     */
    private function migrateSpecificModule(string $module): int
    {
        $options = $this->buildOptions();

        try {
            if ($this->option('rollback')) {
                $this->info("Rolling back migrations for module: {$module}");
                $result = $this->executor->rollback($module, $options);
            } elseif ($this->option('reset')) {
                $this->info("Resetting all migrations for module: {$module}");
                $result = $this->executor->rollback($module, array_merge($options, ['reset' => true]));
            } elseif ($this->option('fresh')) {
                $this->info("Running fresh migrations for module: {$module}");
                $result = $this->executor->migrateFresh($module, $options);
            } else {
                $this->info("Running migrations for module: {$module}");
                $result = $this->executor->migrate($module, $options);
            }

            if ($result === 0) {
                $this->info("Successfully completed migrations for module: {$module}");
            }

            return $result;
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Migrate all modules in order
     *
     * @return int
     */
    private function migrateInOrder(): int
    {
        $modules = $this->migrationService->getAvailableModules();

        if (empty($modules)) {
            $this->warn('No modules with migrations found.');
            return self::SUCCESS;
        }

        // Check if order exists
        if (!$this->orderService->hasOrder()) {
            return $this->promptForModuleOrder($modules);
        }

        $orderedModules = $this->orderService->getOrder();

        // Filter to only existing modules
        $modulesToMigrate = array_filter($orderedModules, function ($module) use ($modules) {
            return in_array($module, $modules);
        });

        if (empty($modulesToMigrate)) {
            $this->warn('No ordered modules found to migrate.');
            return self::SUCCESS;
        }

        $options = $this->buildOptions();
        $failed = false;

        foreach ($modulesToMigrate as $module) {
            $this->line('');
            $this->info("Migrating module: {$module}");

            try {
                if ($this->option('rollback')) {
                    $result = $this->executor->rollback($module, $options);
                } elseif ($this->option('reset')) {
                    $result = $this->executor->rollback($module, array_merge($options, ['reset' => true]));
                } elseif ($this->option('fresh')) {
                    $result = $this->executor->migrateFresh($module, $options);
                } else {
                    $result = $this->executor->migrate($module, $options);
                }

                if ($result !== 0) {
                    $this->error("Migration failed for module: {$module}");
                    $failed = true;
                } else {
                    $this->info("✓ Module {$module} migrated successfully");
                }
            } catch (\RuntimeException $e) {
                $this->error("Error migrating module {$module}: " . $e->getMessage());
                $failed = true;
            }
        }

        $this->line('');
        if (!$failed) {
            $this->info('All modules migrated successfully in order!');
            return self::SUCCESS;
        }

        $this->warn('Some modules failed to migrate.');
        return self::FAILURE;
    }

    /**
     * Prompt user to set module order
     *
     * @param array $modules
     * @return int
     */
    private function promptForModuleOrder(array $modules): int
    {
        $this->line('');
        $this->info('No module migration order is set.');
        $this->line('');

        if (!$this->confirm('Would you like to set the module migration order now?', false)) {
            $this->info('Skipping migration. Run `php artisan aztec:order` to set module order.');
            return self::SUCCESS;
        }

        return $this->setModuleOrder($modules);
    }

    /**
     * Set module order interactively
     *
     * @param array $modules
     * @return int
     */
    private function setModuleOrder(array $modules): int
    {
        $this->line('');
        $this->info('Available modules: ' . implode(', ', $modules));
        $this->line('');

        $orderedModules = [];
        $remainingModules = $modules;

        foreach (range(1, count($modules)) as $position) {
            $selected = $this->choice(
                "Select module for position {$position}",
                $remainingModules
            );

            $orderedModules[] = $selected;
            $remainingModules = array_values(array_filter($remainingModules, fn($m) => $m !== $selected));
        }

        $this->orderService->saveOrder($orderedModules);
        $this->info('Module order saved successfully!');

        // Now proceed with migration
        return $this->migrateInOrder();
    }

    /**
     * Build options array for executor
     *
     * @return array
     */
    private function buildOptions(): array
    {
        $options = [];

        if ($this->option('database')) {
            $options['database'] = $this->option('database');
        }

        if ($this->option('force')) {
            $options['force'] = true;
        }

        return $options;
    }
}
