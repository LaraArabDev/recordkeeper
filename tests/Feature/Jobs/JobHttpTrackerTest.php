<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Feature;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use LaraArabDev\Recordkeeper\Attributes\AuditJob;
use LaraArabDev\Recordkeeper\Listeners\RecordJobAudit;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Models\AuditHttpRequest;
use LaraArabDev\Recordkeeper\Support\HttpTracker;
use LaraArabDev\Recordkeeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[AuditJob]
class TrackedJob {}

class UntrackedJob {}

/**
 * Feature tests for HTTP request tracking within audited job execution.
 */
#[Group('jobs')]
#[CoversClass(HttpTracker::class)]
#[CoversClass(RecordJobAudit::class)]
final class JobHttpTrackerTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('recordkeeper.http.enabled', true);
        $app['config']->set('recordkeeper.jobs.enabled', true);
    }

    protected function setUp(): void
    {
        parent::setUp();
        app(HttpTracker::class)->clearContext();
    }

    #[Test]
    public function tracker_context_set_to_processing_audit_id(): void
    {
        $job = $this->mockJob(TrackedJob::class);
        event(new JobProcessing('sync', $job));

        $processingAudit = Audit::where('event', 'job.processing')->first();

        $this->assertNotNull($processingAudit);
        $this->assertSame($processingAudit->id, app(HttpTracker::class)->currentAuditId());
    }

    #[Test]
    public function tracker_context_cleared_after_job_processed(): void
    {
        $job = $this->mockJob(TrackedJob::class);
        event(new JobProcessing('sync', $job));
        event(new JobProcessed('sync', $job));

        $this->assertNull(app(HttpTracker::class)->currentAuditId());
    }

    #[Test]
    public function tracker_context_cleared_after_job_failed(): void
    {
        $job = $this->mockJob(TrackedJob::class);
        event(new JobProcessing('sync', $job));
        event(new JobFailed('sync', $job, new \RuntimeException('boom')));

        $this->assertNull(app(HttpTracker::class)->currentAuditId());
    }

    #[Test]
    public function tracker_context_cleared_even_for_unaudited_jobs(): void
    {
        app(HttpTracker::class)->setContext(999);

        config(['recordkeeper.jobs.enabled' => false]);

        $job = $this->mockJob(UntrackedJob::class);
        event(new JobProcessed('sync', $job));

        $this->assertNull(app(HttpTracker::class)->currentAuditId());
    }

    #[Test]
    public function http_context_not_set_when_http_disabled(): void
    {
        config(['recordkeeper.http.enabled' => false]);

        $job = $this->mockJob(TrackedJob::class);
        event(new JobProcessing('sync', $job));

        $this->assertNull(app(HttpTracker::class)->currentAuditId());
    }

    #[Test]
    public function http_requests_linked_to_job_processing_audit(): void
    {
        $job = $this->mockJob(TrackedJob::class);
        event(new JobProcessing('sync', $job));

        $processingAudit = Audit::where('event', 'job.processing')->first();

        $request = $this->makeRequest('POST', 'https://api.stripe.com/v1/charges');
        $response = $this->makeResponse(200);

        event(new RequestSending($request));
        event(new ResponseReceived($request, $response));

        event(new JobProcessed('sync', $job));

        $httpRecord = AuditHttpRequest::first();

        $this->assertNotNull($httpRecord);
        $this->assertSame($processingAudit->id, $httpRecord->audit_id);
    }

    #[Test]
    public function multiple_http_calls_all_linked_to_same_job(): void
    {
        $job = $this->mockJob(TrackedJob::class);
        event(new JobProcessing('sync', $job));

        $processingAudit = Audit::where('event', 'job.processing')->first();

        foreach (['https://api.stripe.com/charge', 'https://api.hubspot.com/contact', 'https://api.sendgrid.com/mail'] as $url) {
            $req = $this->makeRequest('POST', $url);
            $resp = $this->makeResponse(201);
            event(new RequestSending($req));
            event(new ResponseReceived($req, $resp));
        }

        event(new JobProcessed('sync', $job));

        $this->assertSame(3, $processingAudit->httpRequests()->count());
    }

    #[Test]
    public function http_requests_after_job_completes_have_null_audit_id(): void
    {
        $job = $this->mockJob(TrackedJob::class);
        event(new JobProcessing('sync', $job));
        event(new JobProcessed('sync', $job));

        $request = $this->makeRequest('GET', 'https://api.stripe.com/after');
        $response = $this->makeResponse(200);

        event(new RequestSending($request));
        event(new ResponseReceived($request, $response));

        $this->assertNull(AuditHttpRequest::first()->audit_id);
    }

    #[Test]
    public function second_job_gets_its_own_context(): void
    {
        $job1 = $this->mockJob(TrackedJob::class);
        $job2 = $this->mockJob(TrackedJob::class);

        event(new JobProcessing('sync', $job1));
        $firstAuditId = app(HttpTracker::class)->currentAuditId();
        event(new JobProcessed('sync', $job1));

        event(new JobProcessing('sync', $job2));
        $secondAuditId = app(HttpTracker::class)->currentAuditId();
        event(new JobProcessed('sync', $job2));

        $this->assertNotNull($firstAuditId);
        $this->assertNotNull($secondAuditId);
        $this->assertNotSame($firstAuditId, $secondAuditId);
    }

    private function mockJob(string $jobClass): Job
    {
        $mock = $this->createMock(Job::class);
        $mock->method('getName')->willReturn($jobClass);
        $mock->method('getRawBody')->willReturn(json_encode(['displayName' => $jobClass]));
        $mock->method('getQueue')->willReturn('default');
        $mock->method('attempts')->willReturn(1);

        return $mock;
    }

    private function makeRequest(string $method, string $url): \Illuminate\Http\Client\Request
    {
        $psr = new Request($method, $url, ['Content-Type' => 'application/json']);

        return new \Illuminate\Http\Client\Request($psr);
    }

    private function makeResponse(int $status): \Illuminate\Http\Client\Response
    {
        $psr = new Response($status, [], '{}');

        return new \Illuminate\Http\Client\Response($psr);
    }
}
