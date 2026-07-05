<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Feature\Jobs;

use Illuminate\Support\Facades\Queue;
use LaraArabDev\Recordkeeper\Actions\RecordAudit;
use LaraArabDev\Recordkeeper\DataObjects\AuditPayload;
use LaraArabDev\Recordkeeper\Jobs\WriteAudit;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Support\AuditDriverManager;
use LaraArabDev\Recordkeeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('jobs')]
#[CoversClass(WriteAudit::class)]
#[CoversClass(RecordAudit::class)]
final class WriteAuditJobTest extends TestCase
{
    private function makePayload(string $event = 'route.get'): AuditPayload
    {
        return new AuditPayload(
            event: $event,
            auditableType: 'route',
            auditableId: null,
            oldValues: [],
            newValues: ['key' => 'value'],
        );
    }

    #[Test]
    public function write_audit_job_persists_audit_record(): void
    {
        $payload = $this->makePayload();

        $job = new WriteAudit($payload->toArray());
        $job->handle(app(AuditDriverManager::class));

        $this->assertSame(1, Audit::count());
        $audit = Audit::first();
        $this->assertSame('route.get', $audit->event);
        $this->assertSame(['key' => 'value'], $audit->new_values);
    }

    #[Test]
    public function record_audit_dispatches_job_when_queue_enabled(): void
    {
        Queue::fake();
        config(['recordkeeper.queue.enabled' => true]);

        $payload = $this->makePayload();
        app(RecordAudit::class)($payload);

        Queue::assertPushed(WriteAudit::class);
        $this->assertSame(0, Audit::count());
    }

    #[Test]
    public function record_audit_writes_sync_when_queue_disabled(): void
    {
        Queue::fake();
        config(['recordkeeper.queue.enabled' => false]);

        $payload = $this->makePayload();
        app(RecordAudit::class)($payload);

        Queue::assertNothingPushed();
        $this->assertSame(1, Audit::count());
    }

    #[Test]
    public function record_audit_dispatches_to_configured_queue(): void
    {
        Queue::fake();
        config([
            'recordkeeper.queue.enabled' => true,
            'recordkeeper.queue.queue' => 'my-audits',
        ]);

        $payload = $this->makePayload();
        app(RecordAudit::class)($payload);

        Queue::assertPushedOn('my-audits', WriteAudit::class);
    }

    #[Test]
    public function record_audit_uses_default_audits_queue(): void
    {
        Queue::fake();
        config([
            'recordkeeper.queue.enabled' => true,
            'recordkeeper.queue.queue' => 'audits',
        ]);

        $payload = $this->makePayload();
        app(RecordAudit::class)($payload);

        Queue::assertPushedOn('audits', WriteAudit::class);
    }
}
