<?php

declare(strict_types=1);

namespace Albaraa\Aztec\Console;

use Albaraa\Aztec\Generators\ControllerGenerator;
use Albaraa\Aztec\Generators\RequestsGenerator;
use Albaraa\Aztec\Generators\ResourceGenerator;
use Albaraa\Aztec\Generators\RepositoryGenerator;
use Albaraa\Aztec\Generators\ServiceGenerator;
use Albaraa\Aztec\Generators\RoutesGenerator;
use Albaraa\Aztec\Generators\Generator;
use Albaraa\Aztec\Inspectors\ClassResolver;
use Albaraa\Aztec\Inspectors\ModelLocator;
use Albaraa\Aztec\Inspectors\SourceResolver;
use Albaraa\Aztec\Support\ModelSpec;
use Albaraa\Aztec\Support\ModuleLocator;
use Illuminate\Console\Command;
use RuntimeException;

class MakeCrudCommand extends Command
{
    protected $signature = 'aztec:make-crud
                            {module : Module name}
                            {model : Model name}
                            {--force : Overwrite existing files}
                            {--only= : Comma-separated list of layers to generate (e.g. controller,requests,resource)}
                            {--no-schema : skip DB schema inspection (future)}';

    protected $description = 'Generate layered CRUD for a module model';

    public function handle(): int
    {
        $module = $this->argument('module');
        $model  = $this->argument('model');

        $this->info("Aztec discovery: module={$module} model={$model}");

        $locator = new ModuleLocator();
        try {
            $modulePath = $locator->locate($module);
            $this->line("Module path: {$modulePath}");
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $modelLocator = new ModelLocator();
        try {
            $modelFile = $modelLocator->locate($modulePath, $model);
            $this->line("Model file: {$modelFile}");
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $resolver = new SourceResolver();
        try {
            $meta = $resolver->resolve($modelFile);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $classResolver = new ClassResolver();
        try {
            $classMeta = $classResolver->resolve($meta['fqcn'], $modelFile);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $spec = ModelSpec::fromParts(
            moduleName: $module,
            modulePath: $modulePath,
            filePath: $modelFile,
            fqcn: $meta['fqcn'],
            className: $meta['class'],
            table: $meta['table'] ?? null,
            notes: array_merge($meta['notes'] ?? [], $classMeta['notes'] ?? []),
            casts: $classMeta['casts'] ?? null,
            fillable: $classMeta['fillable'] ?? null,
            guarded: $classMeta['guarded'] ?? null,
            hidden: $classMeta['hidden'] ?? null,
            appends: $classMeta['appends'] ?? null,
            with: $classMeta['with'] ?? null,
            connection: $classMeta['connection'] ?? null,
            translatable: $classMeta['translatable'] ?? null,
            relations: $classMeta['relations'] ?? [],
        );

        $layers = $this->determineLayers();

        if (in_array('resource', $layers) && !empty($spec->relations)) {
            $this->info('Found relationships: ' . implode(', ', $spec->relations));
            $selected = $this->choice(
                'Select relations to include in the Resource (defaults to eager loaded ones)',
                $spec->relations,
                null,
                null,
                true 
            );
            $spec->resourceRelations = $selected;
        }

        if (in_array('service', $layers)) {
            $this->info('Configuring Service Layer...');
            
            // 1. Filters
            if ($this->confirm('Do you want to add custom filters for the list method?', true)) {
                $filters = [];
                while (true) {
                    $field = $this->ask('Enter filter field name (leave empty to stop)');
                    if (empty($field)) break;
                    
                    $type = $this->choice(
                        "Select type for filter '{$field}'", 
                        ['string', 'int', 'bool', 'array'], 
                        0
                    );
                    $filters[$field] = $type;
                }
                $spec->filters = $filters;
            }

            // 2. Sync Relations
            if (!empty($spec->relations)) {
                 $syncParams = $this->choice(
                    'Select relations to sync on create/update',
                    $spec->relations,
                    null,
                    null,
                    true
                );
                // choice returns array if multiple is true
                $spec->syncRelations = is_array($syncParams) ? $syncParams : [$syncParams];
            }
        }

        if (empty($layers)) {
            $this->warn('No layers to generate (check config or --only option).');
            return self::SUCCESS;
        }

        $this->info('Starting CRUD generation...');

        foreach ($layers as $layer) {
            $generator = $this->resolveGenerator($layer, $spec);

            if (!$generator) {
                $this->warn("Skipping '{$layer}': Generator not implemented yet.");
                continue;
            }

            try {
                $shouldOverwrite = function (string $path) {
                    if ($this->option('force')) return true;
                    if (!file_exists($path)) return true;
                    
                    return $this->confirm(
                        "File [" . basename($path) . "] already exists. Overwrite?", 
                        false
                    );
                };

                $paths = $generator->generate($shouldOverwrite);

                $paths = is_array($paths) ? $paths : [$paths];

                foreach ($paths as $path) {
                    $relative = str_replace($spec->modulePath . '/', '', $path);
                    $this->info("Processed {$layer}: {$relative}");
                }
            } catch (\Exception $e) {
                $this->error("Failed to generate {$layer}: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info('CRUD generation completed.');
        return self::SUCCESS;
    }

    protected function determineLayers(): array
    {
        if ($only = $this->option('only')) {
            return array_filter(explode(',', $only));
        }

        return config('aztec.layers', []);
    }

    protected function resolveGenerator(string $layer, ModelSpec $spec): ?Generator
    {
        return match ($layer) {
            'controller' => new ControllerGenerator($spec),
            'requests'   => new RequestsGenerator($spec),
            'resource'   => new ResourceGenerator($spec),
            'repository' => new RepositoryGenerator($spec),
            'service'    => new ServiceGenerator($spec),
            'routes'     => new RoutesGenerator($spec),
            default      => null,
        };
    }
}