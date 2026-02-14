<?php

namespace Albaraa\Aztec\Generators;

class ControllerGenerator extends Generator
{
    public function generate(bool|callable $force = false): string
    {
        $stub = $this->loadStub('controller.stub');

        $moduleNamespace = $this->getCommonReplacements()['{{ moduleNamespace }}'];

        $withRelations = $this->spec->with ?? [];
        $withClauseIndex = !empty($withRelations) ? "->with(['" . implode("', '", $withRelations) . "'])" : '';
        $withClauseShow = !empty($withRelations) ? "->loadMissing(['" . implode("', '", $withRelations) . "'])" : '';

        $replacements = array_merge($this->getCommonReplacements(), [
            '{{ controllerNamespace }}'   => $moduleNamespace . '\\Http\\Controllers',
            '{{ controllerClass }}'       => $this->spec->className . 'Controller',
            '{{ requestsNamespace }}'     => $moduleNamespace . '\\Http\\Requests',
            '{{ resourcesNamespace }}'    => $moduleNamespace . '\\Http\\Resources',
            '{{ serviceNamespace }}'      => $moduleNamespace . '\\Services',
            '{{ serviceClass }}'          => $this->spec->className . 'Service',
            '{{ listMethodArgs }}'        => $this->generateListMethodArgs(),
            '{{ storeRequest }}'          => $this->spec->className . 'StoreRequest',
            '{{ updateRequest }}'         => $this->spec->className . 'UpdateRequest',
            '{{ resourceClass }}'         => $this->spec->className . 'Resource',
            '{{ eagerLoadIndex }}'        => $withClauseIndex,
            '{{ eagerLoadShow }}'         => $withClauseShow,
        ]);

        $content = $this->replacePlaceholders($stub, $replacements);

        $path = $this->modulePath('Http/Controllers/' . $this->spec->className . 'Controller.php');

        return $this->writeFile($path, $content, $force);
    }

    protected function generateListMethodArgs(): string
    {
        $args = [];
        $args[] = "search: \$request->query('q')";
        $args[] = "perPage: \$request->query('per_page', 15)";

        foreach ($this->spec->filters as $field => $type) {
            $value = match ($type) {
                'int' => "intval(\$request->query('{$field}'))",
                'bool' => "filter_var(\$request->query('{$field}'), FILTER_VALIDATE_BOOLEAN)",
                'array' => "\$request->query('{$field}')",
                default => "\$request->query('{$field}')",
            };
            
            // Should pass null if not present?
            // "it should pass the query params always"
            // If the query param is not present, we should pass null?
            // The service method signature: list(?string $field = null)
            // If we call list(field: null), that's fine.
            // If we call list(field: query('missing')), query returns null by default.
            // But intval(null) -> 0. filter_var(null) -> false.
            // So for int and bool, we need check.
            
            if ($type === 'int' || $type === 'bool') {
                 $args[] = "{$field}: \$request->has('{$field}') ? {$value} : null";
            } else {
                 $args[] = "{$field}: {$value}";
            }
        }

        return implode(",\n            ", $args);
    }
}