<?php

declare(strict_types=1);

namespace Albaraa\Aztec\Services;

use Albaraa\Aztec\Support\ModuleLocator;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;

class MigrationService
{
    private ModuleLocator $moduleLocator;
    private Filesystem $files;

    public function __construct(?ModuleLocator $moduleLocator = null, ?Filesystem $files = null)
    {
        $this->moduleLocator = $moduleLocator ?? new ModuleLocator();
        $this->files = $files ?? app(Filesystem::class);
    }

    /**
     * Get all available modules
     *
     * @return array
     */
    public function getAvailableModules(): array
    {
        $modulesPath = config('aztec.modules_path', base_path('Modules'));

        if (!is_dir($modulesPath)) {
            return [];
        }

        $modules = [];
        foreach (scandir($modulesPath) as $item) {
            $itemPath = rtrim($modulesPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $item;
            if (is_dir($itemPath) && $item !== '.' && $item !== '..' && is_dir($itemPath . '/database/migrations')) {
                $modules[] = $item;
            }
        }

        return sort($modules) ? $modules : [];
    }

    /**
     * Get migrations for a specific module
     *
     * @param string $module
     * @return array
     */
    public function getMigrationsForModule(string $module): array
    {
        try {
            $modulePath = $this->moduleLocator->locate($module);
            $migrationsPath = $modulePath . '/database/migrations';

            if (!is_dir($migrationsPath)) {
                return [];
            }

            $migrations = [];
            foreach (scandir($migrationsPath) as $file) {
                if (substr($file, -4) === '.php' && $file !== '.' && $file !== '..') {
                    $migrations[] = $file;
                }
            }

            return $migrations;
        } catch (RuntimeException $e) {
            throw new RuntimeException("Module '{$module}' not found: " . $e->getMessage());
        }
    }

    /**
     * Get the migration file path for a module
     *
     * @param string $module
     * @return string
     */
    public function getMigrationsPath(string $module): string
    {
        try {
            $modulePath = $this->moduleLocator->locate($module);
            return $modulePath . '/database/migrations';
        } catch (RuntimeException $e) {
            throw new RuntimeException("Module '{$module}' not found: " . $e->getMessage());
        }
    }

    /**
     * Check if a migration file exists in a module
     *
     * @param string $module
     * @param string $migrationName
     * @return bool
     */
    public function migrationExists(string $module, string $migrationName): bool
    {
        $migrationsPath = $this->getMigrationsPath($module);
        $filePath = $migrationsPath . '/' . $migrationName . '.php';

        return $this->files->exists($filePath);
    }

    /**
     * Create a migration file for a module, inferring type and table from the name (Laravel style)
     *
     * @param string $module
     * @param string $name
     * @return string
     */
    public function createMigration(string $module, string $name): string
    {
        $migrationsPath = $this->getMigrationsPath($module);
        $timestamp = date('Y_m_d_His');
        $filename = "{$timestamp}_{$name}.php";
        $filepath = $migrationsPath . DIRECTORY_SEPARATOR . $filename;

        // Infer type and table from migration name
        [$type, $table] = $this->parseMigrationName($name);
        $stub = $this->loadMigrationStub($type);
        $class = $this->getClassName($name);
        $content = str_replace(
            ['{{ class }}', '{{ table }}'],
            [$class, $table],
            $stub
        );

        if (!$this->files->isDirectory($migrationsPath)) {
            $this->files->makeDirectory($migrationsPath, 0755, true);
        }

        $this->files->put($filepath, $content);
        return $filepath;
    }

    /**
     * Parse migration name to determine type and table (Laravel style)
     *
     * @param string $name
     * @return array [type, table]
     */
    private function parseMigrationName(string $name): array
    {
        // create_{table}_table
        if (preg_match('/^create_(.+)_table$/', $name, $matches)) {
            return ['create', $matches[1]];
        }
        // add_{column}_to_{table}_table
        if (preg_match('/^add_.*_to_(.+)_table$/', $name, $matches)) {
            return ['update', $matches[1]];
        }
        // remove_{column}_from_{table}_table
        if (preg_match('/^remove_.*_from_(.+)_table$/', $name, $matches)) {
            return ['update', $matches[1]];
        }
        // drop_{table}_table
        if (preg_match('/^drop_(.+)_table$/', $name, $matches)) {
            return ['update', $matches[1]];
        }
        // fallback: treat as update, try to find last _table
        if (preg_match('/(.+)_table$/', $name, $matches)) {
            return ['update', $matches[1]];
        }
        // fallback: treat as create, use name as table
        return ['create', $name];
    }

    /**
     * Load migration stub
     *
     * @param string $type
     * @return string
     */
    private function loadMigrationStub(string $type = 'create'): string
    {
        $stubs = [
            'create' => <<<'STUB'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('{{ table }}', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('{{ table }}');
    }
};
STUB,
            'update' => <<<'STUB'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('{{ table }}', function (Blueprint $table) {
            //
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('{{ table }}', function (Blueprint $table) {
            //
        });
    }
};
STUB,
        ];

        return $stubs[$type] ?? $stubs['create'];
    }

    /**
     * Convert name to class name
     *
     * @param string $name
     * @return string
     */
    private function getClassName(string $name): string
    {
        $parts = explode('_', str_replace('-', '_', strtolower($name)));
        return implode('', array_map('ucfirst', $parts));
    }
}
