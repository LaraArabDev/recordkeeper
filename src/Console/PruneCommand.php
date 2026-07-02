<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Console;

use Illuminate\Console\Command;
use LaraArabDev\Recordkeeper\Actions\PruneAudits;

/**
 * Delete audit records older than a specified number of days.
 */
class PruneCommand extends Command
{
    protected $signature = 'recordkeeper:prune
        {--days= : Delete audits older than this many days}
        {--dry-run : Show count without deleting}
        {--yes : Skip confirmation prompt}';

    protected $description = 'Prune old audit records';

    public function __construct(
        private readonly PruneAudits $pruneAudits,
    ) {
        parent::__construct();
    }

    /**
     * Delete audit records older than the configured or specified retention period.
     */
    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('recordkeeper.retention.default_days', 365));
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $count = ($this->pruneAudits)($days, true);
            $this->info("{$count} audit record(s) would be deleted (older than {$days} days).");

            return self::SUCCESS;
        }

        if (! $this->option('yes') && ! $this->confirm("Delete audit records older than {$days} days?")) {
            $this->line('Aborted.');

            return self::SUCCESS;
        }

        $deleted = ($this->pruneAudits)($days, false);
        $this->info("Deleted {$deleted} audit record(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
