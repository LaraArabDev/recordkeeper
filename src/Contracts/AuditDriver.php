<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Contracts;

use LaraArabDev\Recordkeeper\DataObjects\AuditPayload;
use LaraArabDev\Recordkeeper\Drivers\DatabaseDriver;
use LaraArabDev\Recordkeeper\Drivers\LogDriver;
use LaraArabDev\Recordkeeper\Drivers\NullDriver;
use LaraArabDev\Recordkeeper\Drivers\RedisDriver;
use LaraArabDev\Recordkeeper\Models\Audit;

/**
 * Contract for audit persistence backends.
 *
 * @see DatabaseDriver
 * @see RedisDriver
 * @see LogDriver
 * @see NullDriver
 */
interface AuditDriver
{
    /** Persist an audit payload and return the resulting Audit model. */
    public function persist(AuditPayload $payload): Audit;

    /** Find an audit by its primary key, or return null if not found. */
    public function find(int|string $id): ?Audit;

    /** Delete all audit records managed by this driver. */
    public function flush(): void;
}
