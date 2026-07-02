<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Feature;

use Illuminate\Support\Facades\DB;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Support\AttributeResolver;
use LaraArabDev\Recordkeeper\Support\AuditQuery;
use LaraArabDev\Recordkeeper\Tests\Fixtures\Order;
use LaraArabDev\Recordkeeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Feature tests for query performance: indexed filters and diff-only writes.
 */
#[Group('support')]
#[CoversClass(AttributeResolver::class)]
#[CoversClass(AuditQuery::class)]
class PerformanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AttributeResolver::clearCache();
    }

    #[Test]
    public function attribute_resolver_caches_result_per_class(): void
    {
        $reflectionCount = 0;

        // First resolution — does reflection
        $config1 = AttributeResolver::resolve(Order::class);

        // Second resolution — must return the same cached object (no new reflection)
        $config2 = AttributeResolver::resolve(Order::class);

        $this->assertSame($config1, $config2, 'AttributeResolver must return the same cached AuditConfig instance');
    }

    #[Test]
    public function attribute_resolver_clear_cache_forces_re_resolution(): void
    {
        $config1 = AttributeResolver::resolve(Order::class);
        AttributeResolver::clearCache();
        $config2 = AttributeResolver::resolve(Order::class);

        // Different instance after clearing
        $this->assertNotSame($config1, $config2);
        // But same values
        $this->assertSame($config1->auditEvents, $config2->auditEvents);
    }

    #[Test]
    public function audit_list_query_has_no_n_plus_one(): void
    {
        // Create several audits
        Order::create(['status' => 'a']);
        Order::create(['status' => 'b']);
        Order::create(['status' => 'c']);

        $queryCount = 0;
        DB::listen(function () use (&$queryCount): void {
            $queryCount++;
        });

        // AuditQuery eager-loads 'auditable' — should be 1 main query + 1 eager load = 2 max
        $audits = (new AuditQuery)->model('Order')->builder()->get();

        // Access the relationship on each item — must not fire extra queries
        foreach ($audits as $audit) {
            $_ = $audit->auditable;
        }

        $this->assertLessThanOrEqual(2, $queryCount, 'List query should use at most 2 queries (main + eager load)');
    }

    #[Test]
    public function multiple_model_resolutions_are_constant_time(): void
    {
        // Resolve the same class 100 times — only the first should do reflection
        $start = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            AttributeResolver::resolve(Order::class);
        }
        $elapsed = microtime(true) - $start;

        // 100 cached resolutions must complete in < 10ms total
        $this->assertLessThan(0.01, $elapsed, '100 cached AttributeResolver resolutions should complete in < 10ms');
    }

    #[Test]
    public function guard_filter_uses_indexed_column_not_json(): void
    {
        // Insert some route audits
        for ($i = 0; $i < 5; $i++) {
            Audit::create([
                'event' => 'route.get',
                'auditable_type' => 'route',
                'old_values' => [],
                'new_values' => [],
                'guard' => $i % 2 === 0 ? 'web' : 'api',
            ]);
        }

        $queries = [];
        DB::listen(function ($q) use (&$queries): void {
            $queries[] = $q->sql;
        });

        Audit::forGuard('api')->get();

        // The SQL must reference the `guard` column directly, not JSON_EXTRACT
        $sql = implode(' ', $queries);
        $this->assertStringContainsString('"guard"', $sql);
        $this->assertStringNotContainsString('JSON_EXTRACT', $sql);
        $this->assertStringNotContainsString('json_extract', $sql);
    }
}
