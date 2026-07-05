<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Drivers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use LaraArabDev\Recordkeeper\Contracts\AuditDriver;
use LaraArabDev\Recordkeeper\DataObjects\AuditPayload;
use LaraArabDev\Recordkeeper\Models\Audit;

/**
 * Write audits to a Laravel log channel.
 *
 * Zero-overhead observability driver. Does not support find or rollback.
 */
final class LogDriver implements AuditDriver
{
    public function __construct(
        private readonly string $channel = 'stack',
        private readonly string $level = 'info',
    ) {}

    /**
     * Persist an audit payload to the configured log channel.
     */
    public function persist(AuditPayload $payload): Audit
    {
        $data = $payload->toArray();
        $id = (string) Str::uuid();

        Log::channel($this->channel)->{$this->level}(
            '[recordkeeper] '.$data['event'],
            $data,
        );

        $audit = new Audit;
        $audit->forceFill(array_merge($data, ['id' => $id]));

        return $audit;
    }

    /**
     * Find an audit record by its ID (not supported by log driver).
     */
    public function find(int|string $id): ?Audit
    {
        return null;
    }

    /**
     * Flush all audit records (no-op for log driver).
     */
    public function flush(): void {}
}
