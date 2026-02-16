<?php

namespace Albaraa\Aztec\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

class ModuleLoader
{
    /**
     * Boot all modules.
     */
    public static function boot(): void
    {
        $modulesPath = base_path('Modules');

        if (!File::isDirectory($modulesPath)) {
            return;
        }

        $modules = File::directories($modulesPath);

        foreach ($modules as $modulePath) {
            // 1. Register Autoloading
            static::registerAutoloader($modulePath);
            
            // 2. Register Providers
            $moduleJsonPath = $modulePath . '/module.json';

            if (File::exists($moduleJsonPath)) {
                $moduleConfig = json_decode(File::get($moduleJsonPath), true);
                
                if (isset($moduleConfig['providers'])) {
                    foreach ($moduleConfig['providers'] as $provider) {
                        if (class_exists($provider)) {
                            app()->register($provider);
                        }
                    }
                }
            }
        }
    }

    protected static function registerAutoloader(string $modulePath): void
    {
        $composerJson = $modulePath . '/composer.json';
        if (File::exists($composerJson)) {
            $composerConfig = json_decode(File::get($composerJson), true);
            $psr4 = $composerConfig['autoload']['psr-4'] ?? [];
            if (!empty($psr4)) {
                foreach ($psr4 as $namespace => $path) {
                    // Handle array or string path
                    $paths = (array) $path;
                    foreach ($paths as $p) {
                         self::registerNamespace($namespace, $modulePath . '/' . $p);
                    }
                }
            }
        }
    }

    protected static function registerNamespace(string $namespace, string $path): void
    {
        spl_autoload_register(function ($class) use ($namespace, $path) {
            $len = strlen($namespace);
            if (strncmp($namespace, $class, $len) !== 0) {
                return;
            }
            $relative_class = substr($class, $len);
            $file = $path . str_replace('\\', '/', $relative_class) . '.php';
            if (file_exists($file)) {
                require $file;
            }
        });
    }
}
