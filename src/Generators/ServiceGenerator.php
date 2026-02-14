<?php

namespace Albaraa\Aztec\Generators;

use Albaraa\Aztec\Support\ModelSpec;
use Illuminate\Support\Str;

class ServiceGenerator extends Generator
{
    public function generate(bool|callable $force = false): string
    {
        $stub = $this->loadStub('service.stub');

        $replacements = array_merge($this->getCommonReplacements(), [
            '{{ serviceNamespace }}' => $this->getServiceNamespace(),
            '{{ serviceClass }}' => $this->getServiceClassName(),
            '{{ repositoryInterfaceNamespace }}' => $this->getRepositoryInterfaceNamespace(),
            '{{ repositoryInterface }}' => $this->spec->className . 'RepositoryInterface',
            '{{ modelFqcn }}' => $this->spec->fqcn,
            '{{ modelName }}' => $this->spec->className,
            '{{ filterParams }}' => $this->generateFilterParams(),
            '{{ searchLogic }}' => $this->generateSearchLogic(),
            '{{ filterLogic }}' => $this->generateFilterLogic(),
            '{{ syncRelationsCreate }}' => $this->generateSyncRelations('create'),
            '{{ syncRelationsUpdate }}' => $this->generateSyncRelations('update'),
        ]);

        $content = $this->replacePlaceholders($stub, $replacements);
        $path = $this->modulePath('Services/' . $this->getServiceClassName() . '.php');

        return $this->writeFile($path, $content, $force);
    }

    protected function getServiceNamespace(): string
    {
        return $this->getCommonReplacements()['{{ moduleNamespace }}'] . '\\Services';
    }

    protected function getServiceClassName(): string
    {
        return $this->spec->className . 'Service';
    }

    protected function getRepositoryInterfaceNamespace(): string
    {
        return $this->getCommonReplacements()['{{ moduleNamespace }}'] . '\\Repositories\\Interfaces';
    }

    protected function generateFilterParams(): string
    {
        $params = [];
        foreach ($this->spec->filters as $field => $type) {
            $phpType = match ($type) {
                'int' => 'int',
                'bool' => 'bool',
                'array' => 'array',
                default => 'string',
            };
            $params[] = ", ?{$phpType} \${$field} = null";
        }
        return implode('', $params);
    }

    protected function generateSearchLogic(): string
    {
        $lines = [];
        $candidates = array_intersect(
            $this->spec->fillable ?? [],
            ['name', 'title', 'description', 'email', 'body', 'content', 'slug']
        );

        if (empty($candidates) && !empty($this->spec->fillable)) {
             // Fallback: take first 3 string fields if available (heuristic)
             $candidates = array_slice($this->spec->fillable, 0, 3);
        }

        $logic = "";
        $first = true;

        foreach ($candidates as $field) {
            if ($this->isTranslatable($field)) {
                $methodEn = $first ? 'whereRaw' : 'orWhereRaw';
                $logic .= "\$q->{$methodEn}(\"LOWER(JSON_UNQUOTE(JSON_EXTRACT({$field}, '$.en'))) LIKE LOWER(?)\", [\$like])\n";
                $logic .= "                      ->orWhereRaw(\"LOWER(JSON_UNQUOTE(JSON_EXTRACT({$field}, '$.ar'))) LIKE LOWER(?)\", [\$like]);\n                      ";
            } else {
                $method = $first ? 'where' : 'orWhere';
                $logic .= "\$q->{$method}('{$field}', 'LIKE', \$like);\n                      ";
            }
            $first = false;
        }

        if (empty($logic)) {
             return '// No searchable fields detected automatically';
        }

        return rtrim($logic);
    }

    protected function isTranslatable(string $field): bool
    {
        return in_array($field, $this->spec->translatable ?? []);
    }

    protected function generateFilterLogic(): string
    {
        $lines = [];
        foreach ($this->spec->filters as $field => $type) {
            $lines[] = "->when(\${$field}, function (\$query, \$value) {";
            $lines[] = "                \$query->where('{$field}', \$value);";
            $lines[] = "            })";
        }
        return implode("\n            ", $lines);
    }

    protected function generateSyncRelations(string $context): string
    {
        $lines = [];
        foreach ($this->spec->syncRelations as $relation) {
             $lines[] = "if (isset(\$data['{$relation}'])) {";
             $lines[] = "                \$model->{$relation}()->sync(\$data['{$relation}']);";
             $lines[] = "            }";
        }
        return implode("\n            ", $lines);
    }
}
