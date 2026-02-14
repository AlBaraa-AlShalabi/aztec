<?php

namespace Albaraa\Aztec\Generators;

class ResourceGenerator extends Generator
{
    public function generate(bool|callable $force = false): string
    {
        $stub = $this->loadStub('resource.stub');

        $replacements = array_merge($this->getCommonReplacements(), [
            '{{ resourcesNamespace }}' => $this->getResourcesNamespace(),
            '{{ resourceClass }}'      => $this->spec->className . 'Resource',
            '{{ toArrayMethod }}'      => $this->buildToArrayMethod(),
        ]);

        $content = $this->replacePlaceholders($stub, $replacements);

        $path = $this->modulePath('Http/Resources/' . $this->spec->className . 'Resource.php');

        return $this->writeFile($path, $content, $force);  // Pass $force here
    }

    protected function getResourcesNamespace(): string
    {
        return $this->getCommonReplacements()['{{ moduleNamespace }}'] . '\\Http\\Resources';
    }

    protected function buildToArrayMethod(): string
    {
        $relationLines = [];
        // Prioritize user selected relations, then fallback to 'with' (eager load) property
        $relations = !empty($this->spec->resourceRelations) 
            ? $this->spec->resourceRelations 
            : ($this->spec->with ?? []);

        foreach ($relations as $relation) {
            $relationLines[] = "            '{$relation}' => \$this->whenLoaded('{$relation}'),";
        }

        $translatableLines = [];
        foreach (($this->spec->translatable ?? []) as $field) {
            $translatableLines[] = "            '{$field}' => \$this->getTranslation('{$field}', app()->getLocale()),";
        }

        $extraLines = array_merge($relationLines, $translatableLines);

        if (empty($extraLines)) {
            return <<<PHP
    public function toArray(\$request): array
    {
        // TODO: Customize as needed (e.g., conditional attributes, formatted fields, custom logic)
        return parent::toArray(\$request);
    }
PHP;
        }

        $extras = implode("\n", $extraLines);

        return <<<PHP
    public function toArray(\$request): array
    {
        return array_merge(parent::toArray(\$request), [
{$extras}
        ]);
    }
PHP;
    }
}