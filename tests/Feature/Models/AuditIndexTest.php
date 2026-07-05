<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Tests\Fixtures\Order;
use LaraArabDev\Recordkeeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('models')]
#[CoversClass(Audit::class)]
final class AuditIndexTest extends TestCase
{
    #[Test]
    public function audits_table_has_deleted_at_index(): void
    {
        $this->assertIndexExists('audits', 'deleted_at');
    }

    #[Test]
    public function audits_table_has_user_id_index(): void
    {
        $this->assertIndexExists('audits', 'user_id');
    }

    #[Test]
    public function audits_table_has_batch_id_event_composite_index(): void
    {
        $this->assertCompositeIndexExists('audits', ['batch_id', 'event']);
    }

    #[Test]
    public function audits_table_has_event_created_at_composite_index(): void
    {
        $this->assertCompositeIndexExists('audits', ['event', 'created_at']);
    }

    #[Test]
    public function audits_table_has_auditable_type_id_created_at_composite_index(): void
    {
        $this->assertCompositeIndexExists('audits', ['auditable_type', 'auditable_id', 'created_at']);
    }

    #[Test]
    public function audits_table_has_source_index(): void
    {
        $this->assertIndexExists('audits', 'source');
    }

    #[Test]
    public function audits_table_has_auditable_event_composite_index(): void
    {
        $this->assertCompositeIndexExists('audits', ['auditable_type', 'auditable_id', 'event']);
    }

    #[Test]
    public function audit_tag_table_has_tag_index(): void
    {
        $this->assertIndexExists('audit_tag', 'tag');
    }

    #[Test]
    public function audit_tag_table_has_audit_id_tag_composite_index(): void
    {
        $this->assertCompositeIndexExists('audit_tag', ['audit_id', 'tag']);
    }

    #[Test]
    public function actor_only_query_returns_correct_results(): void
    {
        Order::create(['status' => 'a']);
        Order::create(['status' => 'b']);

        // Set user_id on one audit
        Audit::first()->update(['user_id' => 42]);

        $results = Audit::where('user_id', 42)->get();

        $this->assertCount(1, $results);
    }

    #[Test]
    public function model_history_query_with_latest_ordering_works(): void
    {
        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);
        $order->update(['status' => 'delivered']);

        $history = Audit::where('auditable_type', Order::class)
            ->where('auditable_id', $order->id)
            ->orderByDesc('created_at')
            ->get();

        $this->assertCount(3, $history);
        $this->assertSame('updated', $history->first()->event);
    }

    #[Test]
    public function batch_event_filtered_query_works(): void
    {
        Order::create(['status' => 'pending']);
        Order::create(['status' => 'shipped']);

        $batchId = 'test-batch-123';
        Audit::first()->update(['batch_id' => $batchId]);

        $results = Audit::where('batch_id', $batchId)
            ->whereIn('event', ['created', 'updated'])
            ->get();

        $this->assertCount(1, $results);
    }

    /**
     * Assert that a single-column index exists on the given table.
     */
    private function assertIndexExists(string $table, string $column): void
    {
        $indexes = Schema::getIndexes($table);

        $found = collect($indexes)->contains(function (array $index) use ($column): bool {
            return in_array($column, $index['columns'], true);
        });

        $this->assertTrue($found, "Expected index on [{$table}.{$column}] to exist.");
    }

    /**
     * Assert that a composite index exists on the given table with the exact columns.
     *
     * @param  list<string>  $columns
     */
    private function assertCompositeIndexExists(string $table, array $columns): void
    {
        $indexes = Schema::getIndexes($table);

        $found = collect($indexes)->contains(function (array $index) use ($columns): bool {
            return $index['columns'] === $columns;
        });

        $this->assertTrue($found, "Expected composite index on [{$table}.(".implode(', ', $columns).')] to exist.');
    }
}
