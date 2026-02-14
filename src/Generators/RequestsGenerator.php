<?php

namespace Albaraa\Aztec\Generators;

use Illuminate\Support\Str;

class RequestsGenerator extends Generator
{
    public function generate(bool|callable $force = false): array
    {
        $paths = [];

        $paths[] = $this->generateStoreRequest($force);
        $paths[] = $this->generateUpdateRequest($force);

        return $paths;
    }

    protected function generateStoreRequest(bool|callable $force = false): string
    {
        $stub = $this->loadStub('store-request.stub');
        $rules = $this->buildRules(true);

        $replacements = array_merge($this->getCommonReplacements(), [
            '{{ requestsNamespace }}' => $this->getRequestsNamespace(),
            '{{ model }}'              => $this->spec->className,
            '{{ rules }}'              => $this->formatRules($rules),
        ]);

        $content = $this->replacePlaceholders($stub, $replacements);
        $path = $this->modulePath('Http/Requests/' . $this->spec->className . 'StoreRequest.php');

        return $this->writeFile($path, $content, $force);
    }

    protected function generateUpdateRequest(bool|callable $force = false): string
    {
        $stub = $this->loadStub('update-request.stub');
        $rules = $this->buildRules(false);

        $replacements = array_merge($this->getCommonReplacements(), [
            '{{ requestsNamespace }}' => $this->getRequestsNamespace(),
            '{{ model }}'              => $this->spec->className,
            '{{ rules }}'              => $this->formatRules($rules),
        ]);

        $content = $this->replacePlaceholders($stub, $replacements);
        $path = $this->modulePath('Http/Requests/' . $this->spec->className . 'UpdateRequest.php');

        return $this->writeFile($path, $content, $force);
    }

    protected function getRequestsNamespace(): string
    {
        return $this->getCommonReplacements()['{{ moduleNamespace }}'] . '\\Http\\Requests';
    }

    protected function buildRules(bool $isStore): array
    {
        $fields = $this->spec->fillable ?? [];
        $translatable = $this->spec->translatable ?? [];
        $locales = config('aztec.locales', ['en']);

        $rules = [];

        foreach ($fields as $field) {
            if (in_array($field, $translatable)) {
                // Translatable field: array + per-locale rules
                $baseRule = $isStore ? ['required', 'array'] : ['sometimes', 'array'];
                $rules["'{$field}'"] = $baseRule;

                foreach ($locales as $locale) {
                    $localeRule = $isStore ? ['required', 'string'] : ['sometimes', 'string'];
                    $rules["'{$field}.{$locale}'"] = $localeRule;
                }
            } else {
                // Normal field
                $fieldRules = $this->getFieldRules($field, $isStore);
                $rules["'{$field}'"] = $fieldRules;
            }
        }

        return $rules;
    }

    protected function getFieldRules(string $field, bool $isStore): array
    {
        $rules = $isStore ? ['required'] : ['sometimes'];

        $cast = is_array($this->spec->casts) ? ($this->spec->casts[$field] ?? null) : null;

        if (str_ends_with($field, '_id')) {
            $rules[] = 'integer';
            $relationName = Str::camel(substr($field, 0, -3));

            if (!class_exists($this->spec->fqcn) && file_exists($this->spec->filePath)) {
                @include_once $this->spec->filePath;
            }

            if (class_exists($this->spec->fqcn) && method_exists($this->spec->fqcn, $relationName)) {
                try {
                    $modelInstance = new ($this->spec->fqcn);
                    $relation = $modelInstance->$relationName();

                    if ($relation instanceof \Illuminate\Database\Eloquent\Relations\Relation) {
                        $relatedTable = $relation->getRelated()->getTable();
                        $relatedKey = $relation->getRelated()->getKeyName();

                        $rules[] = "exists:{$relatedTable},{$relatedKey}";
                        return $rules;
                    }
                } catch (\Throwable $e) {
                }
            }

            $fileContent = file_get_contents($this->spec->filePath);
            if (preg_match('/public\s+function\s+' . $relationName . '\s*\(\s*\)/', $fileContent, $match, PREG_OFFSET_CAPTURE)) {
                $offset = $match[0][1];
                $body = substr($fileContent, $offset, 500);
                if (preg_match('/belongsTo\(\s*([a-zA-Z0-9_\\\\]+)::class/', $body, $m)) {
                    $relatedClass = class_basename($m[1]);
                    $relatedTable = Str::snake(Str::plural($relatedClass));
                    $rules[] = "exists:{$relatedTable},id";
                }
            }
            return $rules;
        }

        switch ($cast) {
            case 'int':
            case 'integer':
                $rules[] = 'integer';
                break;
            case 'float':
            case 'double':
            case 'decimal':
                $rules[] = 'numeric';
                break;
            case 'bool':
            case 'boolean':
                $rules[] = 'boolean';
                break;
            case 'date':
            case 'datetime':
                $rules[] = 'date';
                break;
            case 'array':
            case 'json':
                $rules[] = 'array';
                break;
            default:
                $rules[] = 'string';
                $rules[] = 'max:255';
                break;
        }

        if ($field === 'email') {
            $rules[] = 'email';
        }

        if ($field === 'password') {
            $rules[] = 'confirmed';
            if ($isStore) {
                $rules[] = 'min:8';
            }
        }

        return $rules;
    }

    protected function formatRules(array $rules): string
    {
        if (empty($rules)) {
            return "            // TODO: No fillable fields detected in the model. Define validation rules manually.";
        }

        $lines = [];
        foreach ($rules as $field => $fieldRules) {
            $ruleString = implode("', '", $fieldRules);
            $lines[] = "            {$field} => ['{$ruleString}'],";
        }

        return implode("\n", $lines);
    }
}