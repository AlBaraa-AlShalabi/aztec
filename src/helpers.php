<?php

if (!function_exists('module_path')) {
    /**
     * Get the path to a module or a specific file within it.
     *
     * @param string $moduleName
     * @param string $path
     * @return string
     */
    function module_path(string $moduleName, string $path = ''): string
    {
        $modulePath = base_path('Modules' . DIRECTORY_SEPARATOR . $moduleName);
        
        return $path ? $modulePath . DIRECTORY_SEPARATOR . $path : $modulePath;
    }
}
