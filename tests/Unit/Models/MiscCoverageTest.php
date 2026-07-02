<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Unit;

use LaraArabDev\Recordkeeper\Events\ChangeRecorded;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Models\AuditHttpRequest;
use LaraArabDev\Recordkeeper\RecordkeeperServiceProvider;
use LaraArabDev\Recordkeeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Coverage tests for Audit model features, relations, casts, and service provider registration.
 */
#[Group('models')]
#[CoversClass(Audit::class)]
#[CoversClass(AuditHttpRequest::class)]
#[CoversClass(ChangeRecorded::class)]
#[CoversClass(RecordkeeperServiceProvider::class)]
class MiscCoverageTest extends TestCase
{
    #[Test]
    public function audit_prunable_returns_false_condition_when_retention_zero(): void
    {
        config(['recordkeeper.retention.default_days' => 0]);

        $audit = new Audit;
        $query = $audit->prunable();

        $this->assertSame(0, $query->count());
    }

    #[Test]
    public function audit_prunable_returns_date_query_when_retention_set(): void
    {
        config(['recordkeeper.retention.default_days' => 30]);

        Audit::create([
            'event' => 'created',
            'auditable_type' => 'system',
            'old_values' => [],
            'new_values' => [],
            'created_at' => now()->subDays(60),
            'updated_at' => now()->subDays(60),
        ]);

        $audit = new Audit;
        $count = $audit->prunable()->count();

        $this->assertSame(1, $count);
    }

    #[Test]
    public function audit_http_requests_relation(): void
    {
        $audit = Audit::create([
            'event' => 'job.processed',
            'auditable_type' => 'job',
            'old_values' => [],
            'new_values' => [],
        ]);

        AuditHttpRequest::create([
            'audit_id' => $audit->id,
            'method' => 'GET',
            'url' => 'https://api.example.com/orders',
            'status_code' => 200,
            'duration_ms' => 123,
            'failed' => false,
        ]);

        $this->assertCount(1, $audit->httpRequests);
        $this->assertSame('GET', $audit->httpRequests->first()->method);
    }

    #[Test]
    public function change_recorded_event_carries_audit(): void
    {
        $audit = Audit::create([
            'event' => 'created',
            'auditable_type' => 'system',
            'old_values' => [],
            'new_values' => [],
        ]);

        $event = new ChangeRecorded($audit);

        $this->assertSame($audit, $event->audit);
    }

    #[Test]
    public function audit_model_casts_old_new_values_as_arrays(): void
    {
        $audit = Audit::create([
            'event' => 'updated',
            'auditable_type' => 'system',
            'old_values' => ['a' => 1],
            'new_values' => ['b' => 2],
        ]);

        $fresh = $audit->fresh();
        $this->assertIsArray($fresh->old_values);
        $this->assertIsArray($fresh->new_values);
        $this->assertSame(['a' => 1], $fresh->old_values);
        $this->assertSame(['b' => 2], $fresh->new_values);
    }

    #[Test]
    public function audit_http_request_model_boolean_cast(): void
    {
        $req = AuditHttpRequest::create([
            'method' => 'POST',
            'url' => 'https://example.com',
            'failed' => true,
        ]);

        $this->assertTrue($req->fresh()->failed);
    }

    #[Test]
    public function recordkeeper_service_provider_registers_middleware_aliases(): void
    {
        $router = app('router');

        $middleware = $router->getMiddleware();

        $this->assertArrayHasKey('audit', $middleware);
        $this->assertArrayHasKey('audit.api', $middleware);
    }
}
