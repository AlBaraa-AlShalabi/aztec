<?php

declare(strict_types=1);

namespace Albaraa\Aztec\Console;

use Albaraa\Aztec\Services\SeederExecutor;
use Albaraa\Aztec\Services\SeederService;
use Albaraa\Aztec\Services\ModuleOrderService;
use Illuminate\Console\Command;

class SeedModuleCommand extends Command
{
    protected $signature = 'aztec:seed 
                            {module? : The module name to seed (optional)}
                            {--database= : The database connection to use}
                            {--force : Force the operation to run when not in production}';

    protected $description = 'Seed a specific module or all modules in order';

    private SeederExecutor $executor;
    private SeederService $seederService;
    private ModuleOrderService $orderService;

    public function __construct(
        SeederExecutor $executor,
        SeederService $seederService,
        ModuleOrderService $orderService
    ) {
        parent::__construct();
        $this->executor = $executor;
        $this->seederService = $seederService;
        $this->orderService = $orderService;
    }

    public function handle(): int
    {
        $module = $this->argument('module');

        // If module is specified, seed only that module
        if (!empty($module)) {
            return $this->seedSpecificModule($module);
        }

        // If no module specified, use ordered seeding
        return $this->seedInOrder();
    }

    /**
     * Seed a specific module
     *
     * @param string $module
     * @return int
     */
    private function seedSpecificModule(string $module): int
    {
        $options = $this->buildOptions();

        try {
            $this->info("Seeding module: {$module}");
            $result = $this->executor->seed($module, $options);

            if ($result === 0) {
                $this->info("Successfully seeded module: {$module}");
            } else {
                $this->error("Failed to seed module: {$module}");
            }

            return $result;
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Seed all modules in order
     *
     * @return int
     */
    private function seedInOrder(): int
    {
        $modules = $this->seederService->getAvailableModules();

        if (empty($modules)) {
            $this->warn('No modules with seeders found.');
            return self::SUCCESS;
        }

        // Check if order exists
        if (!$this->orderService->hasOrder()) {
            return $this->promptForModuleOrder($modules);
        }

        $orderedModules = $this->orderService->getOrder();

        // Filter to only existing modules
        $modulesToSeed = array_filter($orderedModules, function ($module) use ($modules) {
            return in_array($module, $modules);
        });

        if (empty($modulesToSeed)) {
            $this->warn('No ordered modules found to seed.');
            return self::SUCCESS;
        }

        $options = $this->buildOptions();
        $failed = false;

        foreach ($modulesToSeed as $module) {
            $this->line('');
            $this->info("Seeding module: {$module}");

            try {
                $result = $this->executor->seed($module, $options);

                if ($result !== 0) {
                    $this->error("Seeding failed for module: {$module}");
                    $failed = true;
                } else {
                    $this->info("✓ Module {$module} seeded successfully");
                }
            } catch (\RuntimeException $e) {
                $this->error("Error seeding module {$module}: " . $e->getMessage());
                $failed = true;
            }
        }

        $this->line('');
        if (!$failed) {
            $this->info('All modules seeded successfully in order!');
            return self::SUCCESS;
        }

        $this->warn('Some modules failed to seed.');
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
        $this->info('No module seeding order is set.');
        $this->line('');

        if (!$this->confirm('Would you like to set the module seeding order now?', false)) {
            $this->info('Skipping seeding. Run `php artisan aztec:order` to set module order.');
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

        // Now proceed with seeding
        return $this->seedInOrder();
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
