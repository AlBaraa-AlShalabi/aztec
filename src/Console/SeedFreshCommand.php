<?php

declare(strict_types=1);

namespace Albaraa\Aztec\Console;

use Albaraa\Aztec\Services\SeederExecutor;
use Albaraa\Aztec\Services\SeederService;
use Albaraa\Aztec\Services\ModuleOrderService;
use Illuminate\Console\Command;

class SeedFreshCommand extends Command
{
    protected $signature = 'aztec:seed-fresh {module? : The module name to seed (optional)} {--database= : The database connection to use} {--force : Force the operation to run when not in production}';
    protected $description = 'Fresh seed a specific module or all modules in order (truncate and reseed)';

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
        $options = $this->buildOptions();

        // NOTE: Laravel does not have a built-in db:seed --fresh, so this is a custom placeholder
        // You may want to truncate tables before seeding here if needed
        if (!empty($module)) {
            $this->info("Fresh seeding module: {$module}");
            // Custom logic for truncating tables can be added here
            $result = $this->executor->seed($module, $options);
            return $result;
        }

        $modules = $this->seederService->getAvailableModules();
        if (empty($modules)) {
            $this->warn('No modules with seeders found.');
            return self::SUCCESS;
        }
        if (!$this->orderService->hasOrder()) {
            $this->warn('No module order set. Run `php artisan aztec:order` first.');
            return self::FAILURE;
        }
        $orderedModules = $this->orderService->getOrder();
        $modulesToSeed = array_filter($orderedModules, fn($m) => in_array($m, $modules));
        $failed = false;
        foreach ($modulesToSeed as $mod) {
            $this->info("Fresh seeding module: {$mod}");
            // Custom logic for truncating tables can be added here
            $result = $this->executor->seed($mod, $options);
            if ($result !== 0) {
                $this->error("Fresh seeding failed for module: {$mod}");
                $failed = true;
            }
        }
        return $failed ? self::FAILURE : self::SUCCESS;
    }

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
