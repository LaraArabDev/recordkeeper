<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use LaraArabDev\Recordkeeper\Listeners\RecordOutboundHttp;
use LaraArabDev\Recordkeeper\Models\AuditHttpRequest;

/**
 * Queued job to persist an outbound HTTP request record asynchronously.
 *
 * Dispatched by {@see RecordOutboundHttp}
 * when async HTTP tracking is enabled.
 */
final class WriteHttpRequest implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public function __construct(private readonly array $data) {}

    public function handle(): void
    {
        AuditHttpRequest::create($this->data);
    }
}
