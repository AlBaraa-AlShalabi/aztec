<?php

namespace Albaraa\Aztec\Console;

use Illuminate\Support\Str;

class MakeControllerCommand extends BaseModuleGeneratorCommand
{
    protected $signature = 'aztec:make-controller {name} {module}';
    protected $description = 'Create a new controller for a module';
    protected $type = 'Controller';

    protected function getStub(): string
    {
        return __DIR__ . '/../../resources/stubs/modules/files/controller.stub';
    }

    protected function getDestinationPath(string $module, string $name): string
    {
        $name = str_replace(['\\', '/'], '/', $name);
        return module_path($module, 'app/Http/Controllers/' . $name . '.php');
    }

    protected function getNamespace(string $module, string $name): string
    {
        $name = str_replace(['\\', '/'], '/', $name);
        $path = dirname($name);
        $namespace = 'Modules\\' . $module . '\\Http\\Controllers';

        if ($path !== '.') {
            $namespace .= '\\' . str_replace('/', '\\', $path);
        }

        return $namespace;
    }
}
