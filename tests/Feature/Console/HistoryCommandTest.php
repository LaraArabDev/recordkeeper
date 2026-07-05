<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Feature\Console;

use LaraArabDev\Recordkeeper\Support\AttributeResolver;
use LaraArabDev\Recordkeeper\Tests\Fixtures\Order;
use LaraArabDev\Recordkeeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('console')]
class HistoryCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AttributeResolver::clearCache();
    }

    #[Test]
    public function displays_timeline_for_model_instance(): void
    {
        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'processing']);
        $order->update(['status' => 'shipped']);

        $this->artisan('recordkeeper:history', [
            'model' => 'Order',
            'id' => (string) $order->id,
        ])
            ->expectsOutputToContain('History for Order')
            ->expectsOutputToContain('created')
            ->assertExitCode(0);
    }

    #[Test]
    public function outputs_json_format(): void
    {
        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        $this->artisan('recordkeeper:history', [
            'model' => 'Order',
            'id' => (string) $order->id,
            '--format' => 'json',
        ])->assertExitCode(0);
    }

    #[Test]
    public function shows_warning_for_empty_result(): void
    {
        $this->artisan('recordkeeper:history', [
            'model' => 'Order',
            'id' => '999',
        ])
            ->expectsOutputToContain('No audit history found')
            ->assertExitCode(0);
    }

    #[Test]
    public function respects_limit_option(): void
    {
        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'a']);
        $order->update(['status' => 'b']);
        $order->update(['status' => 'c']);

        $this->artisan('recordkeeper:history', [
            'model' => 'Order',
            'id' => (string) $order->id,
            '--limit' => '2',
            '--format' => 'json',
        ])->assertExitCode(0);
    }
}
