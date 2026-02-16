<?php

namespace Albaraa\Aztec\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

abstract class BaseModuleGeneratorCommand extends Command
{
    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    abstract protected function getStub(): string;
    abstract protected function getDestinationPath(string $module, string $name): string;
    abstract protected function getNamespace(string $module, string $name): string;

    public function handle(): int
    {
        $name = $this->argument('name');
        $module = $this->argument('module');

        $path = $this->getDestinationPath($module, $name);

        if ($this->files->exists($path)) {
            $this->error($this->type . ' already exists!');
            return self::FAILURE;
        }

        $this->makeDirectory($path);

        $this->files->put($path, $this->buildClass($module, $name));

        $this->info($this->type . ' created successfully.');

        return self::SUCCESS;
    }

    protected function buildClass(string $module, string $name): string
    {
        $stub = $this->files->get($this->getStub());

        return $this->replacePlaceholders($stub, $module, $name);
    }

    protected function replacePlaceholders(string $stub, string $module, string $name): string
    {
        $class = class_basename($name);
        $namespace = $this->getNamespace($module, $name);

        return str_replace(
            ['$NAMESPACE$', '$CLASS$', '$MODULE$', '$MODULE_LOWER$'],
            [$namespace, $class, $module, Str::lower($module)],
            $stub
        );
    }

    protected function makeDirectory(string $path): void
    {
        if (!$this->files->isDirectory(dirname($path))) {
            $this->files->makeDirectory(dirname($path), 0777, true, true);
        }
    }
}
