<?php

declare(strict_types=1);

namespace Albaraa\Aztec\Services;

use Albaraa\Aztec\Support\ModuleLocator;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Symfony\Component\Process\Process;

class MigrationExecutor
{
    private ModuleLocator $moduleLocator;
    private ModuleOrderService $orderService;

    public function __construct(?ModuleLocator $moduleLocator = null, ?ModuleOrderService $orderService = null)
    {
        $this->moduleLocator = $moduleLocator ?? new ModuleLocator();
        $this->orderService = $orderService ?? app(ModuleOrderService::class);
    }

    /**
     * Migrate a specific module
     *
     * @param string $module
     * @param array $options
     * @return int
     */
    public function migrate(string $module, array $options = []): int
    {
        try {
            $this->moduleLocator->locate($module);
        } catch (RuntimeException $e) {
            throw new RuntimeException("Module '{$module}' not found: " . $e->getMessage());
        }

        $command = $this->buildMigrationCommand($module, $options);
        return $this->executeCommand($command);
    }

    /**
     * Rollback migrations for a specific module
     *
     * @param string $module
     * @param array $options
     * @return int
     */
    public function rollback(string $module, array $options = []): int
    {
        try {
            $this->moduleLocator->locate($module);
        } catch (RuntimeException $e) {
            throw new RuntimeException("Module '{$module}' not found: " . $e->getMessage());
        }

        $command = $this->buildRollbackCommand($module, $options);
        return $this->executeCommand($command);
    }

    /**
     * Migrate fresh for a specific module
     *
     * @param string $module
     * @param array $options
     * @return int
     */
    public function migrateFresh(string $module, array $options = []): int
    {
        try {
            $this->moduleLocator->locate($module);
        } catch (RuntimeException $e) {
            throw new RuntimeException("Module '{$module}' not found: " . $e->getMessage());
        }

        // First rollback
        $rollbackCommand = $this->buildRollbackCommand($module, array_merge($options, ['reset' => true]));
        $this->executeCommand($rollbackCommand);

        // Then migrate
        $migrateCommand = $this->buildMigrationCommand($module, $options);
        return $this->executeCommand($migrateCommand);
    }

    /**
     * Build migration artisan command
     *
     * @param string $module
     * @param array $options
     * @return array
     */
    private function buildMigrationCommand(string $module, array $options = []): array
    {
        $command = ['php', 'artisan', 'migrate'];

        // Add path option for module
        $modulePath = $this->getRelativeModulePath($module);
        $command[] = '--path=' . $modulePath . '/database/migrations';

        // Handle specific migration file
        if (!empty($options['migration'])) {
            $command[] = '--' . $options['migration'];
        }

        // Handle database connection
        if (!empty($options['database'])) {
            $command[] = '--database=' . $options['database'];
        }

        // Handle force flag
        if (!empty($options['force'])) {
            $command[] = '--force';
        }

        return $command;
    }

    /**
     * Build rollback artisan command
     *
     * @param string $module
     * @param array $options
     * @return array
     */
    private function buildRollbackCommand(string $module, array $options = []): array
    {
        $command = ['php', 'artisan'];

        // Use migrate:reset for full reset, migrate:rollback otherwise
        if (!empty($options['reset'])) {
            $command[] = 'migrate:reset';
        } else {
            $command[] = 'migrate:rollback';
        }

        // Add path option for module
        $modulePath = $this->getRelativeModulePath($module);
        $command[] = '--path=' . $modulePath . '/database/migrations';

        // Handle database connection
        if (!empty($options['database'])) {
            $command[] = '--database=' . $options['database'];
        }

        // Handle force flag
        if (!empty($options['force'])) {
            $command[] = '--force';
        }

        return $command;
    }

    /**
     * Get relative module path from base path
     *
     * @param string $module
     * @return string
     */
    private function getRelativeModulePath(string $module): string
    {
        $modulePath = $this->moduleLocator->locate($module);
        $basePath = base_path();
        
        return '/' . str_replace($basePath . DIRECTORY_SEPARATOR, '', $modulePath);
    }

    /**
     * Execute a command
     *
     * @param array $command
     * @return int
     */
    private function executeCommand(array $command): int
    {
        $process = new Process($command, base_path());
        $process->setTimeout(null);
        $process->run();

        if (!$process->isSuccessful()) {
            // Show both stdout and stderr for full DB error context
            echo $process->getOutput();
            echo $process->getErrorOutput();
        }

        return $process->getExitCode();
    }
}
