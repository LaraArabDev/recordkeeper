<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use LaraArabDev\Recordkeeper\Actions\RecordAudit;
use LaraArabDev\Recordkeeper\DataObjects\AuditPayload;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Support\AuditDriverManager;

/**
 * Queued job to persist an audit entry asynchronously.
 *
 * Dispatched by {@see RecordAudit}
 * when `recordkeeper.queue.enabled` is true.
 */
final class WriteAudit implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public function __construct(private readonly array $payload) {}

    /**
     * Persist the audit payload via the configured storage driver.
     */
    public function handle(AuditDriverManager $manager): void
    {
        $manager->driver()->persist(AuditPayload::fromArray($this->payload));
    }
}
