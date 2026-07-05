<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Feature\Console;

use LaraArabDev\Recordkeeper\Facades\Recordkeeper;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Support\AttributeResolver;
use LaraArabDev\Recordkeeper\Tests\Fixtures\Order;
use LaraArabDev\Recordkeeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('console')]
class WipeCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AttributeResolver::clearCache();
    }

    #[Test]
    public function requires_at_least_one_filter(): void
    {
        $this->artisan('recordkeeper:wipe')
            ->expectsOutputToContain('At least one filter is required')
            ->assertExitCode(1);
    }

    #[Test]
    public function wipe_by_model(): void
    {
        Order::create(['status' => 'pending']);
        Order::create(['status' => 'shipped']);

        $countBefore = Audit::count();
        $this->assertGreaterThan(0, $countBefore);

        $this->artisan('recordkeeper:wipe', [
            '--model' => 'Order',
            '--yes' => true,
        ])
            ->expectsOutputToContain('Deleted')
            ->assertExitCode(0);

        $this->assertSame(0, Audit::count());
    }

    #[Test]
    public function wipe_by_event(): void
    {
        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        $totalBefore = Audit::count();
        $updatedCount = Audit::where('event', 'updated')->count();

        $this->artisan('recordkeeper:wipe', [
            '--event' => ['updated'],
            '--yes' => true,
        ])->assertExitCode(0);

        $this->assertSame($totalBefore - $updatedCount, Audit::count());
    }

    #[Test]
    public function wipe_dry_run(): void
    {
        Order::create(['status' => 'pending']);

        $countBefore = Audit::count();

        $this->artisan('recordkeeper:wipe', [
            '--model' => 'Order',
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('Dry-run')
            ->assertExitCode(0);

        $this->assertSame($countBefore, Audit::count());
    }

    #[Test]
    public function wipe_no_matches(): void
    {
        $this->artisan('recordkeeper:wipe', [
            '--model' => 'NonExistent',
            '--yes' => true,
        ])
            ->expectsOutputToContain('No audit records match')
            ->assertExitCode(0);
    }

    #[Test]
    public function wipe_by_tag(): void
    {
        Recordkeeper::withTags(['test-wipe']);
        Order::create(['status' => 'pending']);

        $this->assertGreaterThan(0, Audit::where('tags', 'like', '%test-wipe%')->count());

        $this->artisan('recordkeeper:wipe', [
            '--tag' => 'test-wipe',
            '--yes' => true,
        ])
            ->expectsOutputToContain('Deleted')
            ->assertExitCode(0);

        $this->assertSame(0, Audit::where('tags', 'like', '%test-wipe%')->count());
    }
}
