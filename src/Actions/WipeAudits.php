<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Actions;

use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Support\AuditQuery;

/**
 * Bulk delete audit records in chunks to avoid memory exhaustion.
 */
final class WipeAudits
{
    /**
     * Delete all audits matching the given query in chunks.
     *
     * @return int Number of deleted records.
     */
    public function __invoke(AuditQuery $query): int
    {
        $deleted = 0;
        $chunkSize = (int) config('recordkeeper.drivers.database.chunk_size', 500);

        do {
            $ids = (clone $query)->limit($chunkSize)->builder()->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            Audit::whereIn('id', $ids)->forceDelete();
            $deleted += $ids->count();
        } while ($ids->count() === $chunkSize);

        return $deleted;
    }
}
