<?php
declare(strict_types=1);

namespace Albaraa\Aztec\Inspectors;

use RuntimeException;

class ModelLocator
{
    /**
     * Common candidate locations inside a module to look for Model files.
     * Extend this list as needed.
     *
     * @var string[]
     */
    protected array $patterns = [
        'app/Models/%s.php',
        'Models/%s.php',
        'Entities/%s.php',
        '%s.php',
        'Domain/Models/%s.php',
    ];

    /**
     * Find the first existing model file in the module.
     *
     * @param string $modulePath Absolute module path
     * @param string $modelName Class base name, e.g. "Course"
     * @return string Absolute file path
     * @throws RuntimeException if none found
     */
    public function locate(string $modulePath, string $modelName): string
    {
        $modulePath = rtrim($modulePath, DIRECTORY_SEPARATOR);

        foreach ($this->patterns as $pattern) {
            $relative = sprintf($pattern, $modelName);
            $path = $modulePath . DIRECTORY_SEPARATOR . $relative;
            if (is_file($path)) {
                return realpath($path) ?: $path;
            }
        }

        $found = $this->caseInsensitiveSearch($modulePath, $modelName);
        if ($found !== null) {
            return $found;
        }

        throw new RuntimeException(sprintf('Model "%s" not found inside module "%s". Searched patterns: %s', $modelName, $modulePath, implode(', ', $this->patterns)));
    }

    /**
     * A conservative case-insensitive file search within the module for file that ends with /ModelName.php
     *
     * @param string $modulePath
     * @param string $modelName
     * @return string|null
     */
    protected function caseInsensitiveSearch(string $modulePath, string $modelName): ?string
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($modulePath));
        $needle = strtolower($modelName) . '.php';

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }
            if (strtolower($file->getFilename()) === $needle) {
                return $file->getRealPath();
            }
        }

        return null;
    }
}
