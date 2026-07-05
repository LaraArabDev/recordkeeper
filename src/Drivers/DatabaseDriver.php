<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Drivers;

use LaraArabDev\Recordkeeper\Contracts\AuditDriver;
use LaraArabDev\Recordkeeper\DataObjects\AuditPayload;
use LaraArabDev\Recordkeeper\Models\Audit;

/**
 * Persist audits to the database via Eloquent.
 *
 * Default driver. Supports full query, rollback, and pruning capabilities.
 */
final class DatabaseDriver implements AuditDriver
{
    /**
     * Persist an audit payload to the database.
     */
    public function persist(AuditPayload $payload): Audit
    {
        $audit = new Audit;
        $audit->fill($payload->toArray());
        $audit->save();

        return $audit;
    }

    /**
     * Find an audit record by its ID.
     */
    public function find(int|string $id): ?Audit
    {
        return Audit::find($id);
    }

    /**
     * Delete all audit records from the database.
     */
    public function flush(): void
    {
        Audit::query()->forceDelete();
    }
}
