<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Unit\Actions;

use LaraArabDev\Recordkeeper\Actions\UndoChanges;
use LaraArabDev\Recordkeeper\Support\AttributeResolver;
use LaraArabDev\Recordkeeper\Tests\Fixtures\Order;
use LaraArabDev\Recordkeeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('actions')]
#[CoversClass(UndoChanges::class)]
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

        $undo = app(UndoChanges::class);
        $audits = $undo->findUndoable(1);

        $this->assertCount(1, $audits);
        $this->assertSame('updated', $audits->first()->event);
    }

    #[Test]
    public function find_undoable_scoped_to_model(): void
    {
        Order::create(['status' => 'a']);
        Order::create(['status' => 'b']);

        $undo = app(UndoChanges::class);
        $audits = $undo->findUndoable(5, 'Order');

        foreach ($audits as $audit) {
            $this->assertStringContainsString('Order', $audit->auditable_type);
        }
    }

    #[Test]
    public function find_undoable_returns_empty_when_none_exist(): void
    {
        $undo = app(UndoChanges::class);
        $audits = $undo->findUndoable(5);

        $this->assertCount(0, $audits);
    }

    #[Test]
    public function revert_undoes_changes(): void
    {
        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        $undo = app(UndoChanges::class);
        $audits = $undo->findUndoable(1);
        $results = $undo->revert($audits);

        $this->assertCount(1, $results);
        $this->assertSame('pending', $order->fresh()->status);
    }
}
