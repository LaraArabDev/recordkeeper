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
    public function persist(AuditPayload $payload): Audit
    {
        $audit = new Audit;
        $audit->forceFill($payload->toArray());

        return $audit;
    }

    public function find(int|string $id): ?Audit
    {
        return null;
    }

    public function flush(): void {}
}
