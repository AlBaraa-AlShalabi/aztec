<?php
declare(strict_types=1);

namespace Albaraa\Aztec\Inspectors;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

class ClassResolver
{
    protected NodeFinder $finder;

    public function __construct()
    {
        $this->finder = new NodeFinder();
    }

    /**
     * Extract class-level metadata from a model.
     * Prefers Reflection if the class is autoloadable (e.g., in a running Laravel app).
     * Falls back to AST parsing otherwise.
     *
     * @param string $fqcn Fully Qualified Class Name of the model
     * @param string $filePath Absolute path to the model file (for AST fallback)
     * @return array{
     *     casts: array<string, string>|null,
     *     fillable: string[]|null,
     *     guarded: string[]|null,
     *     hidden: string[]|null,
     *     appends: string[]|null,
     *     with: string[]|null,
     *     connection: string|null,
     *     translatable: string[]|null,
     *     notes: string[]
     * }
     * @throws RuntimeException
     */
    public function resolve(string $fqcn, string $filePath): array
    {
        $notes = [];
        $astRelations = $this->scanRelationsFromSource($filePath);

        try {
            $reflection = new ReflectionClass($fqcn);
            $properties = $this->extractFromReflection($reflection);
            
            $properties['relations'] = array_unique(array_merge(
                $properties['relations'] ?? [], 
                $astRelations
            ));

            return $properties;

        } catch (ReflectionException $e) {
            $notes[] = 'reflection_failed_autoload';
            return array_merge(
                $this->extractFromAst($filePath),
                ['notes' => $notes]
            );
        }
    }

    /**
     * Extract metadata using Reflection.
     */
    protected function extractFromReflection(ReflectionClass $reflection): array
    {
        $defaults = $reflection->getDefaultProperties();

        return [
            'casts' => $this->getProperty($defaults, 'casts', []),
            'fillable' => $this->getProperty($defaults, 'fillable', []),
            'guarded' => $this->getProperty($defaults, 'guarded', ['*']),
            'hidden' => $this->getProperty($defaults, 'hidden', []),
            'appends' => $this->getProperty($defaults, 'appends', []),
            'with' => $this->getProperty($defaults, 'with', []),
            'connection' => $this->getProperty($defaults, 'connection', null),
            'translatable' => $this->getProperty($defaults, 'translatable', []),
            'relations' => $this->extractRelationsFromReflection($reflection),
            'notes' => [],
        ];
    }

    /**
     * Helper to get a property from defaults, handling null/undefined.
     */
    protected function getProperty(array $defaults, string $name, mixed $fallback): mixed
    {
        return $defaults[$name] ?? $fallback;
    }

    /**
     * Extract metadata using AST parsing (fallback).
     */
    protected function extractFromAst(string $filePath): array
    {
        $code = file_get_contents($filePath);
        if ($code === false) {
            throw new RuntimeException("Unable to read file: {$filePath}");
        }

        $parser = (new ParserFactory)->createForHostVersion();
        $ast = $parser->parse($code);

        $classNode = $this->finder->findFirstInstanceOf($ast, Node\Stmt\Class_::class);
        if (!$classNode instanceof Node\Stmt\Class_) {
            throw new RuntimeException("No class found in file {$filePath}");
        }

        $properties = [
            'casts' => null,
            'fillable' => null,
            'guarded' => null,
            'hidden' => null,
            'appends' => null,
            'with' => null,
            'connection' => null,
            'translatable' => null,
            'notes' => [],
        ];

        foreach ($classNode->getProperties() as $prop) {
            foreach ($prop->props as $p) {
                $propName = $p->name->toString();

                if (in_array($propName, ['casts', 'fillable', 'guarded', 'hidden', 'appends', 'with', 'translatable'])) {
                    if ($p->default instanceof Node\Expr\Array_) {
                        $properties[$propName] = $this->extractArrayItems($p->default);
                    } elseif ($p->default instanceof Node\Scalar\String_) {
                        $properties[$propName] = $p->default->value;
                    } else {
                        $properties['notes'][] = "{$propName}_property_non_literal";
                    }
                } elseif ($propName === 'connection') {
                    if ($p->default instanceof Node\Scalar\String_) {
                        $properties['connection'] = $p->default->value;
                    } else {
                        $properties['notes'][] = 'connection_property_non_literal';
                    }
                }
            }
        }

        $properties['relations'] = $this->analyzeRelationsInNode($classNode);

        return $properties;
    }

    protected function scanRelationsFromSource(string $filePath): array
    {
        $code = file_get_contents($filePath);
        if ($code === false) return [];

        $parser = (new ParserFactory)->createForHostVersion();
        try {
            $ast = $parser->parse($code);
            $classNode = $this->finder->findFirstInstanceOf($ast, Node\Stmt\Class_::class);
            
            if ($classNode instanceof Node\Stmt\Class_) {
                return $this->analyzeRelationsInNode($classNode);
            }
        } catch (\Throwable $e) {
        }

        return [];
    }

    protected function analyzeRelationsInNode(Node\Stmt\Class_ $classNode): array
    {
        $relations = [];
        $methods = $classNode->getMethods();

        foreach ($methods as $method) {
            if (!$method->isPublic()) continue;
            
            $stmts = $method->stmts ?? [];
            foreach ($stmts as $stmt) {
                if ($stmt instanceof Node\Stmt\Return_ && $stmt->expr instanceof Node\Expr\MethodCall) {
                    $var = $stmt->expr->var;
                    $name = $stmt->expr->name;
                    
                    if ($var instanceof Node\Expr\Variable && $var->name === 'this' && $name instanceof Node\Identifier) {
                        $relationMethods = ['hasOne', 'hasMany', 'belongsTo', 'belongsToMany', 'morphTo', 'morphOne', 'morphMany'];
                        if (in_array($name->toString(), $relationMethods)) {
                            $relations[] = $method->name->toString();
                        }
                    }
                }
            }
        }
        return $relations;
    }

    /**
     * Extract items from an AST array node.
     */
    protected function extractArrayItems(Node\Expr\Array_ $array): array
    {
        $items = [];
        foreach ($array->items as $item) {
            if ($item->value instanceof Node\Scalar\String_) {
                $items[] = $item->value->value;
            } elseif ($item->value instanceof Node\Expr\ArrayDimFetch || $item->value instanceof Node\Expr\ConstFetch) {
            }
        }
        return $items;
    }
    /**
     * Extract relationship methods using Reflection.
     */
    protected function extractRelationsFromReflection(ReflectionClass $reflection): array
    {
        $relations = [];
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            $returnType = $method->getReturnType();
            if ($returnType instanceof \ReflectionNamedType && !$returnType->isBuiltin()) {
                $typeName = $returnType->getName();
                if (is_a($typeName, \Illuminate\Database\Eloquent\Relations\Relation::class, true)) {
                    $relations[] = $method->getName();
                }
            }
        }

        return $relations;
    }

    protected function isUserDefinedTrait(\ReflectionMethod $method, ReflectionClass $class): bool
    {
        return false;
    }
}