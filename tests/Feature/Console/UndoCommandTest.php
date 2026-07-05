<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Feature\Console;

use LaraArabDev\Recordkeeper\Support\AttributeResolver;
use LaraArabDev\Recordkeeper\Tests\Fixtures\Order;
use LaraArabDev\Recordkeeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('console')]
class UndoCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AttributeResolver::clearCache();
    }

    #[Test]
    public function undo_last_change(): void
    {
        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        $this->artisan('recordkeeper:undo', ['--yes' => true])
            ->assertExitCode(0);

        $this->assertSame('pending', $order->fresh()->status);
    }

    #[Test]
    public function undo_last_n_changes(): void
    {
        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'processing']);
        $order->update(['status' => 'shipped']);

        $this->artisan('recordkeeper:undo', ['n' => '2', '--yes' => true])
            ->assertExitCode(0);

        $this->assertSame('pending', $order->fresh()->status);
    }

    #[Test]
    public function undo_scoped_to_model(): void
    {
        $order1 = Order::create(['status' => 'a']);
        $order2 = Order::create(['status' => 'b']);
        $order1->update(['status' => 'changed1']);
        $order2->update(['status' => 'changed2']);

        // Undo only the latest 1 for Order model — should undo order2's update
        $this->artisan('recordkeeper:undo', [
            'n' => '1',
            '--model' => 'Order',
            '--yes' => true,
        ])->assertExitCode(0);

        $this->assertSame('changed1', $order1->fresh()->status);
        $this->assertSame('b', $order2->fresh()->status);
    }

    #[Test]
    public function undo_dry_run(): void
    {
        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        $this->artisan('recordkeeper:undo', ['--dry-run' => true])
            ->expectsOutputToContain('Dry-run')
            ->assertExitCode(0);

        $this->assertSame('shipped', $order->fresh()->status);
    }

    #[Test]
    public function undo_no_audits(): void
    {
        $this->artisan('recordkeeper:undo', ['--yes' => true])
            ->expectsOutputToContain('No rollbackable audits found')
            ->assertExitCode(0);
    }
}
