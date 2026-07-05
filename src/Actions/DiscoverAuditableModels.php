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
     * @param  list<string>  $paths
     * @return list<array{model: string, events: string, redact: string, encrypt: string, retention: string}>
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
     * @param  list<string>  $paths
     * @return list<class-string>
     */
    private function discoverModels(array $paths): array
    {
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
     * @return class-string|null
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

    private function isAuditable(string $class): bool
    {
        if (! class_exists($class)) {
            return false;
        }

        return in_array(AuditsChanges::class, class_uses_recursive($class), true);
    }
}
