<?php

namespace Albaraa\Aztec\Generators;

use Illuminate\Support\Str;

class RoutesGenerator extends Generator
{
    public function generate(bool|callable $force = false): string
    {
        $path = $this->moduleRootPath('routes/web.php');

        // Check if file exists, if not initialize it
        if (!file_exists($path)) {
            $initialContent = "<?php\n\nuse Illuminate\Support\Facades\Route;";
            // Just use file_put_contents to create it initially? Or let writeFile handle it.
            // But we need to READ it first to append.
            if (!is_dir(dirname($path))) mkdir(dirname($path), 0755, true);
            file_put_contents($path, $initialContent);
        }

        $originalContent = file_get_contents($path);
        $content = $originalContent;
        
        $moduleNamespace = $this->getCommonReplacements()['{{ moduleNamespace }}'];
        $controllerClass = $this->spec->className . 'Controller';
        $controllerFQCN = $moduleNamespace . '\\Http\\Controllers\\' . $controllerClass;

        // Add Import if not exists
        if (!str_contains($content, "use {$controllerFQCN};")) {
            // Find insertion point - after the last use statement
            if (preg_match_all('/^use\s.*;/m', $content, $matches, PREG_OFFSET_CAPTURE)) {
                $lastMatch = end($matches[0]);
                $pos = $lastMatch[1] + strlen($lastMatch[0]);
                $content = substr_replace($content, "\nuse {$controllerFQCN};", $pos, 0);
            } else {
                // If no use statements, append after <?php but before logic (or namespace)
                // Assuming simple file structure for now
                if (str_starts_with($content, "<?php")) {
                    $content = preg_replace('/<\?php\s*/', "<?php\n\nuse {$controllerFQCN};\n", $content, 1);
                } else {
                    $content = "<?php\n\nuse {$controllerFQCN};\n" . $content;
                }
            }
        }

        // Check if routes already exist
        if (str_contains($content, "controller({$controllerClass}::class)")) {
             // If content changed (import added) but route exists, save it.
             if ($content !== $originalContent) {
                 return $this->writeFile($path, $content, true);
             }
             return $path;
        }

        // Prepare Route Block
        $moduleKebab = Str::slug($this->spec->moduleName);
        $modelKebab = Str::slug($this->spec->className);

        // Calculate route params
        $param = 'id'; // Default if no key specified, usually {id} or model binding {model}
        // User said "The destroy, will expect the id from the controller".
        // Controller show($id).
        // So {id} is correct.
        
        $routes = <<<PHP


Route::prefix('{$moduleKebab}')->group(function () {
    Route::controller({$controllerClass}::class)->group(function () {
        Route::get('/', 'index')->name('{$moduleKebab}.{$modelKebab}.index');
        Route::post('/', 'store')->name('{$moduleKebab}.{$modelKebab}.store');
        Route::get('/{{$param}}', 'show')->name('{$moduleKebab}.{$modelKebab}.show');
        Route::put('/{{$param}}', 'update')->name('{$moduleKebab}.{$modelKebab}.update');
        Route::delete('/{{$param}}', 'destroy')->name('{$moduleKebab}.{$modelKebab}.destroy');
    });
});
PHP;

        $content .= $routes;

        // Use parent writeFile but allow overwrite since we're appending logically
        // Actually writeFile overwrites completely. So we pass the full constructed content.
        return $this->writeFile($path, $content, true);
    }
}
