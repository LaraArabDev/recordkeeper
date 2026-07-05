<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Unit\Actions;

use LaraArabDev\Recordkeeper\Actions\RollbackAudits;
use LaraArabDev\Recordkeeper\Facades\Recordkeeper;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Support\AttributeResolver;
use LaraArabDev\Recordkeeper\Support\AuditQuery;
use LaraArabDev\Recordkeeper\Tests\Fixtures\Order;
use LaraArabDev\Recordkeeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('actions')]
#[CoversClass(RollbackAudits::class)]
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

        $result = app(RollbackAudits::class)->findById($audit->id);

        $this->assertNotNull($result);
        $this->assertSame($audit->id, $result->id);
    }

    #[Test]
    public function find_by_id_returns_null_for_missing(): void
    {
        $result = app(RollbackAudits::class)->findById(99999);

        $this->assertNull($result);
    }

    #[Test]
    public function find_by_batch_returns_rollbackable_audits(): void
    {
        Recordkeeper::batch('test-batch', function () {
            Order::create(['status' => 'a']);
            Order::create(['status' => 'b']);
        });

        $audits = app(RollbackAudits::class)->findByBatch('test-batch');

        $this->assertCount(2, $audits);
        foreach ($audits as $audit) {
            $this->assertSame('test-batch', $audit->batch_id);
        }
    }

    #[Test]
    public function find_by_batch_returns_empty_for_unknown_batch(): void
    {
        $audits = app(RollbackAudits::class)->findByBatch('nonexistent');

        $this->assertCount(0, $audits);
    }

    #[Test]
    public function find_by_query_returns_matching_audits(): void
    {
        Order::create(['status' => 'pending']);
        Order::create(['status' => 'shipped']);

        $query = (new AuditQuery)->model('Order');
        $audits = app(RollbackAudits::class)->findByQuery($query);

        $this->assertGreaterThanOrEqual(2, $audits->count());
    }

    #[Test]
    public function preview_returns_dry_run_result(): void
    {
        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        $audit = Audit::where('event', 'updated')->first();
        $preview = app(RollbackAudits::class)->preview($audit);

        $this->assertIsArray($preview);
        $this->assertArrayHasKey('action', $preview);
    }

    #[Test]
    public function revert_rolls_back_a_single_audit(): void
    {
        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        $audit = Audit::where('event', 'updated')->first();
        app(RollbackAudits::class)->revert($audit);

        $this->assertSame('pending', $order->fresh()->status);
    }

    #[Test]
    public function revert_collection_rolls_back_multiple_audits(): void
    {
        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'processing']);
        $order->update(['status' => 'shipped']);

        $audits = Audit::where('event', 'updated')->orderByDesc('id')->get();
        $results = app(RollbackAudits::class)->revertCollection($audits);

        $this->assertCount(2, $results);
        $this->assertSame('pending', $order->fresh()->status);
    }

    #[Test]
    public function revert_batch_rolls_back_entire_batch(): void
    {
        Recordkeeper::batch('rollback-batch', fn () => Order::create(['status' => 'batched']));

        $results = app(RollbackAudits::class)->revertBatch('rollback-batch');

        $this->assertNotEmpty($results);
        $this->assertDatabaseCount('orders', 0);
    }
}
