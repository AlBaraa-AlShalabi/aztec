<?php
declare(strict_types=1);

namespace Albaraa\Aztec\Support;

use RuntimeException;

class ModuleLocator
{
    /**
     * Locate a module root directory by name.
     *
     * @param string $moduleName
     * @return string absolute path
     * @throws RuntimeException if module not found
     */
    public function locate(string $moduleName): string
    {
        // prefer Laravel config if available
        $base = null;
        if (function_exists('config')) {
            $base = config('aztec.modules_path', base_path('Modules'));
        }

        // fallback to environment: current working dir + /Modules
        if (empty($base)) {
            $base = getcwd() . DIRECTORY_SEPARATOR . 'Modules';
        }

        $candidates = [
            rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $moduleName,
            rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ucfirst($moduleName),
        ];

        foreach ($candidates as $candidate) {
            if (is_dir($candidate)) {
                return realpath($candidate) ?: $candidate;
            }
        }

        throw new RuntimeException(sprintf('Module "%s" not found under "%s". Checked: %s', $moduleName, $base, implode(', ', $candidates)));
    }
}
