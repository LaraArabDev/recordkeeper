<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Feature;

use LaraArabDev\Recordkeeper\Facades\Recordkeeper;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Support\Rollback;
use LaraArabDev\Recordkeeper\Tests\Fixtures\Order;
use LaraArabDev\Recordkeeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Feature tests for rollback edge cases: missing models, non-rollbackable events, and isRollbackable checks.
 */
#[Group('support')]
#[CoversClass(Rollback::class)]
class RollbackEdgeCasesTest extends TestCase
{
    #[Test]
    public function rollback_throws_when_disabled(): void
    {
        config(['recordkeeper.rollback.enabled' => false]);

        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);
        $audit = Audit::where('event', 'updated')->first();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Rollback is disabled');

        app(Rollback::class)->revert($audit, false);
    }

    #[Test]
    public function restore_throws_when_restore_deleted_disabled(): void
    {
        config(['recordkeeper.rollback.restore_deleted' => false]);

        $order = Order::create(['status' => 'active']);
        $order->delete();
        $audit = Audit::where('event', 'deleted')->first();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('restore_deleted is disabled');

        app(Rollback::class)->revert($audit, false);
    }

    #[Test]
    public function undo_create_returns_null_when_model_already_deleted(): void
    {
        $order = Order::create(['status' => 'pending']);
        $audit = Audit::where('event', 'created')->first();

        $order->forceDelete();

        $result = app(Rollback::class)->revert($audit, false);

        $this->assertNull($result);
    }

    #[Test]
    public function undo_update_returns_null_when_model_missing(): void
    {
        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);
        $audit = Audit::where('event', 'updated')->first();

        $order->forceDelete();

        $result = app(Rollback::class)->revert($audit, false);

        $this->assertNull($result);
    }

    #[Test]
    public function undo_create_dry_run_returns_preview(): void
    {
        $order = Order::create(['status' => 'pending']);
        $audit = Audit::where('event', 'created')->first();

        $result = app(Rollback::class)->revert($audit, true);

        $this->assertSame('delete', $result['action']);
        $this->assertSame(Order::class, $result['auditable_type']);
        $this->assertSame($order->id, $result['auditable_id']);
    }

    #[Test]
    public function restore_dry_run_returns_preview(): void
    {
        $order = Order::create(['status' => 'active']);
        $order->delete();
        $audit = Audit::where('event', 'deleted')->first();

        $result = app(Rollback::class)->revert($audit, true);

        $this->assertSame('restore', $result['action']);
        $this->assertArrayHasKey('attributes', $result);
    }

    #[Test]
    public function revert_batch_dry_run(): void
    {
        Recordkeeper::batch('edge-batch', function (): void {
            Order::create(['status' => 'x']);
            Order::create(['status' => 'y']);
        });

        $results = app(Rollback::class)->revertBatch('edge-batch', true);

        $this->assertCount(2, $results);
        $this->assertDatabaseCount('orders', 2);
    }

    #[Test]
    public function decrypt_values_passes_through_plain_values(): void
    {
        $order = Order::create(['status' => 'pending', 'total' => 10.0]);
        $order->update(['status' => 'shipped', 'total' => 99.99]);
        $audit = Audit::where('event', 'updated')->first();

        $result = app(Rollback::class)->revert($audit, false);

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertNotNull($result);
    }

    #[Test]
    public function is_rollbackable_returns_false_for_route_event(): void
    {
        $audit = Audit::create([
            'event' => 'route.get',
            'auditable_type' => 'route',
            'old_values' => [],
            'new_values' => [],
        ]);

        $this->assertFalse($audit->isRollbackable());
    }

    #[Test]
    public function is_rollbackable_returns_true_for_restored_event(): void
    {
        $audit = Audit::create([
            'event' => 'restored',
            'auditable_type' => Order::class,
            'auditable_id' => 1,
            'old_values' => ['status' => 'active'],
            'new_values' => ['status' => 'active'],
        ]);

        $this->assertTrue($audit->isRollbackable());
    }
}
