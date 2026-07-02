<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Feature;

use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use LaraArabDev\Recordkeeper\Attributes\AuditJob;
use LaraArabDev\Recordkeeper\Listeners\RecordJobAudit;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[AuditJob]
class AuditedJob
{
    public string $queue = 'default';
}

class NonAuditedJob {}

#[AuditJob(queued: false)]
class QueuedDisabledJob {}

#[AuditJob(processing: false)]
class ProcessingDisabledJob {}

#[AuditJob(processed: false)]
class ProcessedDisabledJob {}

#[AuditJob(failed: false)]
class FailedDisabledJob {}

#[AuditJob(tags: ['billing'])]
class TaggedJob {}

#[AuditJob(tags: ['billing', 'payments'])]
class MultiTaggedJob {}

/**
 * Feature tests for job lifecycle auditing via the #[AuditJob] attribute.
 */
#[Group('listeners')]
#[CoversClass(RecordJobAudit::class)]
final class JobAuditingTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('recordkeeper.jobs.enabled', false);
    }

    #[Test]
    public function job_with_attribute_is_audited_when_jobs_disabled(): void
    {
        $this->fireProcessed(AuditedJob::class);

        $audit = Audit::where('event', 'job.processed')->first();

        $this->assertNotNull($audit);
        $this->assertSame('job', $audit->auditable_type);
        $this->assertSame(AuditedJob::class, $audit->context['job']);
        $this->assertSame('sync', $audit->context['connection']);
        $this->assertSame('default', $audit->context['queue']);
        $this->assertSame(1, $audit->context['attempts']);
    }

    #[Test]
    public function job_without_attribute_is_not_audited_when_jobs_disabled(): void
    {
        $this->fireProcessed(NonAuditedJob::class);

        $this->assertSame(0, Audit::where('event', 'job.processed')->count());
    }

    #[Test]
    public function all_jobs_audited_when_jobs_enabled(): void
    {
        config(['recordkeeper.jobs.enabled' => true]);

        $this->fireProcessed(NonAuditedJob::class);

        $this->assertSame(1, Audit::where('event', 'job.processed')->count());
    }

    #[Test]
    public function excluded_job_is_not_audited(): void
    {
        config([
            'recordkeeper.jobs.enabled' => true,
            'recordkeeper.jobs.exclude' => [AuditedJob::class],
        ]);

        $this->fireProcessed(AuditedJob::class);

        $this->assertSame(0, Audit::where('event', 'job.processed')->count());
    }

    #[Test]
    public function job_queued_event_is_audited(): void
    {
        $job = new AuditedJob;

        event(new JobQueued('sync', 'default', 'queued-id', $job, [], null));

        $audit = Audit::where('event', 'job.queued')->first();

        $this->assertNotNull($audit);
        $this->assertSame(AuditedJob::class, $audit->context['job']);
        $this->assertSame('sync', $audit->context['connection']);
        $this->assertSame('default', $audit->context['queue']);
    }

    #[Test]
    public function job_queued_queue_defaults_to_default_when_null(): void
    {
        $job = new AuditedJob;

        event(new JobQueued('sync', null, 'queued-id', $job, [], null));

        $audit = Audit::where('event', 'job.queued')->first();

        $this->assertNotNull($audit);
        $this->assertSame('default', $audit->context['queue']);
    }

    #[Test]
    public function job_with_queued_false_is_not_audited_on_queue(): void
    {
        $job = new QueuedDisabledJob;

        event(new JobQueued('sync', 'default', 'queued-id', $job, [], null));

        $this->assertSame(0, Audit::where('event', 'job.queued')->count());
    }

    #[Test]
    public function job_with_processing_false_is_not_audited_on_processing(): void
    {
        $job = $this->mockQueueJob(ProcessingDisabledJob::class);

        event(new JobProcessing('sync', $job));

        $this->assertSame(0, Audit::where('event', 'job.processing')->count());
    }

    #[Test]
    public function job_with_processed_false_is_not_audited_on_complete(): void
    {
        $this->fireProcessed(ProcessedDisabledJob::class);

        $this->assertSame(0, Audit::where('event', 'job.processed')->count());
    }

    #[Test]
    public function job_with_failed_false_is_not_audited_on_failure(): void
    {
        config(['recordkeeper.jobs.enabled' => true]);

        $this->fireFailed(FailedDisabledJob::class, 'error');

        $this->assertSame(0, Audit::where('event', 'job.failed')->count());
    }

    #[Test]
    public function job_tags_stored_on_processed_event(): void
    {
        $this->fireProcessed(TaggedJob::class);

        $audit = Audit::where('event', 'job.processed')->first();

        $this->assertNotNull($audit);
        $this->assertSame('billing', $audit->tags);
    }

    #[Test]
    public function job_multi_tags_stored_as_comma_separated(): void
    {
        $this->fireProcessed(MultiTaggedJob::class);

        $audit = Audit::where('event', 'job.processed')->first();

        $this->assertNotNull($audit);
        $this->assertSame('billing,payments', $audit->tags);
    }

    #[Test]
    public function job_failed_context_includes_exception(): void
    {
        config(['recordkeeper.jobs.enabled' => true]);

        $this->fireFailed(NonAuditedJob::class, 'Something went wrong');

        $audit = Audit::where('event', 'job.failed')->first();

        $this->assertNotNull($audit);
        $this->assertSame('Something went wrong', $audit->context['exception']);
        $this->assertSame(NonAuditedJob::class, $audit->context['job']);
        $this->assertSame('sync', $audit->context['connection']);
        $this->assertSame('default', $audit->context['queue']);
        $this->assertSame(1, $audit->context['attempts']);
    }

    #[Test]
    public function model_scope_job_audits(): void
    {
        config(['recordkeeper.jobs.enabled' => true]);

        $this->fireProcessed(NonAuditedJob::class);
        Audit::create(['event' => 'updated', 'auditable_type' => 'system', 'old_values' => [], 'new_values' => []]);

        $this->assertSame(1, Audit::jobAudits()->count());
    }

    #[Test]
    public function job_queued_tags_stored(): void
    {
        $job = new TaggedJob;

        event(new JobQueued('sync', 'default', 'queued-id', $job, [], null));

        $audit = Audit::where('event', 'job.queued')->first();

        $this->assertNotNull($audit);
        $this->assertSame('billing', $audit->tags);
    }

    #[Test]
    public function job_processing_tags_stored(): void
    {
        $job = $this->mockQueueJob(TaggedJob::class);
        event(new JobProcessing('sync', $job));

        $audit = Audit::where('event', 'job.processing')->first();

        $this->assertNotNull($audit);
        $this->assertSame('billing', $audit->tags);
    }

    #[Test]
    public function job_failed_tags_stored(): void
    {
        $this->fireFailed(TaggedJob::class, 'error');

        $audit = Audit::where('event', 'job.failed')->first();

        $this->assertNotNull($audit);
        $this->assertSame('billing', $audit->tags);
    }

    #[Test]
    public function job_not_audited_when_kill_switch_disabled(): void
    {
        config(['recordkeeper.enabled' => false]);

        $this->fireProcessed(AuditedJob::class);

        $this->assertSame(0, Audit::where('event', 'job.processed')->count());
    }

    #[Test]
    public function job_processing_event_creates_audit(): void
    {
        $job = $this->mockQueueJob(AuditedJob::class);
        event(new JobProcessing('sync', $job));

        $audit = Audit::where('event', 'job.processing')->first();

        $this->assertNotNull($audit);
        $this->assertSame('job', $audit->auditable_type);
        $this->assertSame(AuditedJob::class, $audit->context['job']);
        $this->assertSame('sync', $audit->context['connection']);
    }

    #[Test]
    public function job_class_resolved_from_display_name_when_name_has_no_namespace(): void
    {
        $mock = $this->createMock(Job::class);
        $mock->method('getName')->willReturn('audited-job');
        $mock->method('getRawBody')->willReturn(json_encode(['displayName' => AuditedJob::class]));
        $mock->method('getQueue')->willReturn('default');
        $mock->method('attempts')->willReturn(1);

        event(new JobProcessed('sync', $mock));

        $audit = Audit::where('event', 'job.processed')->first();

        $this->assertNotNull($audit);
        $this->assertSame(AuditedJob::class, $audit->context['job']);
    }

    #[Test]
    public function job_class_falls_back_to_name_when_display_name_absent(): void
    {
        config(['recordkeeper.jobs.enabled' => true]);

        $mock = $this->createMock(Job::class);
        $mock->method('getName')->willReturn('plain-job');
        $mock->method('getRawBody')->willReturn(json_encode([]));
        $mock->method('getQueue')->willReturn('default');
        $mock->method('attempts')->willReturn(1);

        event(new JobProcessed('sync', $mock));

        $audit = Audit::where('event', 'job.processed')->first();

        $this->assertNotNull($audit);
        $this->assertSame('plain-job', $audit->context['job']);
    }

    private function fireProcessed(string $jobClass): void
    {
        $job = $this->mockQueueJob($jobClass);
        event(new JobProcessed('sync', $job));
    }

    private function fireFailed(string $jobClass, string $message): void
    {
        $job = $this->mockQueueJob($jobClass);
        event(new JobFailed('sync', $job, new \RuntimeException($message)));
    }

    private function mockQueueJob(string $jobClass): Job
    {
        $mock = $this->createMock(Job::class);
        $mock->method('getName')->willReturn($jobClass);
        $mock->method('getRawBody')->willReturn(json_encode(['displayName' => $jobClass]));
        $mock->method('getQueue')->willReturn('default');
        $mock->method('attempts')->willReturn(1);

        return $mock;
    }
}
