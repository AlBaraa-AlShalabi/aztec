<?php

namespace Albaraa\Aztec\Console;

use Illuminate\Support\Str;

class MakeResourceCommand extends BaseModuleGeneratorCommand
{
    protected $signature = 'aztec:make-resource {name} {module}';
    protected $description = 'Create a new resource for a module';
    protected $type = 'Resource';

    protected function getStub(): string
    {
        return __DIR__ . '/../../resources/stubs/modules/files/resource.stub';
    }

    protected function getDestinationPath(string $module, string $name): string
    {
        $name = str_replace(['\\', '/'], '/', $name);
        return module_path($module, 'app/Http/Resources/' . $name . '.php');
    }

    protected function getNamespace(string $module, string $name): string
    {
        $name = str_replace(['\\', '/'], '/', $name);
        $path = dirname($name);
        $namespace = 'Modules\\' . $module . '\\Http\\Resources';

        if ($path !== '.') {
            $namespace .= '\\' . str_replace('/', '\\', $path);
        }

        return $namespace;
    }
}
