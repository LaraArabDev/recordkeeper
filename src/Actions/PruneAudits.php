<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Actions;

use Illuminate\Support\Carbon;
use LaraArabDev\Recordkeeper\Models\Audit;

/**
 * Delete audit records older than a given number of days.
 *
 * Uses chunked deletion to avoid memory exhaustion on large tables.
 */
final class PruneAudits
{
    /** @return int Number of deleted (or deletable, if dry-run) audit records. */
    public function __invoke(int $days, bool $dryRun = false): int
    {
        $cutoff = Carbon::now()->subDays($days);
        $query = Audit::where('created_at', '<', $cutoff);

        if ($dryRun) {
            return $query->count();
        }

        $deleted = 0;
        $query->chunkById(200, function ($audits) use (&$deleted): void {
            $ids = $audits->pluck('id')->all();
            $deleted += count($ids);
            Audit::whereIn('id', $ids)->delete();
        });

        return $deleted;
    }
}
