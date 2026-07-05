<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Console;

use Illuminate\Console\Command;
use LaraArabDev\Recordkeeper\Actions\DiscoverAuditableModels;

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
    public function handle(DiscoverAuditableModels $discover): int
    {
        $paths = config('recordkeeper.discovery.paths', ['app/Models']);
        $rows = $discover($paths);

        if (empty($rows)) {
            $this->warn('No auditable models found in: '.implode(', ', $paths));

            return self::SUCCESS;
        }

        if ($this->option('json')) {
            $this->line(json_encode($rows, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->table(['Model', 'Events', 'Redact', 'Encrypt', 'Retention'], $rows);

        return self::SUCCESS;
    }
}
