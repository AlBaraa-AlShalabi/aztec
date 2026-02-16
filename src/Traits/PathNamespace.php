<?php

namespace Albaraa\Aztec\Traits;

trait PathNamespace
{
    /**
     * Get the path to the module.
     */
    public function getModulePath(): string
    {
        return module_path($this->name);
    }
}
