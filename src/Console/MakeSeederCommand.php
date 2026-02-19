<?php

declare(strict_types=1);

namespace Albaraa\Aztec\Console;

use Albaraa\Aztec\Services\SeederService;
use Illuminate\Console\Command;

class MakeSeederCommand extends Command
{
    protected $signature = 'aztec:make-seeder 
                            {name : The name of the seeder}
                            {module : The name of the module}';

    protected $description = 'Create a new seeder for a module';

    private SeederService $seederService;

    public function __construct(SeederService $seederService)
    {
        parent::__construct();
        $this->seederService = $seederService;
    }

    public function handle(): int
    {
        $name = $this->argument('name');
        $module = $this->argument('module');

        try {
            // Check if seeder already exists
            if ($this->seederService->seederExists($module, $name)) {
                if (!$this->confirm("Seeder already exists for module {$module}. Do you want to overwrite?", false)) {
                    $this->info('Action cancelled.');
                    return self::SUCCESS;
                }
            }

            $filepath = $this->seederService->createSeeder($module, $name);
            $this->info("Seeder created successfully: {$filepath}");
            return self::SUCCESS;
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
