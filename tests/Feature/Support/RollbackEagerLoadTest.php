<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Feature;

use Illuminate\Support\Facades\DB;
use LaraArabDev\Recordkeeper\Facades\Recordkeeper;
use LaraArabDev\Recordkeeper\Support\AttributeResolver;
use LaraArabDev\Recordkeeper\Support\Rollback;
use LaraArabDev\Recordkeeper\Tests\Fixtures\Order;
use LaraArabDev\Recordkeeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('support')]
#[CoversClass(Rollback::class)]
final class RollbackEagerLoadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AttributeResolver::clearCache();
        config(['recordkeeper.rollback.enabled' => true]);
        config(['recordkeeper.rollback.track' => false]);
    }

    #[Test]
    public function revert_batch_eager_loads_auditable_to_avoid_n_plus_one(): void
    {
        // Create multiple orders in a batch
        Recordkeeper::batch('eager-batch', function (): void {
            Order::create(['status' => 'a']);
            Order::create(['status' => 'b']);
            Order::create(['status' => 'c']);
        });

        // Count the queries during rollback
        $queryLog = [];
        DB::listen(function ($query) use (&$queryLog): void {
            $queryLog[] = $query->sql;
        });

        app(Rollback::class)->revertBatch('eager-batch', dryRun: true);

        // Should have a single query fetching audits with eager-loaded auditable,
        // NOT one query per audit for auditable
        $auditQueries = array_filter($queryLog, fn ($sql) => str_contains($sql, 'audits'));
        $orderQueries = array_filter($queryLog, fn ($sql) => str_contains($sql, 'orders'));

        // With eager loading, there should be at most 1 query for orders (the eager-load)
        // Without eager loading, there would be 3 separate queries
        $this->assertLessThanOrEqual(1, count($orderQueries));
    }
}
