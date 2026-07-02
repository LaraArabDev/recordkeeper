<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Feature;

use LaraArabDev\Recordkeeper\Console\InstallCommand;
use LaraArabDev\Recordkeeper\Console\RollbackCommand;
use LaraArabDev\Recordkeeper\Console\SearchCommand;
use LaraArabDev\Recordkeeper\Console\ShowCommand;
use LaraArabDev\Recordkeeper\Console\StatsCommand;
use LaraArabDev\Recordkeeper\Console\SyncCommand;
use LaraArabDev\Recordkeeper\Facades\Recordkeeper;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Support\AttributeResolver;
use LaraArabDev\Recordkeeper\Tests\Fixtures\Order;
use LaraArabDev\Recordkeeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Feature tests for Artisan commands: search, show, tail, stats, install, and models.
 */
#[Group('console')]
#[CoversClass(SearchCommand::class)]
#[CoversClass(ShowCommand::class)]
#[CoversClass(RollbackCommand::class)]
#[CoversClass(StatsCommand::class)]
#[CoversClass(SyncCommand::class)]
#[CoversClass(InstallCommand::class)]
class CommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AttributeResolver::clearCache();
    }

    // ------------------------------------------------------------------
    // recordkeeper:search
    // ------------------------------------------------------------------

    #[Test]
    public function search_command_outputs_json(): void
    {
        Order::create(['status' => 'pending']);

        $this->artisan('recordkeeper:search', ['--json' => true, '--limit' => '10'])
            ->assertExitCode(0);
    }

    #[Test]
    public function search_command_filter_by_event(): void
    {
        Order::create(['status' => 'a']);
        $order = Order::create(['status' => 'b']);
        $order->update(['status' => 'c']);

        $this->artisan('recordkeeper:search', ['--event' => ['updated'], '--json' => true])
            ->assertExitCode(0);
    }

    #[Test]
    public function search_command_filter_by_guard(): void
    {
        Audit::create([
            'event' => 'route.get',
            'auditable_type' => 'route',
            'old_values' => [],
            'new_values' => [],
            'guard' => 'api',
        ]);

        $this->artisan('recordkeeper:search', ['--guard' => 'api', '--json' => true])
            ->assertExitCode(0);
    }

    #[Test]
    public function search_command_returns_empty_json_array_when_no_results(): void
    {
        $this->artisan('recordkeeper:search', ['--json' => true])
            ->expectsOutputToContain('[]')
            ->assertExitCode(0);
    }

    // ------------------------------------------------------------------
    // recordkeeper:show
    // ------------------------------------------------------------------

    #[Test]
    public function show_command_displays_audit(): void
    {
        Order::create(['status' => 'pending']);
        $audit = Audit::first();

        $this->artisan('recordkeeper:show', ['id' => $audit->id])
            ->expectsOutputToContain("Audit #{$audit->id}")
            ->expectsOutputToContain('Event:')
            ->expectsOutputToContain('Changes:')
            ->expectsOutputToContain('system')
            ->expectsOutputToContain('n/a')
            ->assertExitCode(0);
    }

    #[Test]
    public function show_command_json_output(): void
    {
        Order::create(['status' => 'pending']);
        $audit = Audit::first();

        $this->artisan('recordkeeper:show', ['id' => $audit->id, '--json' => true])
            ->doesntExpectOutputToContain('Changes:')
            ->assertExitCode(0);
    }

    #[Test]
    public function show_command_fails_for_unknown_id(): void
    {
        $this->artisan('recordkeeper:show', ['id' => 99999])
            ->expectsOutputToContain('not found')
            ->assertExitCode(1);
    }

    // ------------------------------------------------------------------
    // recordkeeper:rollback
    // ------------------------------------------------------------------

    #[Test]
    public function rollback_command_dry_run_does_not_modify(): void
    {
        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        $audit = Audit::where('event', 'updated')->first();

        $this->artisan('recordkeeper:rollback', [
            'id' => $audit->id,
            '--dry-run' => true,
        ])->assertExitCode(0);

        $this->assertSame('shipped', $order->fresh()->status);
    }

    #[Test]
    public function rollback_command_applies_with_yes_flag(): void
    {
        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        $audit = Audit::where('event', 'updated')->first();

        $this->artisan('recordkeeper:rollback', [
            'id' => $audit->id,
            '--yes' => true,
        ])->assertExitCode(0);

        $this->assertSame('pending', $order->fresh()->status);
    }

    #[Test]
    public function rollback_command_fails_for_missing_id(): void
    {
        $this->artisan('recordkeeper:rollback')
            ->assertExitCode(1);
    }

    #[Test]
    public function rollback_command_batch_mode(): void
    {
        Recordkeeper::batch('cmd-batch', function (): void {
            Order::create(['status' => 'x']);
            Order::create(['status' => 'y']);
        });

        $this->artisan('recordkeeper:rollback', ['--batch' => 'cmd-batch', '--yes' => true])
            ->assertExitCode(0);

        $this->assertDatabaseCount('orders', 0);
    }

    // ------------------------------------------------------------------
    // recordkeeper:stats
    // ------------------------------------------------------------------

    #[Test]
    public function stats_command_outputs_successfully(): void
    {
        Order::create(['status' => 'a']);
        $o = Order::create(['status' => 'b']);
        $o->update(['status' => 'c']);

        $this->artisan('recordkeeper:stats')
            ->assertExitCode(0);
    }

    #[Test]
    public function stats_command_json_output(): void
    {
        Order::create(['status' => 'a']);

        $this->artisan('recordkeeper:stats', ['--json' => true])
            ->assertExitCode(0);
    }

    // ------------------------------------------------------------------
    // recordkeeper:models (sync)
    // ------------------------------------------------------------------

    #[Test]
    public function models_command_runs_successfully(): void
    {
        $this->artisan('recordkeeper:models')
            ->assertExitCode(0);
    }

    #[Test]
    public function search_command_table_format(): void
    {
        Order::create(['status' => 'pending']);

        $this->artisan('recordkeeper:search', ['--format' => 'table', '--limit' => '10'])
            ->assertExitCode(0);
    }

    #[Test]
    public function search_command_csv_format(): void
    {
        Order::create(['status' => 'pending']);

        $this->artisan('recordkeeper:search', ['--format' => 'csv', '--limit' => '10'])
            ->assertExitCode(0);
    }

    #[Test]
    public function search_command_since_option(): void
    {
        Order::create(['status' => 'pending']);

        $this->artisan('recordkeeper:search', ['--since' => '-7 days', '--json' => true])
            ->assertExitCode(0);
    }

    #[Test]
    public function search_command_page_option(): void
    {
        for ($i = 0; $i < 5; $i++) {
            Order::create(['status' => "s{$i}"]);
        }

        $this->artisan('recordkeeper:search', ['--limit' => '2', '--page' => '2', '--json' => true])
            ->assertExitCode(0);
    }

    #[Test]
    public function search_command_empty_result_non_json(): void
    {
        $this->artisan('recordkeeper:search', ['--format' => 'table'])
            ->expectsOutputToContain('No audit records found')
            ->assertExitCode(0);
    }

    #[Test]
    public function search_command_filter_by_batch(): void
    {
        Recordkeeper::batch('test-batch', fn () => Order::create(['status' => 'batched']));

        $this->artisan('recordkeeper:search', ['--batch' => 'test-batch', '--json' => true])
            ->assertExitCode(0);
    }

    #[Test]
    public function search_command_filter_by_tag(): void
    {
        Order::create(['status' => 'ok']);

        $this->artisan('recordkeeper:search', ['--tag' => 'finance', '--json' => true])
            ->assertExitCode(0);
    }

    #[Test]
    public function search_command_filter_by_model(): void
    {
        Order::create(['status' => 'ok']);

        $this->artisan('recordkeeper:search', ['--model' => 'Order', '--json' => true])
            ->assertExitCode(0);
    }

    // ------------------------------------------------------------------
    // recordkeeper:models --json
    // ------------------------------------------------------------------

    #[Test]
    public function models_command_json_output(): void
    {
        $this->artisan('recordkeeper:models', ['--json' => true])
            ->assertExitCode(0);
    }

    // ------------------------------------------------------------------
    // recordkeeper:install
    // ------------------------------------------------------------------

    #[Test]
    public function install_command_runs_without_errors(): void
    {
        $this->artisan('recordkeeper:install')
            ->assertExitCode(0);
    }
}
