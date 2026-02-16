<?php

namespace Albaraa\Aztec\Console;

use Illuminate\Support\Str;

class MakeRequestCommand extends BaseModuleGeneratorCommand
{
    protected $signature = 'aztec:make-request {name} {module}';
    protected $description = 'Create a new form request for a module';
    protected $type = 'Request';

    protected function getStub(): string
    {
        return __DIR__ . '/../../resources/stubs/modules/files/request.stub';
    }

    protected function getDestinationPath(string $module, string $name): string
    {
        $name = str_replace(['\\', '/'], '/', $name);
        return module_path($module, 'app/Http/Requests/' . $name . '.php');
    }

    protected function getNamespace(string $module, string $name): string
    {
        $name = str_replace(['\\', '/'], '/', $name);
        $path = dirname($name);
        $namespace = 'Modules\\' . $module . '\\Http\\Requests';

        if ($path !== '.') {
            $namespace .= '\\' . str_replace('/', '\\', $path);
        }

        return $namespace;
    }
}
