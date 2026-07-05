<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Actions;

use Illuminate\Database\Eloquent\Collection;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Support\AuditQuery;
use LaraArabDev\Recordkeeper\Support\Rollback;

/**
 * Find and revert audits by ID, batch, or filtered query.
 */
final class RollbackAudits
{
    public function __construct(
        private readonly Rollback $rollback,
    ) {}

    /**
     * Find a single rollbackable audit by ID.
     */
    public function findById(int|string $id): ?Audit
    {
        return Audit::find($id);
    }

    /**
     * Get rollbackable audits in a batch.
     *
     * @return Collection<int, Audit>
     */
    public function findByBatch(string $batchId): Collection
    {
        return Audit::where('batch_id', $batchId)
            ->whereIn('event', ['created', 'updated', 'deleted', 'restored'])
            ->get();
    }

    /**
     * Get rollbackable audits matching a query.
     *
     * @return Collection<int, Audit>
     */
    public function findByQuery(AuditQuery $query): Collection
    {
        return $query->rollbackable()->latest()->builder()->get();
    }

    /**
     * Preview a single audit rollback (dry-run).
     */
    public function preview(Audit $audit): array
    {
        return $this->rollback->revert($audit, true);
    }

    /**
     * Revert a single audit synchronously.
     */
    public function revert(Audit $audit): void
    {
        $this->rollback->revert($audit, false);
    }

    /**
     * Dispatch a single audit revert to the queue.
     */
    public function revertAsync(Audit $audit): void
    {
        $this->rollback->revertAsync($audit);
    }

    /**
     * Revert a collection synchronously.
     *
     * @return array<int, array>
     */
    public function revertCollection(Collection $audits): array
    {
        return $this->rollback->revertCollection($audits);
    }

    /**
     * Dispatch a collection revert to the queue.
     *
     * @param  Collection<int, Audit>  $audits
     */
    public function revertCollectionAsync(Collection $audits): void
    {
        $this->rollback->revertCollectionAsync($audits);
    }

    /**
     * Revert an entire batch (dry-run or real).
     */
    public function revertBatch(string $batchId, bool $dryRun = false): array
    {
        return $this->rollback->revertBatch($batchId, $dryRun);
    }
}
