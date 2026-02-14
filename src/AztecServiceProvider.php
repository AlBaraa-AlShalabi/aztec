<?php

namespace Albaraa\Aztec;

use Illuminate\Support\ServiceProvider;
use Albaraa\Aztec\Console\MakeCrudCommand;

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
    }
}