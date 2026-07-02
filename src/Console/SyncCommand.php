<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use LaraArabDev\Recordkeeper\Concerns\AuditsChanges;
use LaraArabDev\Recordkeeper\Support\AttributeResolver;

/**
 * Discover and list all auditable models with their resolved audit configuration.
 */
class SyncCommand extends Command
{
    protected $signature = 'recordkeeper:models {--json : Output as JSON}';

    protected $description = 'List auditable models with their resolved configuration';

    /**
     * Discover auditable models and display their resolved configuration.
     */
    public function handle(): int
    {
        $paths = config('recordkeeper.discovery.paths', ['app/Models']);
        $models = $this->discoverModels($paths);

        if (empty($models)) {
            $this->warn('No auditable models found in: '.implode(', ', $paths));

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($models as $class) {
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

        if ($this->option('json')) {
            $this->line(json_encode($rows, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->table(['Model', 'Events', 'Redact', 'Encrypt', 'Retention'], $rows);

        return self::SUCCESS;
    }

    /**
     * Scan configured paths for models using the AuditsChanges trait.
     *
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
     * Extract the fully-qualified class name from a PHP file.
     *
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

    /**
     * Determine if a class uses the AuditsChanges trait.
     */
    private function isAuditable(string $class): bool
    {
        if (! class_exists($class)) {
            return false;
        }

        return in_array(AuditsChanges::class, class_uses_recursive($class), true);
    }
}
