<?php

declare(strict_types=1);

namespace Albaraa\Aztec\Console;

use Albaraa\Aztec\Services\MigrationExecutor;
use Albaraa\Aztec\Services\MigrationService;
use Albaraa\Aztec\Services\ModuleOrderService;
use Illuminate\Console\Command;

class MigrateRollbackCommand extends Command
{
    protected $signature = 'aztec:migrate-rollback {module? : The module name to rollback (optional)} {--step=0 : Number of steps to rollback} {--database= : The database connection to use} {--force : Force the operation to run when not in production}';
    protected $description = 'Rollback the last migration for a specific module or all modules in order';

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
            $this->info("Rolling back migrations for module: {$module}");
            $result = $this->executor->rollback($module, $options);
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
        $modulesToRollback = array_filter($orderedModules, fn($m) => in_array($m, $modules));
        $failed = false;
        foreach ($modulesToRollback as $mod) {
            $this->info("Rolling back migrations for module: {$mod}");
            $result = $this->executor->rollback($mod, $options);
            if ($result !== 0) {
                $this->error("Rollback failed for module: {$mod}");
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
        if ($this->option('step')) {
            $options['step'] = $this->option('step');
        }
        return $options;
    }
}
