<?php

namespace Albaraa\Aztec\Generators;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Albaraa\Aztec\Support\ModuleLocator;

class ModuleGenerator
{
    protected string $name;
    protected string $modulePath;
    protected Filesystem $files;

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->files = new Filesystem();
        $this->modulePath = base_path('Modules' . DIRECTORY_SEPARATOR . $name);
    }

    public function generate(): void
    {
        $this->createFolders();
        $this->generateFiles();
    }

    protected function createFolders(): void
    {
        $folders = [
            'app/Http/Controllers',
            'app/Http/Requests',
            'app/Models',
            'app/Providers',
            'app/Repositories/Interfaces',
            'app/Services',
            'config',
            'database/factories',
            'database/migrations',
            'database/seeders',
            'resources/assets/js',
            'resources/assets/sass',
            'resources/views/components/layouts',
            'routes',
            'tests/Feature',
            'tests/Unit',
        ];

        foreach ($folders as $folder) {
            $path = $this->modulePath . DIRECTORY_SEPARATOR . $folder;
            if (!$this->files->exists($path)) {
                $this->files->makeDirectory($path, 0755, true);
            }
        }
    }

    protected function generateFiles(): void
    {
        $stubs = [
            'module.stub' => 'module.json',
            'composer.stub' => 'composer.json',
            'config.stub' => 'config/config.php',
            'scaffold/provider.stub' => 'app/Providers/' . $this->name . 'ServiceProvider.php',
            'scaffold/route-provider.stub' => 'app/Providers/RouteServiceProvider.php',
            'scaffold/event-provider.stub' => 'app/Providers/EventServiceProvider.php',
            'scaffold/seeder.stub' => 'database/seeders/' . $this->name . 'DatabaseSeeder.php',
            'scaffold/controller.stub' => 'app/Http/Controllers/' . $this->name . 'Controller.php',
            'scaffold/routes-web.stub' => 'routes/web.php',
            'scaffold/routes-api.stub' => 'routes/api.php',
            'scaffold/vite.stub' => 'vite.config.js',
            'scaffold/view-master.stub' => 'resources/views/components/layouts/master.blade.php',
            'scaffold/view-index.stub' => 'resources/views/index.blade.php',
        ];

        foreach ($stubs as $stub => $file) {
            $source = __DIR__ . '/../../resources/stubs/modules/' . $stub;
            $destination = $this->modulePath . DIRECTORY_SEPARATOR . $file;

            $content = $this->files->get($source);
            $content = $this->replacePlaceholders($content);

            $this->files->put($destination, $content);
        }
        
        // Create empty files if needed
        $this->files->put($this->modulePath . '/resources/assets/js/app.js', '');
        $this->files->put($this->modulePath . '/resources/assets/sass/app.scss', '');
    }

    protected function replacePlaceholders(string $content): string
    {
        $replacements = [
            '$STUDLY_NAME$' => Str::studly($this->name),
            '$LOWER_NAME$' => Str::lower($this->name),
            '$MODULE_NAMESPACE$' => 'Modules',
            '$VENDOR$' => 'modules',
            '$AUTHOR_NAME$' => '',
            '$AUTHOR_EMAIL$' => '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }
}
