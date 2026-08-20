<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Console;

use Illuminate\Console\Command;
use LaraArabDev\Recordkeeper\Actions\WipeAudits;
use LaraArabDev\Recordkeeper\Console\Concerns\BuildsAuditFilters;
use LaraArabDev\Recordkeeper\Console\Concerns\ConfirmsAndExecutes;

/**
 * Bulk delete audit records matching filters.
 */
class WipeCommand extends Command
{
    use BuildsAuditFilters;
    use ConfirmsAndExecutes;

    protected $signature = 'recordkeeper:wipe
        {--model= : Filter by model class name}
        {--model-id= : Filter by model instance ID}
        {--tag= : Filter by tag}
        {--event=* : Filter by event type}
        {--since= : From date}
        {--until= : Until date}
        {--batch= : Filter by batch ID}
        {--dry-run : Preview count without deleting}
        {--yes : Skip confirmation prompt}';

    protected $description = 'Bulk delete audit records matching filters';

    /**
     * Count matching audits, confirm with the user, then delete in chunks.
     *
     * @param  WipeAudits  $wiper  The wipe audits action.
     * @return int The command exit code.
     */
    public function handle(WipeAudits $wiper): int
    {
        if (! $this->hasAnyFilter()) {
            $this->error('At least one filter is required to prevent accidental wipe-all.');

            return self::FAILURE;
        }

        $query = $this->buildAuditQuery();
        $count = $query->builder()->count();

        if ($count === 0) {
            $this->warn('No audit records match the given filters.');

            return self::SUCCESS;
        }

        $this->info("{$count} audit(s) match the given filters.");

        return $this->confirmAndExecute(
            confirmMessage: "Permanently delete {$count} audit(s)?",
            dryRunMessage: 'Dry-run — no records deleted.',
            onSync: function () use ($wiper): int {
                $deleted = $wiper($this->buildAuditQuery());
                $this->info("Deleted {$deleted} audit(s).");

                return self::SUCCESS;
            },
        );
    }
}
