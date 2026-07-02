<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Actions;

use LaraArabDev\Recordkeeper\DataObjects\AuditPayload;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Support\AuditDriverManager;

/**
 * Persist an audit payload via the configured storage driver.
 */
final class RecordAudit
{
    public function __construct(private readonly AuditDriverManager $manager) {}

    public function __invoke(AuditPayload $payload): Audit
    {
        return $this->manager->driver()->persist($payload);
    }
}
