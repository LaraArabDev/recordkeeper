<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Actions;

use Illuminate\Database\Eloquent\Collection;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Support\AuditQuery;
use LaraArabDev\Recordkeeper\Support\Rollback;

/**
 * Fetch and revert the last N rollbackable audits.
 */
final class UndoChanges
{
    public function __construct(
        private readonly Rollback $rollback,
    ) {}

    /**
     * Find the last N rollbackable audits, optionally scoped to a model.
     *
     * @return Collection<int, Audit>
     */
    public function findUndoable(int $n, ?string $model = null): Collection
    {
        $query = (new AuditQuery)->rollbackable()->latest()->limit($n);

        if ($model) {
            $query->model($model);
        }

        return $query->builder()->get();
    }

    /**
     * Revert the given audits synchronously.
     *
     * @return array<int, array>
     */
    public function revert(Collection $audits): array
    {
        return $this->rollback->revertCollection($audits);
    }

    /**
     * Dispatch the revert to the queue.
     *
     * @param  Collection<int, Audit>  $audits
     */
    public function revertAsync(Collection $audits): void
    {
        $this->rollback->revertCollectionAsync($audits);
    }
}
