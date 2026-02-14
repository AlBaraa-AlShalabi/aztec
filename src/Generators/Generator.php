<?php

namespace Albaraa\Aztec\Generators;

use Albaraa\Aztec\Support\ModelSpec;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

abstract class Generator
{
    protected ModelSpec $spec;
    protected Filesystem $files;

    public function __construct(ModelSpec $spec, ?Filesystem $files = null)
    {
        $this->spec = $spec;
        $this->files = $files ?? app(Filesystem::class);
    }

    /**
     * Main entry point – generate the file and return the written path.
     */
    abstract public function generate(bool|callable $force = false): string|array;

    /**
     * Load a stub file from resources/stubs.
     */
    protected function loadStub(string $stubName): string
    {
        $path = realpath(__DIR__ . '/../../resources/stubs/' . $stubName);

        if (!$path || !$this->files->exists($path)) {
            throw new \RuntimeException("Stub file not found: {$stubName}");
        }

        return $this->files->get($path);
    }

    /**
     * Replace placeholders in content.
     */
    protected function replacePlaceholders(string $content, array $replacements): string
    {
        foreach ($replacements as $search => $replace) {
            $content = str_replace($search, $replace, $content);
        }

        return $content;
    }

    /**
     * Common placeholders available to all generators.
     */
    protected function getCommonReplacements(): array
    {
        $moduleNamespace = 'Modules\\' . ucfirst($this->spec->moduleName);

        return [
            '{{ moduleNamespace }}'       => $moduleNamespace,
            '{{ modelNamespace }}'        => dirname(str_replace('\\', '/', $this->spec->fqcn)), // e.g., Modules\Blog\Models
            '{{ modelFqcn }}'             => $this->spec->fqcn,
            '{{ model }}'                 => $this->spec->className,
            '{{ modelVariable }}'         => lcfirst($this->spec->className),
            '{{ modelPlural }}'           => Str::pluralStudly($this->spec->className),
            '{{ modelPluralLower }}'      => Str::lower(Str::pluralStudly($this->spec->className)),
            '{{ modelKebab }}'            => Str::kebab($this->spec->className),
            '{{ modelPluralKebab }}'      => Str::kebab(Str::pluralStudly($this->spec->className)),
        ];
    }

    /**
     * Write file with basic overwrite protection.
     */
    protected function writeFile(string $path, string $content, bool|callable $force = false): string
    {
        $canWrite = is_callable($force) ? $force($path) : $force;

        if ($this->files->exists($path) && !$canWrite) {
            // We return the path but skip the actual filesystem write
            return $path;
        }

        $directory = dirname($path);
        if (!$this->files->exists($directory)) {
            $this->files->makeDirectory($directory, 0755, true);
        }

        $this->files->put($path, $content);
        return $path;
    }

    /**
     * Get the module root path.
     */
    protected function modulePath(string $append = ''): string
    {
        $base = rtrim($this->spec->modulePath, DIRECTORY_SEPARATOR);
        
        // If "app" directory exists inside the module, use it as the source root
        if (is_dir($base . DIRECTORY_SEPARATOR . 'app')) {
            $base .= DIRECTORY_SEPARATOR . 'app';
        }

        return $base . ($append ? DIRECTORY_SEPARATOR . $append : '');
    }
}