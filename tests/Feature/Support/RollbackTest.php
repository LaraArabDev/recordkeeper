<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Feature;

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
 * Feature tests for single-audit, batch, and dry-run rollback operations.
 */
#[Group('support')]
#[CoversClass(Rollback::class)]
class RollbackTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AttributeResolver::clearCache();
        config(['recordkeeper.rollback.enabled' => true]);
    }

    // ------------------------------------------------------------------
    // Update rollback — uses laravel-auditing's transitionTo()
    // ------------------------------------------------------------------

    #[Test]
    public function rolls_back_update_via_transition_to(): void
    {
        $order = Order::create(['status' => 'pending', 'total' => 100]);
        $order->update(['status' => 'shipped']);

        Audit::where('event', 'updated')->first()->rollback();

        $this->assertSame('pending', $order->fresh()->status);
    }

    #[Test]
    public function rollback_update_only_restores_changed_field(): void
    {
        $order = Order::create(['status' => 'a', 'total' => 50]);
        $order->update(['status' => 'b']);

        Audit::where('event', 'updated')->first()->rollback();

        $fresh = $order->fresh();
        $this->assertSame('a', $fresh->status);
        $this->assertEquals(50, $fresh->total); // unchanged
    }

    #[Test]
    public function multiple_sequential_rollbacks(): void
    {
        $order = Order::create(['status' => 'v1']);
        $order->update(['status' => 'v2']);
        $order->update(['status' => 'v3']);

        $audits = Audit::where('event', 'updated')->latest('id')->get();

        // Roll back v2→v3 to v2
        $audits[0]->rollback();
        $this->assertSame('v2', $order->fresh()->status);

        // Roll back v1→v2 to v1
        $audits[1]->rollback();
        $this->assertSame('v1', $order->fresh()->status);
    }

    // ------------------------------------------------------------------
    // Dry run
    // ------------------------------------------------------------------

    #[Test]
    public function dry_run_returns_action_array(): void
    {
        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        $result = Audit::where('event', 'updated')->first()->rollback(dryRun: true);

        $this->assertIsArray($result);
        $this->assertSame('update', $result['action']);
    }

    #[Test]
    public function dry_run_does_not_modify_record(): void
    {
        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        Audit::where('event', 'updated')->first()->rollback(dryRun: true);

        $this->assertSame('shipped', $order->fresh()->status);
    }

    // ------------------------------------------------------------------
    // Create rollback — deletes the record
    // ------------------------------------------------------------------

    #[Test]
    public function rollback_of_create_deletes_record(): void
    {
        $order = Order::create(['status' => 'pending']);

        Audit::where('event', 'created')->first()->rollback();

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
    }

    #[Test]
    public function rollback_of_create_dry_run_returns_delete_action(): void
    {
        $order = Order::create(['status' => 'pending']);

        $result = Audit::where('event', 'created')->first()->rollback(dryRun: true);

        $this->assertSame('delete', $result['action']);
        $this->assertDatabaseHas('orders', ['id' => $order->id]); // not deleted
    }

    // ------------------------------------------------------------------
    // Delete rollback — restores soft-deleted record
    // ------------------------------------------------------------------

    #[Test]
    public function restores_soft_deleted_record(): void
    {
        $order = Order::create(['status' => 'active', 'total' => 50]);
        $order->delete();

        Audit::where('event', 'deleted')->first()->rollback();

        $restored = Order::withTrashed()->find($order->id);
        $this->assertNotNull($restored);
        $this->assertNull($restored->deleted_at);
    }

    #[Test]
    public function restore_dry_run_does_not_un_delete(): void
    {
        $order = Order::create(['status' => 'active']);
        $order->delete();

        $result = Audit::where('event', 'deleted')->first()->rollback(dryRun: true);

        $this->assertSame('restore', $result['action']);
        $this->assertSoftDeleted('orders', ['id' => $order->id]);
    }

    // ------------------------------------------------------------------
    // Batch rollback
    // ------------------------------------------------------------------

    #[Test]
    public function rollback_batch_reverts_all_in_batch(): void
    {
        Recordkeeper::batch('test-batch', function (): void {
            Order::create(['status' => 'a']);
            Order::create(['status' => 'b']);
        });

        Recordkeeper::rollbackBatch('test-batch');

        $this->assertDatabaseCount('orders', 0);
    }

    #[Test]
    public function rollback_batch_only_affects_its_batch_id(): void
    {
        Recordkeeper::batch('batch-1', fn () => Order::create(['status' => 'x']));
        Order::create(['status' => 'no-batch']); // no batch_id

        Recordkeeper::rollbackBatch('batch-1');

        // 'no-batch' order must still exist
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('orders', ['status' => 'no-batch']);
    }

    #[Test]
    public function rollback_batch_dry_run(): void
    {
        Recordkeeper::batch('dry-batch', fn () => Order::create(['status' => 'z']));

        $results = Recordkeeper::rollbackBatch('dry-batch', dryRun: true);

        $this->assertCount(1, $results);
        $this->assertDatabaseCount('orders', 1); // not deleted
    }

    // ------------------------------------------------------------------
    // Guard / config
    // ------------------------------------------------------------------

    #[Test]
    public function rollback_throws_when_disabled(): void
    {
        config(['recordkeeper.rollback.enabled' => false]);

        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        $this->expectException(\RuntimeException::class);
        Audit::where('event', 'updated')->first()->rollback();
    }

    #[Test]
    public function restore_throws_when_restore_deleted_disabled(): void
    {
        config(['recordkeeper.rollback.restore_deleted' => false]);

        $order = Order::create(['status' => 'pending']);
        $order->delete();

        $this->expectException(\RuntimeException::class);
        Audit::where('event', 'deleted')->first()->rollback();
    }
}
