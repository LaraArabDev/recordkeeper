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

#[Group('support')]
#[CoversClass(Rollback::class)]
final class RollbackAuditTrailTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AttributeResolver::clearCache();
        config(['recordkeeper.rollback.enabled' => true]);
        config(['recordkeeper.rollback.track' => true]);
    }

    // ------------------------------------------------------------------
    // Event-specific trail recording
    // ------------------------------------------------------------------

    #[Test]
    public function rollback_of_update_records_undo_update_trail(): void
    {
        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        $updateAudit = Audit::where('event', 'updated')->first();
        app(Rollback::class)->revert($updateAudit);

        $trail = Audit::where('event', 'rollback.undo_update')->first();
        $this->assertNotNull($trail);
        $this->assertSame($updateAudit->id, $trail->context['original_audit_id']);
        $this->assertSame('updated', $trail->context['original_event']);
    }

    #[Test]
    public function rollback_of_create_records_undo_create_trail(): void
    {
        $order = Order::create(['status' => 'pending']);

        $createAudit = Audit::where('event', 'created')->first();
        app(Rollback::class)->revert($createAudit);

        $trail = Audit::where('event', 'rollback.undo_create')->first();
        $this->assertNotNull($trail);
        $this->assertSame($createAudit->id, $trail->context['original_audit_id']);
        $this->assertSame('created', $trail->context['original_event']);
    }

    #[Test]
    public function rollback_of_delete_records_restore_trail(): void
    {
        $order = Order::create(['status' => 'active']);
        $order->delete();

        $deleteAudit = Audit::where('event', 'deleted')->first();
        app(Rollback::class)->revert($deleteAudit);

        $trail = Audit::where('event', 'rollback.restore')->first();
        $this->assertNotNull($trail);
        $this->assertSame($deleteAudit->id, $trail->context['original_audit_id']);
        $this->assertSame('deleted', $trail->context['original_event']);
    }

    // ------------------------------------------------------------------
    // Trail context details
    // ------------------------------------------------------------------

    #[Test]
    public function trail_context_includes_original_changes(): void
    {
        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        $updateAudit = Audit::where('event', 'updated')->first();
        app(Rollback::class)->revert($updateAudit);

        $trail = Audit::where('event', 'rollback.undo_update')->first();
        $this->assertNotNull($trail);
        $this->assertArrayHasKey('original_changes', $trail->context);
    }

    #[Test]
    public function trail_links_to_correct_subject_model(): void
    {
        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        $updateAudit = Audit::where('event', 'updated')->first();
        app(Rollback::class)->revert($updateAudit);

        $trail = Audit::where('event', 'rollback.undo_update')->first();
        $this->assertNotNull($trail);
        $this->assertSame(Order::class, $trail->auditable_type);
        $this->assertSame((string) $order->id, (string) $trail->auditable_id);
    }

    // ------------------------------------------------------------------
    // Batch and collection rollback trails
    // ------------------------------------------------------------------

    #[Test]
    public function batch_rollback_records_trail_for_each_audit(): void
    {
        Recordkeeper::batch('trail-batch', function (): void {
            Order::create(['status' => 'a']);
            Order::create(['status' => 'b']);
        });

        app(Rollback::class)->revertBatch('trail-batch');

        $this->assertSame(2, Audit::where('event', 'rollback.undo_create')->count());
    }

    #[Test]
    public function collection_rollback_records_trail_for_each_audit(): void
    {
        Order::create(['status' => 'a']);
        Order::create(['status' => 'b']);

        $audits = Audit::where('event', 'created')->get();
        app(Rollback::class)->revertCollection($audits);

        $this->assertSame(2, Audit::where('event', 'rollback.undo_create')->count());
    }

    #[Test]
    public function batch_rollback_with_record_trail_false_suppresses_trail(): void
    {
        Recordkeeper::batch('no-trail-batch', function (): void {
            Order::create(['status' => 'a']);
        });

        app(Rollback::class)->revertBatch('no-trail-batch', recordTrail: false);

        $this->assertSame(0, Audit::where('event', 'like', 'rollback.%')->count());
    }

    #[Test]
    public function collection_rollback_with_record_trail_false_suppresses_trail(): void
    {
        Order::create(['status' => 'a']);

        $audits = Audit::where('event', 'created')->get();
        app(Rollback::class)->revertCollection($audits, recordTrail: false);

        $this->assertSame(0, Audit::where('event', 'like', 'rollback.%')->count());
    }

    // ------------------------------------------------------------------
    // Trail suppression
    // ------------------------------------------------------------------

    #[Test]
    public function track_disabled_suppresses_trail(): void
    {
        config(['recordkeeper.rollback.track' => false]);

        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        $updateAudit = Audit::where('event', 'updated')->first();
        app(Rollback::class)->revert($updateAudit);

        $this->assertSame(0, Audit::where('event', 'like', 'rollback.%')->count());
    }

    #[Test]
    public function dry_run_does_not_record_trail(): void
    {
        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        $updateAudit = Audit::where('event', 'updated')->first();
        app(Rollback::class)->revert($updateAudit, dryRun: true);

        $this->assertSame(0, Audit::where('event', 'like', 'rollback.%')->count());
    }

    #[Test]
    public function record_trail_parameter_suppresses_trail(): void
    {
        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        $updateAudit = Audit::where('event', 'updated')->first();
        app(Rollback::class)->revert($updateAudit, recordTrail: false);

        $this->assertSame(0, Audit::where('event', 'like', 'rollback.%')->count());
    }

    #[Test]
    public function dry_run_batch_does_not_record_trail(): void
    {
        Recordkeeper::batch('dry-trail', function (): void {
            Order::create(['status' => 'a']);
        });

        app(Rollback::class)->revertBatch('dry-trail', dryRun: true);

        $this->assertSame(0, Audit::where('event', 'like', 'rollback.%')->count());
    }

    // ------------------------------------------------------------------
    // Mixed event types in batch
    // ------------------------------------------------------------------

    #[Test]
    public function batch_with_mixed_events_records_correct_trail_types(): void
    {
        $order = null;

        Recordkeeper::batch('mixed-batch', function () use (&$order): void {
            $order = Order::create(['status' => 'pending']);
            $order->update(['status' => 'shipped']);
        });

        app(Rollback::class)->revertBatch('mixed-batch');

        // Batch has a created + updated audit; rollback reverses newest first
        // The update rollback records rollback.undo_update
        $this->assertSame(1, Audit::where('event', 'rollback.undo_update')->count());
        // The create rollback records rollback.undo_create
        $this->assertSame(1, Audit::where('event', 'rollback.undo_create')->count());
    }
}
