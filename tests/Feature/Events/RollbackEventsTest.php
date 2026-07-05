<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Feature;

use Illuminate\Support\Facades\Event;
use LaraArabDev\Recordkeeper\Events\RollbackCompleted;
use LaraArabDev\Recordkeeper\Events\RollbackStarted;
use LaraArabDev\Recordkeeper\Facades\Recordkeeper;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Support\AttributeResolver;
use LaraArabDev\Recordkeeper\Support\Rollback;
use LaraArabDev\Recordkeeper\Tests\Fixtures\Order;
use LaraArabDev\Recordkeeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('events')]
#[CoversClass(RollbackStarted::class)]
#[CoversClass(RollbackCompleted::class)]
final class RollbackEventsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AttributeResolver::clearCache();
        config(['recordkeeper.rollback.enabled' => true]);
        config(['recordkeeper.rollback.track' => false]);
    }

    // ------------------------------------------------------------------
    // Single revert events
    // ------------------------------------------------------------------

    #[Test]
    public function rollback_started_is_dispatched_before_revert(): void
    {
        Event::fake([RollbackStarted::class, RollbackCompleted::class]);

        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        $audit = Audit::where('event', 'updated')->first();
        app(Rollback::class)->revert($audit);

        Event::assertDispatched(RollbackStarted::class, function (RollbackStarted $event) use ($audit): bool {
            return $event->audit->id === $audit->id && $event->dryRun === false;
        });
    }

    #[Test]
    public function rollback_completed_is_dispatched_after_revert(): void
    {
        Event::fake([RollbackStarted::class, RollbackCompleted::class]);

        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        $audit = Audit::where('event', 'updated')->first();
        app(Rollback::class)->revert($audit);

        Event::assertDispatched(RollbackCompleted::class, function (RollbackCompleted $event) use ($audit): bool {
            return $event->audit->id === $audit->id && $event->result !== null;
        });
    }

    #[Test]
    public function events_carry_correct_audit_and_result(): void
    {
        Event::fake([RollbackStarted::class, RollbackCompleted::class]);

        $order = Order::create(['status' => 'pending']);

        $audit = Audit::where('event', 'created')->first();
        app(Rollback::class)->revert($audit);

        Event::assertDispatched(RollbackStarted::class, function (RollbackStarted $event) use ($audit): bool {
            return $event->audit->id === $audit->id;
        });

        Event::assertDispatched(RollbackCompleted::class, function (RollbackCompleted $event) use ($audit): bool {
            return $event->audit->id === $audit->id;
        });
    }

    // ------------------------------------------------------------------
    // Dry-run events
    // ------------------------------------------------------------------

    #[Test]
    public function dry_run_still_dispatches_events(): void
    {
        Event::fake([RollbackStarted::class, RollbackCompleted::class]);

        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        $audit = Audit::where('event', 'updated')->first();
        app(Rollback::class)->revert($audit, dryRun: true);

        Event::assertDispatched(RollbackStarted::class, function (RollbackStarted $event): bool {
            return $event->dryRun === true;
        });

        Event::assertDispatched(RollbackCompleted::class);
    }

    #[Test]
    public function dry_run_completed_result_is_preview_array(): void
    {
        Event::fake([RollbackStarted::class, RollbackCompleted::class]);

        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        $audit = Audit::where('event', 'updated')->first();
        app(Rollback::class)->revert($audit, dryRun: true);

        Event::assertDispatched(RollbackCompleted::class, function (RollbackCompleted $event): bool {
            return is_array($event->result) && isset($event->result['action']);
        });
    }

    // ------------------------------------------------------------------
    // Delete / restore events
    // ------------------------------------------------------------------

    #[Test]
    public function rollback_of_delete_dispatches_events(): void
    {
        Event::fake([RollbackStarted::class, RollbackCompleted::class]);

        $order = Order::create(['status' => 'active']);
        $order->delete();

        $audit = Audit::where('event', 'deleted')->first();
        app(Rollback::class)->revert($audit);

        Event::assertDispatched(RollbackStarted::class, function (RollbackStarted $event) use ($audit): bool {
            return $event->audit->id === $audit->id;
        });

        Event::assertDispatched(RollbackCompleted::class, function (RollbackCompleted $event) use ($audit): bool {
            return $event->audit->id === $audit->id && $event->result instanceof Order;
        });
    }

    // ------------------------------------------------------------------
    // Batch and collection events
    // ------------------------------------------------------------------

    #[Test]
    public function batch_rollback_dispatches_events_for_each_audit(): void
    {
        Event::fake([RollbackStarted::class, RollbackCompleted::class]);

        Recordkeeper::batch('events-batch', function (): void {
            Order::create(['status' => 'a']);
            Order::create(['status' => 'b']);
        });

        app(Rollback::class)->revertBatch('events-batch');

        Event::assertDispatched(RollbackStarted::class, 2);
        Event::assertDispatched(RollbackCompleted::class, 2);
    }

    #[Test]
    public function collection_rollback_dispatches_events_for_each_audit(): void
    {
        Event::fake([RollbackStarted::class, RollbackCompleted::class]);

        Order::create(['status' => 'a']);
        Order::create(['status' => 'b']);

        $audits = Audit::where('event', 'created')->get();
        app(Rollback::class)->revertCollection($audits);

        Event::assertDispatched(RollbackStarted::class, 2);
        Event::assertDispatched(RollbackCompleted::class, 2);
    }

    // ------------------------------------------------------------------
    // Event ordering
    // ------------------------------------------------------------------

    #[Test]
    public function started_event_fires_before_completed_event(): void
    {
        $sequence = [];

        Event::listen(RollbackStarted::class, function () use (&$sequence): void {
            $sequence[] = 'started';
        });
        Event::listen(RollbackCompleted::class, function () use (&$sequence): void {
            $sequence[] = 'completed';
        });

        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        $audit = Audit::where('event', 'updated')->first();
        app(Rollback::class)->revert($audit);

        $this->assertSame(['started', 'completed'], $sequence);
    }

    // ------------------------------------------------------------------
    // Event properties
    // ------------------------------------------------------------------

    #[Test]
    public function started_event_has_readonly_properties(): void
    {
        $order = Order::create(['status' => 'pending']);
        $audit = Audit::where('event', 'created')->first();

        $event = new RollbackStarted($audit, true);

        $this->assertSame($audit->id, $event->audit->id);
        $this->assertTrue($event->dryRun);
    }

    #[Test]
    public function completed_event_has_readonly_properties(): void
    {
        $order = Order::create(['status' => 'pending']);
        $audit = Audit::where('event', 'created')->first();

        $event = new RollbackCompleted($audit, ['action' => 'delete']);

        $this->assertSame($audit->id, $event->audit->id);
        $this->assertSame(['action' => 'delete'], $event->result);
    }
}
