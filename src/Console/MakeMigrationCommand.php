<?php

declare(strict_types=1);

namespace Albaraa\Aztec\Console;

use Albaraa\Aztec\Services\MigrationService;
use Illuminate\Console\Command;

class MakeMigrationCommand extends Command
{
    protected $signature = 'aztec:make-migration 
                            {name : The name of the migration}
                            {module : The name of the module}';

    protected $description = 'Create a new migration for a module (Laravel style naming)';

    private MigrationService $migrationService;

    public function __construct(MigrationService $migrationService)
    {
        parent::__construct();
        $this->migrationService = $migrationService;
    }

    public function handle(): int
    {
        $name = $this->argument('name');
        $module = $this->argument('module');

        try {
            $filepath = $this->migrationService->createMigration($module, $name);
            $this->info("Migration created successfully: {$filepath}");
            return self::SUCCESS;
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
