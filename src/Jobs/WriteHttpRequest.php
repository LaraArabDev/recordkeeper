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

    /**
     * Create a new WriteHttpRequest job instance.
     *
     * @param  array<string, mixed>  $data  The HTTP request data to persist.
     */
    public function __construct(private readonly array $data) {}

    /**
     * Persist the outbound HTTP request record to the database.
     */
    public function handle(): void
    {
        AuditHttpRequest::create($this->data);
    }
}
