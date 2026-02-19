<?php

declare(strict_types=1);

namespace Albaraa\Aztec\Services;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;

class ModuleOrderService
{
    private string $configPath;
    private Filesystem $files;

    public function __construct(?Filesystem $files = null)
    {
        $this->files = $files ?? app(Filesystem::class);
        $this->configPath = config_path('aztec-modules-order.php');
    }

    /**
     * Get the ordered list of modules
     *
     * @return array
     */
    public function getOrder(): array
    {
        if (!$this->files->exists($this->configPath)) {
            return [];
        }

        try {
            $order = include $this->configPath;
            return is_array($order) ? $order : [];
        } catch (\Exception $e) {
            throw new RuntimeException('Failed to read module order configuration: ' . $e->getMessage());
        }
    }

    /**
     * Save the module order
     *
     * @param array $modules
     * @return void
     */
    public function saveOrder(array $modules): void
    {
        try {
            $content = '<?php

return ' . var_export($modules, true) . ';';
            $this->files->put($this->configPath, $content);
        } catch (\Exception $e) {
            throw new RuntimeException('Failed to save module order configuration: ' . $e->getMessage());
        }
    }

    /**
     * Check if an order is already set
     *
     * @return bool
     */
    public function hasOrder(): bool
    {
        return $this->files->exists($this->configPath) && !empty($this->getOrder());
    }

    /**
     * Clear the module order
     *
     * @return void
     */
    public function clearOrder(): void
    {
        if ($this->files->exists($this->configPath)) {
            $this->files->delete($this->configPath);
        }
    }

    /**
     * Get configuration path
     *
     * @return string
     */
    public function getConfigPath(): string
    {
        return $this->configPath;
    }
}
