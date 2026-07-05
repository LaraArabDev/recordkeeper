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
use LaraArabDev\Recordkeeper\Concerns\AuditsJob;
use LaraArabDev\Recordkeeper\Concerns\ResolvesHttpFilterConfig;
use LaraArabDev\Recordkeeper\DataObjects\AuditPayload;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Support\HttpTracker;

/**
 * Subscribe to queue job lifecycle events and record audit entries.
 *
 * Tracks job queued, processing, processed, and failed events.
 * Respects the {@see AuditJob} attribute, {@see AuditsJob} trait, and global config for opt-in/opt-out.
 */
final class RecordJobAudit
{
    use ResolvesHttpFilterConfig;

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

    /**
     * Record an audit when a job is queued.
     */
    public function onQueued(JobQueued $event): void
    {
        $jobClass = is_object($event->job) ? $event->job::class : (string) $event->job;
        $attr = $this->attribute($jobClass);

        if (! $this->shouldAudit($jobClass, $attr)) {
            return;
        }

        if (! $this->resolveJobToggle($jobClass, $attr, 'queued')) {
            return;
        }

        $this->write('job.queued', [
            'job' => $jobClass,
            'connection' => $event->connectionName,
            'queue' => $event->queue ?? 'default',
        ], $this->resolveTags($jobClass, $attr));
    }

    /**
     * Record an audit when a job starts processing.
     */
    public function onProcessing(JobProcessing $event): void
    {
        $jobClass = $this->resolveJobClass($event->job);
        $attr = $this->attribute($jobClass);

        if (! $this->shouldAudit($jobClass, $attr)) {
            return;
        }

        if (! $this->resolveJobToggle($jobClass, $attr, 'processing')) {
            return;
        }

        $audit = $this->write('job.processing', [
            'job' => $jobClass,
            'connection' => $event->connectionName,
            'queue' => $event->job->getQueue(),
            'attempts' => $event->job->attempts(),
        ], $this->resolveTags($jobClass, $attr));

        if (config('recordkeeper.http.enabled', false)) {
            $filterConfig = $this->resolveHttpFilterConfig($jobClass);
            app(HttpTracker::class)->setContext($audit->id, $filterConfig);
        }
    }

    /**
     * Record an audit when a job finishes processing.
     */
    public function onProcessed(JobProcessed $event): void
    {
        $jobClass = $this->resolveJobClass($event->job);
        $attr = $this->attribute($jobClass);

        if (config('recordkeeper.http.enabled', false)) {
            app(HttpTracker::class)->clearContext();
        }

        if (! $this->shouldAudit($jobClass, $attr)) {
            return;
        }

        if (! $this->resolveJobToggle($jobClass, $attr, 'processed')) {
            return;
        }

        $this->write('job.processed', [
            'job' => $jobClass,
            'connection' => $event->connectionName,
            'queue' => $event->job->getQueue(),
            'attempts' => $event->job->attempts(),
        ], $this->resolveTags($jobClass, $attr));
    }

    /**
     * Record an audit when a job fails.
     */
    public function onFailed(JobFailed $event): void
    {
        $jobClass = $this->resolveJobClass($event->job);
        $attr = $this->attribute($jobClass);

        if (config('recordkeeper.http.enabled', false)) {
            app(HttpTracker::class)->clearContext();
        }

        if (! $this->shouldAudit($jobClass, $attr)) {
            return;
        }

        if (! $this->resolveJobToggle($jobClass, $attr, 'failed')) {
            return;
        }

        $this->write('job.failed', [
            'job' => $jobClass,
            'connection' => $event->connectionName,
            'queue' => $event->job->getQueue(),
            'attempts' => $event->job->attempts(),
            'exception' => $event->exception->getMessage(),
        ], $this->resolveTags($jobClass, $attr));
    }

    /**
     * Determine whether the given job class should be audited.
     *
     * @param  class-string  $jobClass
     */
    private function shouldAudit(string $jobClass, ?AuditJob $attr): bool
    {
        if (! config('recordkeeper.enabled', true)) {
            return false;
        }

        $excluded = config('recordkeeper.jobs.exclude', []);
        if (in_array($jobClass, $excluded, true)) {
            return false;
        }

        return config('recordkeeper.jobs.enabled', false) || $attr !== null || $this->usesTrait($jobClass);
    }

    /**
     * Resolve tags from attribute, trait, or empty default.
     *
     * Priority: attribute > trait > empty.
     *
     * @return list<string>
     */
    private function resolveTags(string $jobClass, ?AuditJob $attr): array
    {
        if ($attr !== null) {
            return $attr->tags;
        }

        if ($this->usesTrait($jobClass)) {
            $instance = (new \ReflectionClass($jobClass))->newInstanceWithoutConstructor();

            return $instance->auditJobTags();
        }

        return [];
    }

    /**
     * Resolve a per-lifecycle-event toggle from attribute, trait, or default true.
     *
     * Priority: attribute > trait > true.
     */
    private function resolveJobToggle(string $jobClass, ?AuditJob $attr, string $event): bool
    {
        if ($attr !== null) {
            return match ($event) {
                'queued' => $attr->queued,
                'processing' => $attr->processing,
                'processed' => $attr->processed,
                'failed' => $attr->failed,
                default => true,
            };
        }

        if ($this->usesTrait($jobClass)) {
            $instance = (new \ReflectionClass($jobClass))->newInstanceWithoutConstructor();

            return match ($event) {
                'queued' => $instance->shouldAuditQueued(),
                'processing' => $instance->shouldAuditProcessing(),
                'processed' => $instance->shouldAuditProcessed(),
                'failed' => $instance->shouldAuditFailed(),
                default => true,
            };
        }

        return true;
    }

    /**
     * Check whether the given class uses the AuditsJob trait.
     *
     * @param  class-string  $jobClass
     */
    private function usesTrait(string $jobClass): bool
    {
        if (! class_exists($jobClass)) {
            return false;
        }

        return in_array(AuditsJob::class, class_uses_recursive($jobClass), true);
    }

    /**
     * Write an audit record for a job lifecycle event.
     *
     * @param  string  $eventName  The audit event name (e.g. 'job.queued').
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
            source: $context['job'] ?? null,
        ));
    }

    /**
     * Resolve the fully qualified class name from a queue job payload.
     *
     * @return class-string
     */
    private function resolveJobClass(mixed $job): string
    {
        $name = $job->getName();

        if (str_contains($name, '\\')) {
            return $name;
        }

        $payload = json_decode($job->getRawBody(), true);

        return $payload['displayName'] ?? $name;
    }

    /**
     * Retrieve the #[AuditJob] attribute instance from a job class, if present.
     *
     * @param  class-string  $jobClass
     */
    private function attribute(string $jobClass): ?AuditJob
    {
        if (! class_exists($jobClass)) {
            return null;
        }

        $attrs = (new \ReflectionClass($jobClass))->getAttributes(AuditJob::class);

        return $attrs ? $attrs[0]->newInstance() : null;
    }
}
