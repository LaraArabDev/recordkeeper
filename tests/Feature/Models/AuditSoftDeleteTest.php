<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Feature;

use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Support\AttributeResolver;
use LaraArabDev\Recordkeeper\Tests\Fixtures\Order;
use LaraArabDev\Recordkeeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('models')]
#[CoversClass(Audit::class)]
final class AuditSoftDeleteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AttributeResolver::clearCache();
    }

    #[Test]
    public function audit_model_uses_soft_deletes(): void
    {
        $this->assertTrue(
            method_exists(Audit::class, 'trashed'),
            'Audit model should use SoftDeletes trait',
        );
    }

    #[Test]
    public function deleting_audit_soft_deletes_it(): void
    {
        Order::create(['status' => 'pending']);

        $audit = Audit::first();
        $audit->delete();

        // Not visible in normal queries
        $this->assertNull(Audit::find($audit->id));

        // Still in database with deleted_at set
        $trashed = Audit::withTrashed()->find($audit->id);
        $this->assertNotNull($trashed);
        $this->assertNotNull($trashed->deleted_at);
    }

    #[Test]
    public function soft_deleted_audit_can_be_restored(): void
    {
        Order::create(['status' => 'pending']);

        $audit = Audit::first();
        $audit->delete();

        $trashed = Audit::withTrashed()->find($audit->id);
        $trashed->restore();

        $this->assertNotNull(Audit::find($audit->id));
        $this->assertNull(Audit::find($audit->id)->deleted_at);
    }

    #[Test]
    public function soft_deleted_audit_excluded_from_default_queries(): void
    {
        Order::create(['status' => 'a']);
        Order::create(['status' => 'b']);

        $this->assertSame(2, Audit::count());

        Audit::first()->delete();

        $this->assertSame(1, Audit::count());
        $this->assertSame(2, Audit::withTrashed()->count());
    }

    #[Test]
    public function force_delete_permanently_removes_audit(): void
    {
        Order::create(['status' => 'pending']);

        $audit = Audit::first();
        $audit->forceDelete();

        $this->assertNull(Audit::withTrashed()->find($audit->id));
    }

    #[Test]
    public function only_trashed_scope_returns_soft_deleted_audits(): void
    {
        Order::create(['status' => 'a']);
        Order::create(['status' => 'b']);

        Audit::first()->delete();

        $this->assertSame(1, Audit::onlyTrashed()->count());
    }

    #[Test]
    public function rollback_of_update_soft_deletes_audit_record(): void
    {
        config(['recordkeeper.rollback.enabled' => true]);
        config(['recordkeeper.rollback.track' => false]);

        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        $audit = Audit::where('event', 'updated')->first();
        $audit->rollback();

        // The update audit should be soft-deleted after rollback
        $this->assertNull(Audit::find($audit->id));
        $this->assertNotNull(Audit::withTrashed()->find($audit->id));
    }

    #[Test]
    public function pruning_force_deletes_audits(): void
    {
        config(['recordkeeper.retention.default_days' => 30]);

        Order::create(['status' => 'pending']);

        // Backdate the audit to be old enough for pruning
        Audit::query()->update(['created_at' => now()->subDays(60)]);

        $pruned = (new Audit)->pruneAll();

        $this->assertSame(1, $pruned);
        $this->assertSame(0, Audit::withTrashed()->count());
    }
}
