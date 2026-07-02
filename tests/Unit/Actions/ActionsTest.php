<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Unit;

use LaraArabDev\Recordkeeper\Actions\PruneAudits;
use LaraArabDev\Recordkeeper\Actions\RecordAudit;
use LaraArabDev\Recordkeeper\Actions\RedactValues;
use LaraArabDev\Recordkeeper\Actions\SearchAudits;
use LaraArabDev\Recordkeeper\DataObjects\AuditPayload;
use LaraArabDev\Recordkeeper\Facades\Recordkeeper;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Support\AttributeResolver;
use LaraArabDev\Recordkeeper\Support\Rollback;
use LaraArabDev\Recordkeeper\Tests\Fixtures\Order;
use LaraArabDev\Recordkeeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Unit tests for RecordAudit, SearchAudits, RedactValues, and Rollback operations.
 */
#[Group('actions')]
#[CoversClass(RecordAudit::class)]
#[CoversClass(SearchAudits::class)]
#[CoversClass(RedactValues::class)]
#[CoversClass(PruneAudits::class)]
class ActionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AttributeResolver::clearCache();
    }

    // ── RecordAudit ───────────────────────────────────────────────────────

    #[Test]
    public function record_audit_persists_payload_to_database(): void
    {
        $payload = new AuditPayload(
            event: 'created',
            auditableType: 'Order',
            auditableId: 1,
            oldValues: [],
            newValues: ['status' => 'pending'],
        );

        $audit = app(RecordAudit::class)($payload);

        $this->assertInstanceOf(Audit::class, $audit);
        $this->assertTrue($audit->exists);
        $this->assertDatabaseHas('audits', ['event' => 'created', 'auditable_type' => 'Order']);
    }

    #[Test]
    public function record_audit_stores_context(): void
    {
        $payload = new AuditPayload(
            event: 'system.custom',
            auditableType: 'system',
            auditableId: null,
            oldValues: [],
            newValues: [],
            context: ['reason' => 'test'],
        );

        $audit = app(RecordAudit::class)($payload);

        $this->assertSame('test', $audit->context['reason'] ?? null);
    }

    // ── SearchAudits ──────────────────────────────────────────────────────

    #[Test]
    public function search_returns_empty_when_no_audits(): void
    {
        $results = app(SearchAudits::class)();

        $this->assertCount(0, $results);
    }

    #[Test]
    public function search_returns_audits_without_filters(): void
    {
        Order::create(['status' => 'a']);
        Order::create(['status' => 'b']);

        $results = app(SearchAudits::class)();

        $this->assertGreaterThanOrEqual(2, $results->count());
    }

    #[Test]
    public function search_filters_by_event(): void
    {
        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        $results = app(SearchAudits::class)(['event' => 'created']);

        foreach ($results as $audit) {
            $this->assertSame('created', $audit->event);
        }
    }

    #[Test]
    public function search_filters_by_model_short_name(): void
    {
        Order::create(['status' => 'ok']);

        $results = app(SearchAudits::class)(['model' => 'Order']);

        $this->assertGreaterThanOrEqual(1, $results->count());
        foreach ($results as $audit) {
            $this->assertStringContainsString('Order', $audit->auditable_type);
        }
    }

    #[Test]
    public function search_respects_limit(): void
    {
        for ($i = 0; $i < 10; $i++) {
            Order::create(['status' => "s{$i}"]);
        }

        $results = app(SearchAudits::class)([], 3);

        $this->assertCount(3, $results);
    }

    #[Test]
    public function search_respects_offset(): void
    {
        for ($i = 0; $i < 5; $i++) {
            Order::create(['status' => "step{$i}"]);
        }

        $all = app(SearchAudits::class)([], 100, 0);
        $paged = app(SearchAudits::class)([], 100, 2);

        $this->assertCount(max(0, $all->count() - 2), $paged);
    }

    #[Test]
    public function search_filters_by_batch(): void
    {
        Recordkeeper::batch('batch-xyz', fn () => Order::create(['status' => 'batched']));
        Order::create(['status' => 'no-batch']);

        $results = app(SearchAudits::class)(['batch' => 'batch-xyz']);

        $this->assertCount(1, $results);
        $this->assertSame('batch-xyz', $results->first()->batch_id);
    }

    // ── RedactValues ──────────────────────────────────────────────────────

    #[Test]
    public function redact_values_uses_explicit_patterns(): void
    {
        $action = app(RedactValues::class);
        $result = $action(['secret' => 'x', 'other' => 'y'], ['secret']);

        $this->assertSame('***', $result['secret']);
        $this->assertSame('y', $result['other']);
    }

    // ── PruneAudits ───────────────────────────────────────────────────────

    // ── Rollback (revert / revertBatch) ─────────────────────────────────

    #[Test]
    public function rollback_revert_performs_update_rollback(): void
    {
        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        $audit = Audit::where('event', 'updated')->first();
        app(Rollback::class)->revert($audit, false);

        $this->assertSame('pending', $order->fresh()->status);
    }

    #[Test]
    public function rollback_revert_batch_rolls_back_batch(): void
    {
        Recordkeeper::batch('unit-batch', fn () => Order::create(['status' => 'batched']));

        app(Rollback::class)->revertBatch('unit-batch', false);

        $this->assertDatabaseCount('orders', 0);
    }

    #[Test]
    public function rollback_revert_restores_deleted_record(): void
    {
        $order = Order::create(['status' => 'active']);
        $order->delete();

        $deletedAudit = Audit::where('event', 'deleted')->first();

        app(Rollback::class)->revert($deletedAudit, false);

        $this->assertNotNull(Order::find($order->id));
    }

    #[Test]
    public function search_filters_by_subject_id(): void
    {
        $order1 = Order::create(['status' => 'a']);
        $order2 = Order::create(['status' => 'b']);

        $results = app(SearchAudits::class)(['model' => 'Order', 'subject_id' => $order1->id]);

        $this->assertGreaterThanOrEqual(1, $results->count());
        foreach ($results as $audit) {
            $this->assertSame((string) $order1->id, (string) $audit->auditable_id);
        }
    }

    #[Test]
    public function search_filters_by_tag(): void
    {
        Audit::create([
            'event' => 'created',
            'auditable_type' => 'system',
            'old_values' => [],
            'new_values' => [],
            'tags' => 'finance',
        ]);
        Order::create(['status' => 'ok']);

        $results = app(SearchAudits::class)(['tag' => 'finance']);

        $this->assertCount(1, $results);
        $this->assertStringContainsString('finance', $results->first()->tags);
    }

    #[Test]
    public function search_filters_by_since(): void
    {
        Order::create(['status' => 'now']);

        $results = app(SearchAudits::class)(['since' => now()->subMinute()]);

        $this->assertGreaterThanOrEqual(1, $results->count());
    }

    #[Test]
    public function search_filters_by_q(): void
    {
        Order::create(['status' => 'pending']);

        $results = app(SearchAudits::class)(['q' => 'Order']);

        $this->assertGreaterThanOrEqual(1, $results->count());
    }

    #[Test]
    public function search_filters_by_user(): void
    {
        Audit::create([
            'event' => 'updated',
            'auditable_type' => 'system',
            'old_values' => [],
            'new_values' => [],
            'user_id' => 42,
            'user_type' => 'App\\Models\\User',
        ]);
        Order::create(['status' => 'ok']);

        $results = app(SearchAudits::class)(['user' => 42, 'user_type' => 'App\\Models\\User']);

        $this->assertGreaterThanOrEqual(1, $results->count());
        foreach ($results as $a) {
            $this->assertSame(42, (int) $a->user_id);
        }
    }

    #[Test]
    public function search_filters_by_user_type_only(): void
    {
        Audit::create([
            'event' => 'updated',
            'auditable_type' => 'system',
            'old_values' => [],
            'new_values' => [],
            'user_id' => 99,
            'user_type' => 'App\\Models\\Admin',
        ]);
        Order::create(['status' => 'ok']);

        $results = app(SearchAudits::class)(['user_type' => 'App\\Models\\Admin']);

        $this->assertGreaterThanOrEqual(1, $results->count());
        foreach ($results as $a) {
            $this->assertSame('App\\Models\\Admin', $a->user_type);
        }
    }

    // ── SearchAudits guard filter ─────────────────────────────────────────
    #[Test]
    public function search_filters_by_guard(): void
    {
        $payload = new AuditPayload(
            event: 'route.get',
            auditableType: 'route',
            auditableId: null,
            oldValues: [],
            newValues: [],
        );
        $audit = app(RecordAudit::class)($payload);
        $audit->guard = 'api';
        $audit->save();

        $results = app(SearchAudits::class)(['guard' => 'api']);

        $this->assertGreaterThanOrEqual(1, $results->count());
        foreach ($results as $a) {
            $this->assertSame('api', $a->guard);
        }
    }
}
