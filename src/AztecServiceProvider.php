<?php

namespace Albaraa\Aztec;

use Illuminate\Support\ServiceProvider;
use Albaraa\Aztec\Console\MakeCrudCommand;
use Albaraa\Aztec\Console\MakeModuleCommand;
use Albaraa\Aztec\Console\MakeControllerCommand;
use Albaraa\Aztec\Console\MakeModelCommand;
use Albaraa\Aztec\Console\MakeRequestCommand;
use Albaraa\Aztec\Console\MakeResourceCommand;
use Albaraa\Aztec\Support\ModuleLoader;

class AztecServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            dirname(__DIR__) . '/config/aztec.php',
            'aztec'
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeCrudCommand::class,
                MakeModuleCommand::class,
                MakeControllerCommand::class,
                MakeModelCommand::class,
                MakeRequestCommand::class,
                MakeResourceCommand::class,
            ]);

            $sourceConfig = realpath(dirname(__DIR__) . '/config/aztec.php') ?: dirname(__DIR__) . '/config/aztec.php';
            $this->publishes([
                $sourceConfig => config_path('aztec.php'),
            ], ['aztec-config', 'config']);

            $sourceStubs = realpath(dirname(__DIR__) . '/resources/stubs') ?: dirname(__DIR__) . '/resources/stubs';
            $this->publishes([
                $sourceStubs => base_path('stubs/aztec'),
            ], ['aztec-stubs', 'stubs']);
        }

        ModuleLoader::boot();
    }
}