<?php

namespace Albaraa\Aztec\Generators;

class RepositoryGenerator extends Generator
{
    public function generate(bool|callable $force = false): array
    {
        $paths = [];

        $paths[] = $this->generateInterface($force);
        $paths[] = $this->generateRepository($force);

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
}
