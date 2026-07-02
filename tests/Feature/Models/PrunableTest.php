<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Feature;

use Illuminate\Support\Carbon;
use LaraArabDev\Recordkeeper\Actions\PruneAudits;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Support\AttributeResolver;
use LaraArabDev\Recordkeeper\Tests\Fixtures\Order;
use LaraArabDev\Recordkeeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests for date-based pruning via MassPrunable.
 * laravel-auditing only supports count-based $auditThreshold — this is new.
 */
#[Group('models')]
#[CoversClass(Audit::class)]
#[CoversClass(PruneAudits::class)]
class PrunableTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AttributeResolver::clearCache();
    }

    #[Test]
    public function prunable_scope_returns_nothing_when_disabled(): void
    {
        config(['recordkeeper.retention.default_days' => 0]);

        Order::create(['status' => 'old']);
        Audit::query()->update(['created_at' => Carbon::now()->subDays(500)]);

        $prunable = (new Audit)->prunable()->get();

        $this->assertCount(0, $prunable);
    }

    #[Test]
    public function prunable_scope_matches_records_older_than_retention(): void
    {
        config(['recordkeeper.retention.default_days' => 30]);

        Order::create(['status' => 'old']);
        Audit::query()->update(['created_at' => Carbon::now()->subDays(60)]);

        $prunable = (new Audit)->prunable()->get();

        $this->assertGreaterThan(0, $prunable->count());
    }

    #[Test]
    public function prunable_scope_keeps_records_within_retention(): void
    {
        config(['recordkeeper.retention.default_days' => 365]);

        Order::create(['status' => 'recent']);
        // created_at is now — within retention

        $prunable = (new Audit)->prunable()->get();

        $this->assertCount(0, $prunable);
    }

    #[Test]
    public function prune_command_deletes_old_records(): void
    {
        Order::create(['status' => 'old']);
        Audit::query()->update(['created_at' => Carbon::now()->subDays(400)]);

        $this->artisan('recordkeeper:prune', ['--days' => 365, '--yes' => true])
            ->assertExitCode(0);

        $this->assertDatabaseCount('audits', 0);
    }

    #[Test]
    public function prune_command_dry_run_does_not_delete(): void
    {
        Order::create(['status' => 'old']);
        Audit::query()->update(['created_at' => Carbon::now()->subDays(400)]);

        $this->artisan('recordkeeper:prune', ['--days' => 365, '--dry-run' => true])
            ->assertExitCode(0);

        $this->assertDatabaseCount('audits', 1);
    }

    #[Test]
    public function prune_action_returns_deleted_count(): void
    {
        Order::create(['status' => 'a']);
        Order::create(['status' => 'b']);
        Audit::query()->update(['created_at' => Carbon::now()->subDays(400)]);

        $deleted = app(PruneAudits::class)(365, false);

        $this->assertSame(2, $deleted);
        $this->assertDatabaseCount('audits', 0);
    }

    #[Test]
    public function prune_action_dry_run_returns_count_without_deleting(): void
    {
        Order::create(['status' => 'a']);
        Audit::query()->update(['created_at' => Carbon::now()->subDays(400)]);

        $count = app(PruneAudits::class)(365, true);

        $this->assertSame(1, $count);
        $this->assertDatabaseCount('audits', 1);
    }

    #[Test]
    public function recent_records_not_pruned(): void
    {
        Order::create(['status' => 'old']);
        Order::create(['status' => 'new']);

        // Only make the first record old
        Audit::where('id', 1)->update(['created_at' => Carbon::now()->subDays(400)]);

        $deleted = app(PruneAudits::class)(365, false);

        $this->assertSame(1, $deleted);
        $this->assertDatabaseCount('audits', 1);
    }
}
