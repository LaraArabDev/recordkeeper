<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Unit\Actions;

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
class UndoChangesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AttributeResolver::clearCache();
    }

    #[Test]
    public function find_undoable_returns_latest_rollbackable_audits(): void
    {
        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        $audits = (new AuditQuery)->rollbackable()->latest()->limit(1)->builder()->get();

        $this->assertCount(1, $audits);
        $this->assertSame('updated', $audits->first()->event);
    }

    #[Test]
    public function find_undoable_scoped_to_model(): void
    {
        Order::create(['status' => 'a']);
        Order::create(['status' => 'b']);

        $audits = (new AuditQuery)->rollbackable()->latest()->limit(5)->model('Order')->builder()->get();

        foreach ($audits as $audit) {
            $this->assertStringContainsString('Order', $audit->auditable_type);
        }
    }

    #[Test]
    public function find_undoable_returns_empty_when_none_exist(): void
    {
        $audits = (new AuditQuery)->rollbackable()->latest()->limit(5)->builder()->get();

        $this->assertCount(0, $audits);
    }

    #[Test]
    public function revert_undoes_changes(): void
    {
        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        $audits = (new AuditQuery)->rollbackable()->latest()->limit(1)->builder()->get();
        $results = app(Rollback::class)->revertCollection($audits);

        $this->assertCount(1, $results);
        $this->assertSame('pending', $order->fresh()->status);
    }
}
