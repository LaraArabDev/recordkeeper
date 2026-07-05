<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Feature\Console;

use LaraArabDev\Recordkeeper\Facades\Recordkeeper;
use LaraArabDev\Recordkeeper\Support\AttributeResolver;
use LaraArabDev\Recordkeeper\Tests\Fixtures\Order;
use LaraArabDev\Recordkeeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('console')]
class RollbackFilteredTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AttributeResolver::clearCache();
    }

    #[Test]
    public function rollback_by_tag(): void
    {
        Recordkeeper::withTags(['finance']);
        $order = Order::create(['status' => 'pending', 'total' => 100]);
        $order->update(['status' => 'shipped']);

        $this->artisan('recordkeeper:rollback', [
            '--tag' => 'finance',
            '--event' => ['updated'],
            '--yes' => true,
        ])->assertExitCode(0);

        $this->assertSame('pending', $order->fresh()->status);
    }

    #[Test]
    public function rollback_by_model(): void
    {
        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        $this->artisan('recordkeeper:rollback', [
            '--model' => 'Order',
            '--event' => ['updated'],
            '--yes' => true,
        ])->assertExitCode(0);

        $this->assertSame('pending', $order->fresh()->status);
    }

    #[Test]
    public function rollback_by_model_id_requires_model(): void
    {
        $this->artisan('recordkeeper:rollback', [
            '--model-id' => '1',
        ])->assertExitCode(1);
    }

    #[Test]
    public function rollback_by_model_and_model_id(): void
    {
        $order1 = Order::create(['status' => 'a']);
        $order2 = Order::create(['status' => 'b']);
        $order1->update(['status' => 'changed']);
        $order2->update(['status' => 'changed']);

        $this->artisan('recordkeeper:rollback', [
            '--model' => 'Order',
            '--model-id' => (string) $order1->id,
            '--event' => ['updated'],
            '--yes' => true,
        ])->assertExitCode(0);

        $this->assertSame('a', $order1->fresh()->status);
        $this->assertSame('changed', $order2->fresh()->status);
    }

    #[Test]
    public function rollback_filtered_dry_run(): void
    {
        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        $this->artisan('recordkeeper:rollback', [
            '--model' => 'Order',
            '--event' => ['updated'],
            '--dry-run' => true,
        ])->assertExitCode(0);

        $this->assertSame('shipped', $order->fresh()->status);
    }

    #[Test]
    public function rollback_filtered_no_matches(): void
    {
        $this->artisan('recordkeeper:rollback', [
            '--model' => 'NonExistent',
        ])->assertExitCode(0);
    }

    #[Test]
    public function rollback_by_event_filter(): void
    {
        $order = Order::create(['status' => 'pending']);

        $this->artisan('recordkeeper:rollback', [
            '--model' => 'Order',
            '--event' => ['created'],
            '--yes' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseCount('orders', 0);
    }

    #[Test]
    public function rollback_with_date_range(): void
    {
        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        $this->artisan('recordkeeper:rollback', [
            '--model' => 'Order',
            '--event' => ['updated'],
            '--since' => '-1 hour',
            '--yes' => true,
        ])->assertExitCode(0);

        $this->assertSame('pending', $order->fresh()->status);
    }
}
