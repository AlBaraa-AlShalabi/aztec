<?php

declare(strict_types=1);

namespace Albaraa\Aztec\Console;

use Albaraa\Aztec\Services\ModuleOrderService;
use Albaraa\Aztec\Services\MigrationService;
use Illuminate\Console\Command;

class SetModuleOrderCommand extends Command
{
    protected $signature = 'aztec:order {--reset : Clear the existing module order}';

    protected $description = 'Set or update the order of modules for migrations and seeders';

    private ModuleOrderService $orderService;
    private MigrationService $migrationService;

    public function __construct(ModuleOrderService $orderService, MigrationService $migrationService)
    {
        parent::__construct();
        $this->orderService = $orderService;
        $this->migrationService = $migrationService;
    }

    public function handle(): int
    {
        if ($this->option('reset')) {
            return $this->resetOrder();
        }

        $modules = $this->migrationService->getAvailableModules();

        if (empty($modules)) {
            $this->warn('No modules with migrations found.');
            return self::SUCCESS;
        }

        // Check if order already exists
        $currentOrder = [];
        if ($this->orderService->hasOrder()) {
            $currentOrder = $this->orderService->getOrder();
            $this->line('');
            $this->info('Current module order: ' . implode(' → ', $currentOrder));
            $this->line('');

            if (!$this->confirm('Do you want to change the module order?', false)) {
                $this->info('Module order remains unchanged.');
                return self::SUCCESS;
            }

            $this->line('');
        }

        return $this->interactivelySetOrder($modules);
    }

    /**
     * Interactively set the module order
     *
     * @param array $modules
     * @return int
     */
    private function interactivelySetOrder(array $modules): int
    {
        $this->info('Setting module order for migrations and seeders');
        $this->line('Available modules: ' . implode(', ', $modules));
        $this->line('');

        $orderedModules = [];
        $remainingModules = $modules;

        foreach (range(1, count($modules)) as $position) {
            $this->line("<comment>Position {$position}:</comment>");

            $selected = $this->choice(
                'Select a module',
                $remainingModules,
                0
            );

            $orderedModules[] = $selected;
            $remainingModules = array_values(array_filter($remainingModules, fn($m) => $m !== $selected));

            $this->info("✓ {$selected} assigned to position {$position}");
            $this->line('');
        }

        // Show the final order
        $this->line('');
        $this->info('Module order to be saved:');
        foreach ($orderedModules as $index => $module) {
            $this->line(sprintf('  %d. %s', $index + 1, $module));
        }
        $this->line('');

        if (!$this->confirm('Does this order look correct?', true)) {
            $this->info('Order not saved. Please try again.');
            return $this->interactivelySetOrder($modules);
        }

        try {
            $this->orderService->saveOrder($orderedModules);
            $this->info('✓ Module order saved successfully!');
            $this->line('You can now run migrations and seeders with: php artisan aztec:migrate or php artisan aztec:seed');
            return self::SUCCESS;
        } catch (\RuntimeException $e) {
            $this->error('Failed to save module order: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Reset the module order
     *
     * @return int
     */
    private function resetOrder(): int
    {
        if (!$this->orderService->hasOrder()) {
            $this->info('No module order is currently set.');
            return self::SUCCESS;
        }

        $currentOrder = $this->orderService->getOrder();
        $this->line('Current module order: ' . implode(' → ', $currentOrder));
        $this->line('');

        if (!$this->confirm('Are you sure you want to clear the module order?', false)) {
            $this->info('Module order remains unchanged.');
            return self::SUCCESS;
        }

        try {
            $this->orderService->clearOrder();
            $this->info('✓ Module order cleared successfully!');
            return self::SUCCESS;
        } catch (\RuntimeException $e) {
            $this->error('Failed to clear module order: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
