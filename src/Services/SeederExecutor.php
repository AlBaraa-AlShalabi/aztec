<?php

declare(strict_types=1);

namespace Albaraa\Aztec\Services;

use Albaraa\Aztec\Support\ModuleLocator;
use RuntimeException;
use Symfony\Component\Process\Process;

class SeederExecutor
{
    private ModuleLocator $moduleLocator;

    public function __construct(?ModuleLocator $moduleLocator = null)
    {
        $this->moduleLocator = $moduleLocator ?? new ModuleLocator();
    }

    /**
     * Seed a specific module
     *
     * @param string $module
     * @param array $options
     * @return int
     */
    public function seed(string $module, array $options = []): int
    {
        try {
            $this->moduleLocator->locate($module);
        } catch (RuntimeException $e) {
            throw new RuntimeException("Module '{$module}' not found: " . $e->getMessage());
        }

        $command = $this->buildSeedCommand($module, $options);
        return $this->executeCommand($command);
    }

    /**
     * Build seed artisan command
     *
     * @param string $module
     * @param array $options
     * @return array
     */
    private function buildSeedCommand(string $module, array $options = []): array
    {
        $command = ['php', 'artisan', 'db:seed'];

        // Add path option for module seeders
        $seedersNamespace = 'Modules\\' . ucfirst($module) . '\\Database\\Seeders';
        $command[] = '--class=' . $seedersNamespace . '\\' . ucfirst($module) . 'DatabaseSeeder';

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
