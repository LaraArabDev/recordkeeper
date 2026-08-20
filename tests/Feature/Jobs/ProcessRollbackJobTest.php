<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Feature\Jobs;

use Illuminate\Support\Facades\Queue;
use LaraArabDev\Recordkeeper\Facades\Recordkeeper;
use LaraArabDev\Recordkeeper\Jobs\ProcessRollback;
use LaraArabDev\Recordkeeper\Jobs\ProcessRollbackCollection;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Support\AttributeResolver;
use LaraArabDev\Recordkeeper\Support\Rollback;
use LaraArabDev\Recordkeeper\Tests\Fixtures\Order;
use LaraArabDev\Recordkeeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('jobs')]
#[CoversClass(ProcessRollback::class)]
#[CoversClass(ProcessRollbackCollection::class)]
final class ProcessRollbackJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AttributeResolver::clearCache();
        config(['recordkeeper.rollback.enabled' => true]);
        config(['recordkeeper.rollback.track' => false]);
    }

    // ------------------------------------------------------------------
    // ProcessRollback job
    // ------------------------------------------------------------------

    #[Test]
    public function process_rollback_job_reverts_update_audit(): void
    {
        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        $audit = Audit::where('event', 'updated')->first();

        $job = new ProcessRollback($audit->id);
        $job->handle(app(Rollback::class));

        $this->assertSame('pending', $order->fresh()->status);
    }

    #[Test]
    public function process_rollback_job_reverts_create_audit(): void
    {
        $order = Order::create(['status' => 'pending']);

        $audit = Audit::where('event', 'created')->first();

        $job = new ProcessRollback($audit->id);
        $job->handle(app(Rollback::class));

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
    }

    #[Test]
    public function process_rollback_job_reverts_delete_audit(): void
    {
        $order = Order::create(['status' => 'active']);
        $order->delete();

        $audit = Audit::where('event', 'deleted')->first();

        $job = new ProcessRollback($audit->id);
        $job->handle(app(Rollback::class));

        $restored = Order::find($order->id);
        $this->assertNotNull($restored);
        $this->assertNull($restored->deleted_at);
    }

    #[Test]
    public function process_rollback_with_record_trail_true_records_trail(): void
    {
        config(['recordkeeper.rollback.track' => true]);

        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        $audit = Audit::where('event', 'updated')->first();

        $job = new ProcessRollback($audit->id, recordTrail: true);
        $job->handle(app(Rollback::class));

        $this->assertSame(1, Audit::where('event', 'rollback.undo_update')->count());
    }

    #[Test]
    public function process_rollback_with_record_trail_false_suppresses_trail(): void
    {
        config(['recordkeeper.rollback.track' => true]);

        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        $audit = Audit::where('event', 'updated')->first();

        $job = new ProcessRollback($audit->id, recordTrail: false);
        $job->handle(app(Rollback::class));

        $this->assertSame(0, Audit::where('event', 'like', 'rollback.%')->count());
    }

    // ------------------------------------------------------------------
    // ProcessRollbackCollection job
    // ------------------------------------------------------------------

    #[Test]
    public function process_rollback_collection_job_reverts_all_audits(): void
    {
        Order::create(['status' => 'a']);
        Order::create(['status' => 'b']);

        $auditIds = Audit::where('event', 'created')->pluck('id')->map(fn ($id) => (int) $id)->all();

        $job = new ProcessRollbackCollection($auditIds);
        $job->handle(app(Rollback::class));

        $this->assertDatabaseCount('orders', 0);
    }

    #[Test]
    public function process_rollback_collection_with_record_trail_true(): void
    {
        config(['recordkeeper.rollback.track' => true]);

        Order::create(['status' => 'a']);
        Order::create(['status' => 'b']);

        $auditIds = Audit::where('event', 'created')->pluck('id')->map(fn ($id) => (int) $id)->all();

        $job = new ProcessRollbackCollection($auditIds, recordTrail: true);
        $job->handle(app(Rollback::class));

        $this->assertSame(2, Audit::where('event', 'rollback.undo_create')->count());
    }

    #[Test]
    public function process_rollback_collection_with_record_trail_false(): void
    {
        config(['recordkeeper.rollback.track' => true]);

        Order::create(['status' => 'a']);

        $auditIds = Audit::where('event', 'created')->pluck('id')->map(fn ($id) => (int) $id)->all();

        $job = new ProcessRollbackCollection($auditIds, recordTrail: false);
        $job->handle(app(Rollback::class));

        $this->assertSame(0, Audit::where('event', 'like', 'rollback.%')->count());
    }

    // ------------------------------------------------------------------
    // Async dispatch — Rollback methods
    // ------------------------------------------------------------------

    #[Test]
    public function revert_async_dispatches_process_rollback_job(): void
    {
        Queue::fake();

        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        $audit = Audit::where('event', 'updated')->first();
        app(Rollback::class)->revertAsync($audit);

        Queue::assertPushed(ProcessRollback::class);
    }

    #[Test]
    public function revert_async_uses_configured_queue(): void
    {
        Queue::fake();
        config(['recordkeeper.queue.queue' => 'custom-queue']);

        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        $audit = Audit::where('event', 'updated')->first();
        app(Rollback::class)->revertAsync($audit);

        Queue::assertPushedOn('custom-queue', ProcessRollback::class);
    }

    #[Test]
    public function revert_collection_async_dispatches_collection_job(): void
    {
        Queue::fake();

        Order::create(['status' => 'a']);
        Order::create(['status' => 'b']);

        $audits = Audit::where('event', 'created')->get();
        app(Rollback::class)->revertCollectionAsync($audits);

        Queue::assertPushed(ProcessRollbackCollection::class);
    }

    #[Test]
    public function revert_collection_async_uses_configured_queue(): void
    {
        Queue::fake();
        config(['recordkeeper.queue.queue' => 'rollback-queue']);

        Order::create(['status' => 'a']);

        $audits = Audit::where('event', 'created')->get();
        app(Rollback::class)->revertCollectionAsync($audits);

        Queue::assertPushedOn('rollback-queue', ProcessRollbackCollection::class);
    }

    // ------------------------------------------------------------------
    // Facade async methods
    // ------------------------------------------------------------------

    #[Test]
    public function facade_rollback_async_dispatches_job(): void
    {
        Queue::fake();

        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        $audit = Audit::where('event', 'updated')->first();
        Recordkeeper::rollbackAsync($audit);

        Queue::assertPushed(ProcessRollback::class);
    }

    #[Test]
    public function facade_rollback_async_accepts_audit_id(): void
    {
        Queue::fake();

        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        $audit = Audit::where('event', 'updated')->first();
        Recordkeeper::rollbackAsync($audit->id);

        Queue::assertPushed(ProcessRollback::class);
    }

    #[Test]
    public function facade_rollback_batch_async_dispatches_collection_job(): void
    {
        Queue::fake();

        Recordkeeper::batch('async-batch', function (): void {
            Order::create(['status' => 'a']);
            Order::create(['status' => 'b']);
        });

        Recordkeeper::rollbackBatchAsync('async-batch');

        Queue::assertPushed(ProcessRollbackCollection::class);
    }

    // ------------------------------------------------------------------
    // CLI --async flag
    // ------------------------------------------------------------------

    #[Test]
    public function rollback_command_async_dispatches_job(): void
    {
        Queue::fake();

        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        $audit = Audit::where('event', 'updated')->first();

        $this->artisan('recordkeeper:rollback', [
            'id' => $audit->id,
            '--async' => true,
            '--yes' => true,
        ])->assertSuccessful()
            ->expectsOutputToContain('Rollback job dispatched to queue.');

        Queue::assertPushed(ProcessRollback::class);
    }

    #[Test]
    public function rollback_command_async_batch_dispatches_collection_job(): void
    {
        Queue::fake();

        Recordkeeper::batch('cli-batch', function (): void {
            Order::create(['status' => 'a']);
        });

        $this->artisan('recordkeeper:rollback', [
            '--batch' => 'cli-batch',
            '--async' => true,
            '--yes' => true,
        ])->assertSuccessful()
            ->expectsOutputToContain('Rollback job dispatched to queue.');

        Queue::assertPushed(ProcessRollbackCollection::class);
    }

    #[Test]
    public function rollback_command_async_filtered_dispatches_collection_job(): void
    {
        Queue::fake();

        Order::create(['status' => 'a']);

        $this->artisan('recordkeeper:rollback', [
            '--model' => Order::class,
            '--async' => true,
            '--yes' => true,
        ])->assertSuccessful()
            ->expectsOutputToContain('Rollback job dispatched to queue.');

        Queue::assertPushed(ProcessRollbackCollection::class);
    }

    #[Test]
    public function undo_command_async_dispatches_collection_job(): void
    {
        Queue::fake();

        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        $this->artisan('recordkeeper:undo', [
            'n' => 1,
            '--async' => true,
            '--yes' => true,
        ])->assertSuccessful()
            ->expectsOutputToContain('Undo job dispatched to queue.');

        Queue::assertPushed(ProcessRollbackCollection::class);
    }

    #[Test]
    public function restore_command_async_dispatches_job(): void
    {
        Queue::fake();

        $order = Order::create(['status' => 'active']);
        $order->delete();

        $this->artisan('recordkeeper:restore', [
            'model' => Order::class,
            'id' => $order->id,
            '--async' => true,
            '--yes' => true,
        ])->assertSuccessful()
            ->expectsOutputToContain('Restore job dispatched to queue.');

        Queue::assertPushed(ProcessRollback::class);
    }

    // ------------------------------------------------------------------
    // CLI without --async runs synchronously
    // ------------------------------------------------------------------

    #[Test]
    public function rollback_command_without_async_runs_sync(): void
    {
        Queue::fake();

        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        $audit = Audit::where('event', 'updated')->first();

        $this->artisan('recordkeeper:rollback', [
            'id' => $audit->id,
            '--yes' => true,
        ])->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertSame('pending', $order->fresh()->status);
    }

    #[Test]
    public function undo_command_without_async_runs_sync(): void
    {
        Queue::fake();

        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        $this->artisan('recordkeeper:undo', [
            'n' => 1,
            '--yes' => true,
        ])->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertSame('pending', $order->fresh()->status);
    }

    #[Test]
    public function restore_command_without_async_runs_sync(): void
    {
        Queue::fake();

        $order = Order::create(['status' => 'active']);
        $order->delete();

        $this->artisan('recordkeeper:restore', [
            'model' => Order::class,
            'id' => $order->id,
            '--yes' => true,
        ])->assertSuccessful();

        Queue::assertNothingPushed();
        $restored = Order::find($order->id);
        $this->assertNotNull($restored);
    }
}
