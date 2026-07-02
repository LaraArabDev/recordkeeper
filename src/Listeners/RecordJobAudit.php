<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Listeners;

use Illuminate\Events\Dispatcher;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use LaraArabDev\Recordkeeper\Actions\RecordAudit;
use LaraArabDev\Recordkeeper\Attributes\AuditJob;
use LaraArabDev\Recordkeeper\DataObjects\AuditPayload;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Support\HttpTracker;

/**
 * Subscribe to queue job lifecycle events and record audit entries.
 *
 * Tracks job queued, processing, processed, and failed events.
 * Respects the {@see AuditJob} attribute and global config for opt-in/opt-out.
 */
final class RecordJobAudit
{
    public function __construct(private readonly RecordAudit $recordAudit) {}

    /** @return array<string, string> */
    public function subscribe(Dispatcher $events): array
    {
        return [
            JobQueued::class => 'onQueued',
            JobProcessing::class => 'onProcessing',
            JobProcessed::class => 'onProcessed',
            JobFailed::class => 'onFailed',
        ];
    }

    public function onQueued(JobQueued $event): void
    {
        $jobClass = is_object($event->job) ? $event->job::class : (string) $event->job;
        $attr = $this->attribute($jobClass);

        if (! $this->shouldAudit($jobClass, $attr) || ($attr && ! $attr->queued)) {
            return;
        }

        $this->write('job.queued', [
            'job' => $jobClass,
            'connection' => $event->connectionName,
            'queue' => $event->queue ?? 'default',
        ], $attr !== null ? $attr->tags : []);
    }

    public function onProcessing(JobProcessing $event): void
    {
        $jobClass = $this->resolveJobClass($event->job);
        $attr = $this->attribute($jobClass);

        if (! $this->shouldAudit($jobClass, $attr) || ($attr && ! $attr->processing)) {
            return;
        }

        $audit = $this->write('job.processing', [
            'job' => $jobClass,
            'connection' => $event->connectionName,
            'queue' => $event->job->getQueue(),
            'attempts' => $event->job->attempts(),
        ], $attr !== null ? $attr->tags : []);

        if (config('recordkeeper.http.enabled', false)) {
            app(HttpTracker::class)->setContext($audit->id);
        }
    }

    public function onProcessed(JobProcessed $event): void
    {
        $jobClass = $this->resolveJobClass($event->job);
        $attr = $this->attribute($jobClass);

        if (config('recordkeeper.http.enabled', false)) {
            app(HttpTracker::class)->clearContext();
        }

        if (! $this->shouldAudit($jobClass, $attr) || ($attr && ! $attr->processed)) {
            return;
        }

        $this->write('job.processed', [
            'job' => $jobClass,
            'connection' => $event->connectionName,
            'queue' => $event->job->getQueue(),
            'attempts' => $event->job->attempts(),
        ], $attr !== null ? $attr->tags : []);
    }

    public function onFailed(JobFailed $event): void
    {
        $jobClass = $this->resolveJobClass($event->job);
        $attr = $this->attribute($jobClass);

        if (config('recordkeeper.http.enabled', false)) {
            app(HttpTracker::class)->clearContext();
        }

        if (! $this->shouldAudit($jobClass, $attr) || ($attr && ! $attr->failed)) {
            return;
        }

        $this->write('job.failed', [
            'job' => $jobClass,
            'connection' => $event->connectionName,
            'queue' => $event->job->getQueue(),
            'attempts' => $event->job->attempts(),
            'exception' => $event->exception->getMessage(),
        ], $attr !== null ? $attr->tags : []);
    }

    private function shouldAudit(string $jobClass, ?AuditJob $attr): bool
    {
        if (! config('recordkeeper.enabled', true)) {
            return false;
        }

        $excluded = config('recordkeeper.jobs.exclude', []);
        if (in_array($jobClass, $excluded, true)) {
            return false;
        }

        return config('recordkeeper.jobs.enabled', false) || $attr !== null;
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  list<string>  $tags
     */
    private function write(string $eventName, array $context, array $tags): Audit
    {
        return ($this->recordAudit)(new AuditPayload(
            event: $eventName,
            auditableType: 'job',
            auditableId: null,
            oldValues: [],
            newValues: [],
            tags: implode(',', $tags),
            context: $context,
        ));
    }

    private function resolveJobClass(mixed $job): string
    {
        $name = $job->getName();

        if (str_contains($name, '\\')) {
            return $name;
        }

        $payload = json_decode($job->getRawBody(), true);

        return $payload['displayName'] ?? $name;
    }

    private function attribute(string $jobClass): ?AuditJob
    {
        if (! class_exists($jobClass)) {
            return null;
        }

        $attrs = (new \ReflectionClass($jobClass))->getAttributes(AuditJob::class);

        return $attrs ? $attrs[0]->newInstance() : null;
    }
}
