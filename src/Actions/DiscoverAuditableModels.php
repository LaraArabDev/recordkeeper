<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Actions;

use Illuminate\Support\Facades\File;
use LaraArabDev\Recordkeeper\Concerns\AuditsChanges;
use LaraArabDev\Recordkeeper\Support\AttributeResolver;

/**
 * Discover all auditable models in configured paths and resolve their audit configuration.
 */
final class DiscoverAuditableModels
{
    /**
     * Scan paths for models using AuditsChanges and return their resolved config.
     *
     * @param  list<string>  $paths  Relative paths (from base_path) to scan for model files.
     * @return list<array{model: string, events: string, redact: string, encrypt: string, retention: string}> The resolved audit configuration for each discovered model.
     */
    public function __invoke(array $paths): array
    {
        $rows = [];

        foreach ($this->discoverModels($paths) as $class) {
            $config = AttributeResolver::resolve($class);
            $rows[] = [
                'model' => class_basename($class),
                'events' => implode(',', $config->auditEvents),
                'redact' => implode(',', array_keys(array_filter(
                    $config->attributeModifiers,
                    fn ($m) => str_contains($m, 'Redact')
                ))),
                'encrypt' => implode(',', array_keys(array_filter(
                    $config->attributeModifiers,
                    fn ($m) => str_contains($m, 'Encrypt')
                ))),
                'retention' => $config->retentionDays.'d',
            ];
        }

        return $rows;
    }

    /**
     * Discover model classes that use the AuditsChanges trait within the given paths.
     *
     * @param  list<string>  $paths  Relative paths (from base_path) to scan for PHP files.
     * @return list<class-string> Fully qualified class names of auditable models.
     */
    private function discoverModels(array $paths): array
    {
        if (config('recordkeeper.discovery.auto_modules', true)) {
            $paths = array_unique(array_merge($paths, $this->detectModulePaths()));
        }
        $models = [];

        foreach ($paths as $path) {
            $fullPath = base_path($path);
            if (! is_dir($fullPath)) {
                continue;
            }
            foreach (File::allFiles($fullPath) as $file) {
                $class = $this->fileToClass($file->getPathname());
                if ($class && $this->isAuditable($class)) {
                    $models[] = $class;
                }
            }
        }

        return $models;
    }

    /**
     * Auto-detect HMVC module model directories (nwidart/laravel-modules, coolsam/modules).
     *
     * @return list<string> Relative paths to module model directories.
     */
    private function detectModulePaths(): array
    {
        $paths = [];

        foreach (['Modules', 'modules'] as $modulesDir) {
            $dir = base_path($modulesDir);
            if (! is_dir($dir)) {
                continue;
            }

            foreach (File::directories($dir) as $moduleDir) {
                $moduleName = basename($moduleDir);

                foreach (['app/Models', 'src/Models', 'Models'] as $modelDir) {
                    $candidate = $modulesDir.'/'.$moduleName.'/'.$modelDir;
                    if (is_dir(base_path($candidate))) {
                        $paths[] = $candidate;
                    }
                }
            }
        }

        return $paths;
    }

    /**
     * Extract the fully qualified class name from a PHP file by parsing its namespace and class declarations.
     *
     * @param  string  $path  The absolute file path to the PHP file.
     * @return class-string|null The fully qualified class name, or null if it cannot be determined.
     */
    private function fileToClass(string $path): ?string
    {
        $content = file_get_contents($path);
        if ($content === false) {
            return null;
        }
        if (preg_match('/^namespace\s+(.+?);/m', $content, $ns)
            && preg_match('/^class\s+(\w+)/m', $content, $cls)) {
            return $ns[1].'\\'.$cls[1];
        }

        return null;
    }

    /**
     * Determine whether the given class uses the AuditsChanges trait.
     *
     * @param  string  $class  The fully qualified class name to check.
     * @return bool True if the class uses AuditsChanges.
     */
    private function isAuditable(string $class): bool
    {
        if (! class_exists($class)) {
            return false;
        }

        return in_array(AuditsChanges::class, class_uses_recursive($class), true);
    }
}
