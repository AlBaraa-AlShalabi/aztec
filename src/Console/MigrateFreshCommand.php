<?php

declare(strict_types=1);

namespace Albaraa\Aztec\Console;

use Albaraa\Aztec\Services\MigrationExecutor;
use Albaraa\Aztec\Services\MigrationService;
use Albaraa\Aztec\Services\ModuleOrderService;
use Illuminate\Console\Command;

class MigrateFreshCommand extends Command
{
    protected $signature = 'aztec:migrate-fresh {module? : The module name to fresh migrate (optional)} {--database= : The database connection to use} {--force : Force the operation to run when not in production}';
    protected $description = 'Drop all tables and re-run migrations for a specific module or all modules in order';

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
        $options = $this->buildOptions();

        if (!empty($module)) {
            $this->info("Running fresh migrations for module: {$module}");
            $result = $this->executor->migrateFresh($module, $options);
            return $result;
        }

        $modules = $this->migrationService->getAvailableModules();
        if (empty($modules)) {
            $this->warn('No modules with migrations found.');
            return self::SUCCESS;
        }
        if (!$this->orderService->hasOrder()) {
            $this->warn('No module order set. Run `php artisan aztec:order` first.');
            return self::FAILURE;
        }
        $orderedModules = $this->orderService->getOrder();
        $modulesToMigrate = array_filter($orderedModules, fn($m) => in_array($m, $modules));
        $failed = false;
        foreach ($modulesToMigrate as $mod) {
            $this->info("Running fresh migrations for module: {$mod}");
            $result = $this->executor->migrateFresh($mod, $options);
            if ($result !== 0) {
                $this->error("Fresh migration failed for module: {$mod}");
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
