<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Unit\Actions;

use LaraArabDev\Recordkeeper\Facades\Recordkeeper;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Support\AttributeResolver;
use LaraArabDev\Recordkeeper\Support\AuditQuery;
use LaraArabDev\Recordkeeper\Support\Rollback;
use LaraArabDev\Recordkeeper\Tests\Fixtures\Order;
use LaraArabDev\Recordkeeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('actions')]
#[CoversClass(Rollback::class)]
class RollbackAuditsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AttributeResolver::clearCache();
    }

    #[Test]
    public function find_by_id_returns_audit(): void
    {
        Order::create(['status' => 'pending']);
        $audit = Audit::first();

        $result = Audit::find($audit->id);

        $this->assertNotNull($result);
        $this->assertSame($audit->id, $result->id);
    }

    #[Test]
    public function find_by_id_returns_null_for_missing(): void
    {
        $result = Audit::find(99999);

        $this->assertNull($result);
    }

    #[Test]
    public function find_by_batch_returns_rollbackable_audits(): void
    {
        Recordkeeper::batch('test-batch', function () {
            Order::create(['status' => 'a']);
            Order::create(['status' => 'b']);
        });

        $audits = Audit::where('batch_id', 'test-batch')
            ->whereIn('event', Audit::ROLLBACKABLE_EVENTS)
            ->get();

        $this->assertCount(2, $audits);
        foreach ($audits as $audit) {
            $this->assertSame('test-batch', $audit->batch_id);
        }
    }

    #[Test]
    public function find_by_batch_returns_empty_for_unknown_batch(): void
    {
        $audits = Audit::where('batch_id', 'nonexistent')
            ->whereIn('event', Audit::ROLLBACKABLE_EVENTS)
            ->get();

        $this->assertCount(0, $audits);
    }

    #[Test]
    public function find_by_query_returns_matching_audits(): void
    {
        Order::create(['status' => 'pending']);
        Order::create(['status' => 'shipped']);

        $audits = (new AuditQuery)->model('Order')->rollbackable()->latest()->builder()->get();

        $this->assertGreaterThanOrEqual(2, $audits->count());
    }

    #[Test]
    public function preview_returns_dry_run_result(): void
    {
        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        $audit = Audit::where('event', 'updated')->first();
        $preview = app(Rollback::class)->revert($audit, true);

        $this->assertIsArray($preview);
        $this->assertArrayHasKey('action', $preview);
    }

    #[Test]
    public function revert_rolls_back_a_single_audit(): void
    {
        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        $audit = Audit::where('event', 'updated')->first();
        app(Rollback::class)->revert($audit);

        $this->assertSame('pending', $order->fresh()->status);
    }

    #[Test]
    public function revert_collection_rolls_back_multiple_audits(): void
    {
        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'processing']);
        $order->update(['status' => 'shipped']);

        $audits = Audit::where('event', 'updated')->orderByDesc('id')->get();
        $results = app(Rollback::class)->revertCollection($audits);

        $this->assertCount(2, $results);
        $this->assertSame('pending', $order->fresh()->status);
    }

    #[Test]
    public function revert_batch_rolls_back_entire_batch(): void
    {
        Recordkeeper::batch('rollback-batch', fn () => Order::create(['status' => 'batched']));

        $results = app(Rollback::class)->revertBatch('rollback-batch');

        $this->assertNotEmpty($results);
        $this->assertDatabaseCount('orders', 0);
    }
}
