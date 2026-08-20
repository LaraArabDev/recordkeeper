<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Console;

use Illuminate\Console\Command;
use LaraArabDev\Recordkeeper\Actions\PruneAudits;
use LaraArabDev\Recordkeeper\Console\Concerns\ConfirmsAndExecutes;

/**
 * Delete audit records older than a specified number of days.
 */
class PruneCommand extends Command
{
    use ConfirmsAndExecutes;

    protected $signature = 'recordkeeper:prune
        {--days= : Delete audits older than this many days}
        {--dry-run : Show count without deleting}
        {--yes : Skip confirmation prompt}';

    protected $description = 'Prune old audit records';

    /**
     * Delete audit records older than the configured retention period.
     *
     * @param  PruneAudits  $pruner  The prune audits action.
     * @return int The command exit code.
     */
    public function handle(PruneAudits $pruner): int
    {
        $days = (int) ($this->option('days') ?: config('recordkeeper.retention.default_days', 365));
        $count = $pruner($days, true);

        if ($count === 0) {
            $this->info("No audit records older than {$days} days.");

            return self::SUCCESS;
        }

        $this->info("{$count} audit(s) older than {$days} days.");

        return $this->confirmAndExecute(
            confirmMessage: "Delete {$count} audit(s) older than {$days} days?",
            dryRunMessage: "Dry-run — {$count} record(s) would be deleted.",
            onSync: function () use ($pruner, $days): int {
                $deleted = $pruner($days, false);
                $this->info("Deleted {$deleted} audit record(s).");

                return self::SUCCESS;
            },
        );
    }
}
