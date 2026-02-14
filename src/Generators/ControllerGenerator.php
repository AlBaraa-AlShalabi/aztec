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
            '{{ storeRequest }}'          => $this->spec->className . 'StoreRequest',
            '{{ updateRequest }}'         => $this->spec->className . 'UpdateRequest',
            '{{ resourceClass }}'         => $this->spec->className . 'Resource',
            '{{ eagerLoadIndex }}'        => $withClauseIndex,
            '{{ eagerLoadShow }}'         => $withClauseShow,
        ]);

        $content = $this->replacePlaceholders($stub, $replacements);

        $path = $this->modulePath('Http/Controllers/' . $this->spec->className . 'Controller.php');

        return $this->writeFile($path, $content, $force);  // Pass $force here
    }
}