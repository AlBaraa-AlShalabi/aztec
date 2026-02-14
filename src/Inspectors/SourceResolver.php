<?php

declare(strict_types=1);

namespace Albaraa\Aztec\Inspectors;

use PhpParser\Error;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use RuntimeException;

class SourceResolver
{
    protected \PhpParser\Parser $parser;
    protected NodeFinder $finder;

    public function __construct()
    {
        // Updated for php-parser v5+: use createForHostVersion()
        $this->parser = (new ParserFactory)->createForHostVersion();
        $this->finder = new NodeFinder();
    }

    /**
     * Parse a PHP file and attempt to extract FQCN and the protected $table property (if present).
     *
     * @param string $filePath
     * @return array{fqcn:string, class:string, table:?string, notes:array}
     * @throws RuntimeException
     */
    public function resolve(string $filePath): array
    {
        if (!is_file($filePath)) {
            throw new RuntimeException("File not found: {$filePath}");
        }

        $code = file_get_contents($filePath);
        if ($code === false) {
            throw new RuntimeException("Unable to read file: {$filePath}");
        }

        try {
            $ast = $this->parser->parse($code);
        } catch (Error $e) {
            throw new RuntimeException("PHP-Parser error while parsing {$filePath}: " . $e->getMessage());
        }

        $namespace = $this->finder->findFirstInstanceOf($ast, Node\Stmt\Namespace_::class);
        $nsName = $namespace instanceof Node\Stmt\Namespace_ && $namespace->name ? $namespace->name->toString() : '';

        $classNode = $this->finder->findFirstInstanceOf($ast, Node\Stmt\Class_::class);
        if (! $classNode instanceof Node\Stmt\Class_) {
            throw new RuntimeException("No class found in file {$filePath}");
        }

        $className = $classNode->name?->toString() ?? null;
        if (! $className) {
            throw new RuntimeException("Unnamed class in file {$filePath}");
        }

        $fqcn = $nsName !== '' ? $nsName . '\\' . $className : $className;

        $table = null;
        $notes = [];

        foreach ($classNode->getProperties() as $prop) {
            foreach ($prop->props as $p) {
                $propName = $p->name->toString();
                if ($propName === 'table') {
                    if ($p->default instanceof Node\Scalar\String_) {
                        $table = $p->default->value;
                    } else {
                        $notes[] = 'table_property_non_literal';
                    }
                }
            }
        }

        return [
            'fqcn' => $fqcn,
            'class' => $className,
            'table' => $table,
            'notes' => $notes,
        ];
    }
}