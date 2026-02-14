<?php

namespace Albaraa\Aztec\Generators;

class RepositoryGenerator extends Generator
{
    public function generate(bool|callable $force = false): array
    {
        $paths = [];

        $paths[] = $this->generateInterface($force);
        $paths[] = $this->generateRepository($force);

        $this->bindRepository();

        return $paths;
    }

    protected function generateInterface(bool|callable $force = false): string
    {
        $stub = $this->loadStub('repository-interface.stub');

        $replacements = array_merge($this->getCommonReplacements(), [
            '{{ repositoryNamespace }}'   => $this->getRepositoryNamespace(),
            '{{ interfaceClass }}'        => $this->spec->className . 'RepositoryInterface',
        ]);

        $content = $this->replacePlaceholders($stub, $replacements);
        $path = $this->modulePath('Repositories/Interfaces/' . $this->spec->className . 'RepositoryInterface.php');

        return $this->writeFile($path, $content, $force);
    }

    protected function generateRepository(bool|callable $force = false): string
    {
        $stub = $this->loadStub('repository.stub');

        $replacements = array_merge($this->getCommonReplacements(), [
            '{{ repositoryNamespace }}'   => $this->getRepositoryNamespace(),
            '{{ interfaceClass }}'        => $this->spec->className . 'RepositoryInterface',
            '{{ repositoryClass }}'       => 'Eloquent' . $this->spec->className . 'Repository',
        ]);

        $content = $this->replacePlaceholders($stub, $replacements);
        $path = $this->modulePath('Repositories/Eloquent' . $this->spec->className . 'Repository.php');

        return $this->writeFile($path, $content, $force);
    }

    protected function getRepositoryNamespace(): string
    {
        return $this->getCommonReplacements()['{{ moduleNamespace }}'] . '\\Repositories';
    }

    protected function bindRepository(): void
    {
        $providerName = $this->spec->moduleName . 'ServiceProvider';
        $providerPath = $this->modulePath("Providers/{$providerName}.php");

        if (!$this->files->exists($providerPath)) {
            // Fallback for different structures or if file missing
             $this->files->exists($providerPath) ? $providerPath : null; 
             // Try without 'app' if modulePath added it?
             // But modulePath logic is robust.
             return;
        }

        $content = $this->files->get($providerPath);
        
        $interfaceName = $this->spec->className . 'RepositoryInterface';
        $repoName = 'Eloquent' . $this->spec->className . 'Repository';
        
        $interfaceNamespace = $this->getCommonReplacements()['{{ moduleNamespace }}'] . '\\Repositories\\Interfaces\\' . $interfaceName;
        $repoNamespace = $this->getRepositoryNamespace() . '\\' . $repoName;

        // Add Imports
        foreach ([$interfaceNamespace, $repoNamespace] as $ns) {
            if (!str_contains($content, "use {$ns};")) {
                if (preg_match('/^use\s.*;/m', $content)) {
                    $pos = strrpos($content, 'use ');
                    $pos = strpos($content, ';', $pos) + 1;
                    $content = substr_replace($content, "\nuse {$ns};", $pos, 0);
                } else {
                    $content = str_replace("<?php", "<?php\n\nuse {$ns};", $content);
                }
            }
        }

        // Add Binding
        // Check if already bound
        if (str_contains($content, $interfaceName . '::class')) {
            return;
        }

        $binding = "\n        \$this->app->bind(\n" .
                   "            {$interfaceName}::class,\n" .
                   "            {$repoName}::class\n" .
                   "        );\n";

        // Find register method
        if (preg_match('/public\s+function\s+register\s*\([^)]*\)\s*(?::\s*void)?\s*\{/', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $start = $matches[0][1];
            $length = strlen($matches[0][0]);
            $bodyStart = $start + $length;
            
            // Simple brace counting to find the closing brace of the method
            $openBraces = 1;
            $pos = $bodyStart;
            $len = strlen($content);
            $foundEnd = false;
            
            while ($pos < $len) {
                $char = $content[$pos];
                if ($char === '{') {
                    $openBraces++;
                } elseif ($char === '}') {
                    $openBraces--;
                }
                
                if ($openBraces === 0) {
                    $foundEnd = true;
                    // Insert before this closing brace
                    $content = substr_replace($content, $binding . "    ", $pos, 0);
                    break;
                }
                $pos++;
            }
            
            if ($foundEnd) {
                $this->writeFile($providerPath, $content, true);
            }
        }
    }
}
