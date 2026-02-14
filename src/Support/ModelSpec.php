<?php
declare(strict_types=1);

namespace Albaraa\Aztec\Support;

/**
 * Lightweight ModelSpec value object.
 * Extend this as we discover more metadata.
 */
final class ModelSpec
{
    public function __construct(
        public string $moduleName,
        public string $modulePath,
        public string $filePath,
        public string $fqcn,
        public string $className,
        public ?string $table = null,
        public array $notes = [],
        public ?array $casts = null,
        public ?array $fillable = null,
        public ?array $guarded = null,
        public ?array $hidden = null,
        public ?array $appends = null,
        public ?array $with = null,
        public ?string $connection = null,
        public ?array $translatable = null,
        public array $relations = [],
        public array $resourceRelations = [],
        // Add more as needed for future inspectors
    ) {
    }

    public function toArray(): array
    {
        return [
            'module' => $this->moduleName,
            'module_path' => $this->modulePath,
            'file_path' => $this->filePath,
            'fqcn' => $this->fqcn,
            'class' => $this->className,
            'table' => $this->table,
            'notes' => $this->notes,
            'casts' => $this->casts,
            'fillable' => $this->fillable,
            'guarded' => $this->guarded,
            'hidden' => $this->hidden,
            'appends' => $this->appends,
            'with' => $this->with,
            'connection' => $this->connection,
            'translatable' => $this->translatable,
            'relations' => $this->relations,
            'resource_relations' => $this->resourceRelations,
        ];
    }

    public static function fromParts(
        string $moduleName,
        string $modulePath,
        string $filePath,
        string $fqcn,
        string $className,
        ?string $table = null,
        array $notes = [],
        ?array $casts = null,
        ?array $fillable = null,
        ?array $guarded = null,
        ?array $hidden = null,
        ?array $appends = null,
        ?array $with = null,
        ?string $connection = null,
        ?array $translatable = null,
        array $relations = [],
        array $resourceRelations = [],
    ): self {
        return new self(
            $moduleName, $modulePath, $filePath, $fqcn, $className, $table, $notes,
            $casts, $fillable, $guarded, $hidden, $appends, $with, $connection, $translatable,
            $relations, $resourceRelations
        );
    }
}