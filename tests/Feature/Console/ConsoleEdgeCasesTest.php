<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Feature;

use LaraArabDev\Recordkeeper\Console\InstallCommand;
use LaraArabDev\Recordkeeper\Console\PruneCommand;
use LaraArabDev\Recordkeeper\Console\RollbackCommand;
use LaraArabDev\Recordkeeper\Console\ShowCommand;
use LaraArabDev\Recordkeeper\Console\StatsCommand;
use LaraArabDev\Recordkeeper\Console\SyncCommand;
use LaraArabDev\Recordkeeper\Facades\Recordkeeper;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Tests\Fixtures\Order;
use LaraArabDev\Recordkeeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Feature tests for console command edge cases: missing IDs, aborted rollbacks, and output formats.
 */
#[Group('console')]
#[CoversClass(RollbackCommand::class)]
#[CoversClass(PruneCommand::class)]
#[CoversClass(StatsCommand::class)]
#[CoversClass(InstallCommand::class)]
#[CoversClass(ShowCommand::class)]
#[CoversClass(SyncCommand::class)]
class ConsoleEdgeCasesTest extends TestCase
{
    // ------------------------------------------------------------------
    // RollbackCommand
    // ------------------------------------------------------------------

    #[Test]
    public function rollback_command_fails_for_non_rollbackable_audit(): void
    {
        Audit::create([
            'event' => 'route.get',
            'auditable_type' => 'route',
            'old_values' => [],
            'new_values' => [],
        ]);

        $audit = Audit::where('event', 'route.get')->first();

        $this->artisan('recordkeeper:rollback', ['id' => $audit->id, '--yes' => true])
            ->assertExitCode(1);
    }

    #[Test]
    public function rollback_command_batch_dry_run(): void
    {
        Recordkeeper::batch('dry-batch', fn () => Order::create(['status' => 'x']));

        $this->artisan('recordkeeper:rollback', ['--batch' => 'dry-batch', '--dry-run' => true])
            ->assertExitCode(0);

        $this->assertDatabaseCount('orders', 1);
    }

    #[Test]
    public function rollback_command_batch_empty_warns(): void
    {
        $this->artisan('recordkeeper:rollback', ['--batch' => 'nonexistent-batch', '--yes' => true])
            ->assertExitCode(0);
    }

    #[Test]
    public function rollback_command_dry_run_shows_preview_for_update(): void
    {
        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        $audit = Audit::where('event', 'updated')->first();

        $this->artisan('recordkeeper:rollback', ['id' => $audit->id, '--dry-run' => true])
            ->assertExitCode(0);

        $this->assertSame('shipped', $order->fresh()->status);
    }

    // ------------------------------------------------------------------
    // PruneCommand
    // ------------------------------------------------------------------

    #[Test]
    public function prune_command_with_yes_flag_deletes(): void
    {
        Audit::create([
            'event' => 'created',
            'auditable_type' => 'system',
            'old_values' => [],
            'new_values' => [],
            'created_at' => now()->subDays(400),
            'updated_at' => now()->subDays(400),
        ]);

        $this->artisan('recordkeeper:prune', ['--days' => '365', '--yes' => true])
            ->assertExitCode(0);

        $this->assertDatabaseCount('audits', 0);
    }

    #[Test]
    public function prune_command_dry_run_shows_count(): void
    {
        Audit::create([
            'event' => 'created',
            'auditable_type' => 'system',
            'old_values' => [],
            'new_values' => [],
            'created_at' => now()->subDays(400),
            'updated_at' => now()->subDays(400),
        ]);

        $this->artisan('recordkeeper:prune', ['--days' => '365', '--dry-run' => true])
            ->assertExitCode(0);

        $this->assertDatabaseCount('audits', 1);
    }

    // ------------------------------------------------------------------
    // StatsCommand
    // ------------------------------------------------------------------

    #[Test]
    public function stats_command_since_option(): void
    {
        Order::create(['status' => 'a']);

        $this->artisan('recordkeeper:stats', ['--since' => '-7 days'])
            ->assertExitCode(0);
    }

    // ------------------------------------------------------------------
    // InstallCommand
    // ------------------------------------------------------------------

    #[Test]
    public function install_command_force_flag(): void
    {
        $this->artisan('recordkeeper:install', ['--force' => true])
            ->assertExitCode(0);
    }

    // ------------------------------------------------------------------
    // ShowCommand
    // ------------------------------------------------------------------

    #[Test]
    public function show_command_displays_context_when_present(): void
    {
        $audit = Audit::create([
            'event' => 'route.get',
            'auditable_type' => 'route',
            'old_values' => [],
            'new_values' => [],
            'context' => ['route' => '/test', 'duration_ms' => 42, 'tags' => ['a', 'b']],
        ]);

        $this->artisan('recordkeeper:show', ['id' => $audit->id])
            ->expectsOutputToContain('Context:')
            ->expectsOutputToContain('/test')
            ->expectsOutputToContain('["a","b"]')
            ->assertExitCode(0);
    }

    #[Test]
    public function show_command_displays_batch_when_set(): void
    {
        $audit = Audit::create([
            'event' => 'updated',
            'auditable_type' => 'system',
            'old_values' => [],
            'new_values' => [],
            'batch_id' => 'show-batch',
        ]);

        $this->artisan('recordkeeper:show', ['id' => $audit->id])
            ->expectsOutputToContain('show-batch')
            ->assertExitCode(0);
    }

    // ------------------------------------------------------------------
    // SyncCommand (models) with real discovery path
    // ------------------------------------------------------------------

    #[Test]
    public function models_command_warns_when_path_not_found(): void
    {
        config(['recordkeeper.discovery.paths' => ['nonexistent/path']]);

        $this->artisan('recordkeeper:models')
            ->assertExitCode(0);
    }
}
