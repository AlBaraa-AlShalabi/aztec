<?php

namespace Albaraa\Aztec\Console;

use Illuminate\Support\Str;

class MakeModelCommand extends BaseModuleGeneratorCommand
{
    protected $signature = 'aztec:make-model {name} {module}';
    protected $description = 'Create a new model for a module';
    protected $type = 'Model';

    protected function getStub(): string
    {
        return __DIR__ . '/../../resources/stubs/modules/files/model.stub';
    }

    protected function getDestinationPath(string $module, string $name): string
    {
        $name = str_replace(['\\', '/'], '/', $name);
        return module_path($module, 'app/Models/' . $name . '.php');
    }

    protected function getNamespace(string $module, string $name): string
    {
        $name = str_replace(['\\', '/'], '/', $name);
        $path = dirname($name);
        $namespace = 'Modules\\' . $module . '\\Models';

        if ($path !== '.') {
            $namespace .= '\\' . str_replace('/', '\\', $path);
        }

        return $namespace;
    }
}
