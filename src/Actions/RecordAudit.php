<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Actions;

use LaraArabDev\Recordkeeper\DataObjects\AuditPayload;
use LaraArabDev\Recordkeeper\Jobs\WriteAudit;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Support\AuditDriverManager;

/**
 * Persist an audit payload via the configured storage driver.
 *
 * When `recordkeeper.queue.enabled` is true, the write is dispatched
 * to a background job on the configured queue, adding minimal
 * synchronous overhead.
 */
final class RecordAudit
{
    public function __construct(private readonly AuditDriverManager $manager) {}

    /**
     * Persist an audit payload synchronously or dispatch to queue.
     */
    public function __invoke(AuditPayload $payload): Audit
    {
        if (config('recordkeeper.queue.enabled', false)) {
            return $this->dispatchAsync($payload);
        }

        return $this->manager->driver()->persist($payload);
    }

    /**
     * Dispatch the audit write to a background queue job.
     */
    private function dispatchAsync(AuditPayload $payload): Audit
    {
        $connection = config('recordkeeper.queue.connection');
        $queue = config('recordkeeper.queue.queue', 'audits');

        dispatch(new WriteAudit($payload->toArray()))
            ->onConnection($connection)
            ->onQueue($queue);

        // Return a non-persisted Audit instance since the actual write happens async.
        $audit = new Audit;
        $audit->forceFill($payload->toArray());

        return $audit;
    }
}
