<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Actions;

use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Support\AuditQuery;

/**
 * Find the most recent deletion audit for a model instance.
 */
final class RestoreModel
{
    /**
     * Find the latest deletion audit for the given model and ID.
     */
    public function findDeletionAudit(string $model, string $id): ?Audit
    {
        return (new AuditQuery)
            ->model($model)
            ->subjectId($id)
            ->event(['deleted', 'forceDeleted'])
            ->latest()
            ->limit(1)
            ->builder()
            ->first();
    }
}
