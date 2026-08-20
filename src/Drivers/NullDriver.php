<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Drivers;

use LaraArabDev\Recordkeeper\Contracts\AuditDriver;
use LaraArabDev\Recordkeeper\DataObjects\AuditPayload;
use LaraArabDev\Recordkeeper\Models\Audit;

/**
 * Discard all audits without persisting.
 *
 * Useful for test environments where audit writes should be silently ignored.
 */
final class NullDriver implements AuditDriver
{
    /**
     * Create an in-memory audit without persisting.
     *
     * @param  AuditPayload  $payload  The audit data to use.
     * @return Audit The in-memory audit model instance (not persisted).
     */
    public function persist(AuditPayload $payload): Audit
    {
        $audit = new Audit;
        $audit->forceFill($payload->toArray());

        return $audit;
    }

    /**
     * Find an audit record by its ID (always returns null).
     *
     * @param  int|string  $id  The audit record identifier.
     * @return Audit|null Always returns null.
     */
    public function find(int|string $id): ?Audit
    {
        return null;
    }

    /**
     * Flush all audit records (no-op for null driver).
     */
    public function flush(): void {}
}
