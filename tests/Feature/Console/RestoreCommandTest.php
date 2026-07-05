<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Feature\Console;

use LaraArabDev\Recordkeeper\Support\AttributeResolver;
use LaraArabDev\Recordkeeper\Tests\Fixtures\Order;
use LaraArabDev\Recordkeeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('console')]
class RestoreCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AttributeResolver::clearCache();
    }

    #[Test]
    public function restore_deleted_model(): void
    {
        $order = Order::create(['status' => 'pending', 'total' => 50]);
        $orderId = $order->id;
        $order->delete();

        $this->assertSoftDeleted('orders', ['id' => $orderId]);

        $this->artisan('recordkeeper:restore', [
            'model' => 'Order',
            'id' => (string) $orderId,
            '--yes' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'deleted_at' => null,
        ]);
    }

    #[Test]
    public function restore_not_found(): void
    {
        $this->artisan('recordkeeper:restore', [
            'model' => 'Order',
            'id' => '999',
        ])
            ->expectsOutputToContain('No deletion audit found')
            ->assertExitCode(1);
    }

    #[Test]
    public function restore_dry_run(): void
    {
        $order = Order::create(['status' => 'pending']);
        $orderId = $order->id;
        $order->delete();

        $this->artisan('recordkeeper:restore', [
            'model' => 'Order',
            'id' => (string) $orderId,
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('Dry-run')
            ->assertExitCode(0);

        $this->assertSoftDeleted('orders', ['id' => $orderId]);
    }
}
