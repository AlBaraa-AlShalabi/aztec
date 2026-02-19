<?php

declare(strict_types=1);

namespace Albaraa\Aztec\Services;

use Albaraa\Aztec\Support\ModuleLocator;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;

class SeederService
{
    private ModuleLocator $moduleLocator;
    private Filesystem $files;

    public function __construct(?ModuleLocator $moduleLocator = null, ?Filesystem $files = null)
    {
        $this->moduleLocator = $moduleLocator ?? new ModuleLocator();
        $this->files = $files ?? app(Filesystem::class);
    }

    /**
     * Get all available modules with seeders
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
            if (is_dir($itemPath) && $item !== '.' && $item !== '..' && is_dir($itemPath . '/database/seeders')) {
                $modules[] = $item;
            }
        }

        return sort($modules) ? $modules : [];
    }

    /**
     * Get seeders for a specific module
     *
     * @param string $module
     * @return array
     */
    public function getSeedersForModule(string $module): array
    {
        try {
            $modulePath = $this->moduleLocator->locate($module);
            $seedersPath = $modulePath . '/database/seeders';

            if (!is_dir($seedersPath)) {
                return [];
            }

            $seeders = [];
            foreach (scandir($seedersPath) as $file) {
                if (substr($file, -4) === '.php' && $file !== '.' && $file !== '..') {
                    $seeders[] = $file;
                }
            }

            return $seeders;
        } catch (RuntimeException $e) {
            throw new RuntimeException("Module '{$module}' not found: " . $e->getMessage());
        }
    }

    /**
     * Get the seeder directory path for a module
     *
     * @param string $module
     * @return string
     */
    public function getSeedersPath(string $module): string
    {
        try {
            $modulePath = $this->moduleLocator->locate($module);
            return $modulePath . '/database/seeders';
        } catch (RuntimeException $e) {
            throw new RuntimeException("Module '{$module}' not found: " . $e->getMessage());
        }
    }

    /**
     * Check if a seeder file exists in a module
     *
     * @param string $module
     * @param string $seederName
     * @return bool
     */
    public function seederExists(string $module, string $seederName): bool
    {
        $seedersPath = $this->getSeedersPath($module);
        $filePath = $seedersPath . '/' . $seederName . '.php';

        return $this->files->exists($filePath);
    }

    /**
     * Create a seeder file for a module
     *
     * @param string $module
     * @param string $name
     * @return string
     */
    public function createSeeder(string $module, string $name): string
    {
        $seedersPath = $this->getSeedersPath($module);
        $class = $this->getClassName($name);
        $filename = $class . '.php';
        $filepath = $seedersPath . DIRECTORY_SEPARATOR . $filename;

        $stub = $this->loadSeederStub();
        
        $content = str_replace(
            ['{{ class }}', '{{ namespace }}'],
            [$class, $this->getSeederNamespace($module)],
            $stub
        );

        if (!$this->files->isDirectory($seedersPath)) {
            $this->files->makeDirectory($seedersPath, 0755, true);
        }

        $this->files->put($filepath, $content);

        return $filepath;
    }

    /**
     * Load seeder stub
     *
     * @return string
     */
    private function loadSeederStub(): string
    {
        return <<<'STUB'
<?php

namespace {{ namespace }};

use Illuminate\Database\Seeder;

class {{ class }} extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Add your seeding logic here
    }
}
STUB;
    }

    /**
     * Get seeder namespace for a module
     *
     * @param string $module
     * @return string
     */
    private function getSeederNamespace(string $module): string
    {
        return 'Modules\\' . ucfirst($module) . '\\Database\\Seeders';
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
