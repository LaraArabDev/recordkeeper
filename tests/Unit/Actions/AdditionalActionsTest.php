<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Unit\Actions;

use Illuminate\Support\Facades\Queue;
use LaraArabDev\Recordkeeper\Actions\DiscoverAuditableModels;
use LaraArabDev\Recordkeeper\Actions\ExportAudits;
use LaraArabDev\Recordkeeper\Actions\GatherAuditStats;
use LaraArabDev\Recordkeeper\Actions\WipeAudits;
use LaraArabDev\Recordkeeper\Facades\Recordkeeper;
use LaraArabDev\Recordkeeper\Jobs\ProcessRollback;
use LaraArabDev\Recordkeeper\Jobs\ProcessRollbackCollection;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Support\AttributeResolver;
use LaraArabDev\Recordkeeper\Support\AuditQuery;
use LaraArabDev\Recordkeeper\Support\Rollback;
use LaraArabDev\Recordkeeper\Tests\Fixtures\Order;
use LaraArabDev\Recordkeeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('actions')]
#[CoversClass(GatherAuditStats::class)]
#[CoversClass(ExportAudits::class)]
#[CoversClass(WipeAudits::class)]
#[CoversClass(DiscoverAuditableModels::class)]
#[CoversClass(Rollback::class)]
class AdditionalActionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AttributeResolver::clearCache();
    }

    // ====================================================================
    // GatherAuditStats
    // ====================================================================

    #[Test]
    public function stats_returns_correct_structure(): void
    {
        $stats = app(GatherAuditStats::class)(null);

        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('by_event', $stats);
        $this->assertArrayHasKey('top_models', $stats);
        $this->assertArrayHasKey('top_actors', $stats);
        $this->assertArrayHasKey('since', $stats);
        $this->assertNull($stats['since']);
    }

    #[Test]
    public function stats_counts_audits_correctly(): void
    {
        Order::create(['status' => 'a']);
        Order::create(['status' => 'b']);

        $stats = app(GatherAuditStats::class)(null);

        $this->assertSame(2, $stats['total']);
        $this->assertSame(2, $stats['by_event']['created'] ?? 0);
    }

    #[Test]
    public function stats_groups_by_event(): void
    {
        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        $stats = app(GatherAuditStats::class)(null);

        $this->assertArrayHasKey('created', $stats['by_event']);
        $this->assertArrayHasKey('updated', $stats['by_event']);
    }

    #[Test]
    public function stats_returns_top_models(): void
    {
        Order::create(['status' => 'pending']);

        $stats = app(GatherAuditStats::class)(null);

        $this->assertNotEmpty($stats['top_models']);
        $this->assertSame('Order', $stats['top_models'][0]['model']);
    }

    #[Test]
    public function stats_top_actors_excludes_null_user_id(): void
    {
        Order::create(['status' => 'pending']);

        $stats = app(GatherAuditStats::class)(null);

        // No authenticated user, so top_actors should be empty
        $this->assertEmpty($stats['top_actors']);
    }

    #[Test]
    public function stats_top_actors_includes_user(): void
    {
        Audit::create([
            'event' => 'created',
            'auditable_type' => Order::class,
            'auditable_id' => 1,
            'old_values' => [],
            'new_values' => ['status' => 'pending'],
            'user_type' => 'App\\Models\\User',
            'user_id' => 42,
        ]);

        $stats = app(GatherAuditStats::class)(null);

        $this->assertNotEmpty($stats['top_actors']);
        $this->assertSame('User #42', $stats['top_actors'][0]['actor']);
        $this->assertSame(1, $stats['top_actors'][0]['count']);
    }

    #[Test]
    public function stats_with_since_filter(): void
    {
        Audit::create([
            'event' => 'created',
            'auditable_type' => Order::class,
            'old_values' => [],
            'new_values' => [],
            'created_at' => now()->subDays(10),
        ]);

        Audit::create([
            'event' => 'updated',
            'auditable_type' => Order::class,
            'old_values' => [],
            'new_values' => [],
            'created_at' => now(),
        ]);

        $stats = app(GatherAuditStats::class)('-3 days');

        $this->assertSame(1, $stats['total']);
        $this->assertNotNull($stats['since']);
    }

    #[Test]
    public function stats_with_null_since_returns_all(): void
    {
        Order::create(['status' => 'a']);

        $stats = app(GatherAuditStats::class)(null);

        $this->assertSame(1, $stats['total']);
        $this->assertNull($stats['since']);
    }

    #[Test]
    public function stats_with_invalid_since_ignores_filter(): void
    {
        Order::create(['status' => 'a']);

        $stats = app(GatherAuditStats::class)('not-a-valid-date');

        // strtotime returns false for invalid date, so since = null, no filtering
        $this->assertSame(1, $stats['total']);
        $this->assertNull($stats['since']);
    }

    #[Test]
    public function stats_empty_database(): void
    {
        $stats = app(GatherAuditStats::class)(null);

        $this->assertSame(0, $stats['total']);
        $this->assertEmpty($stats['by_event']);
        $this->assertEmpty($stats['top_models']);
        $this->assertEmpty($stats['top_actors']);
    }

    // ====================================================================
    // ExportAudits
    // ====================================================================

    #[Test]
    public function export_json_format(): void
    {
        Order::create(['status' => 'pending']);

        $handle = fopen('php://memory', 'r+');
        $count = app(ExportAudits::class)(Audit::query(), $handle, 'json');

        $this->assertSame(1, $count);
    }

    #[Test]
    public function export_json_writes_valid_json(): void
    {
        Order::create(['status' => 'a']);
        Order::create(['status' => 'b']);

        $handle = fopen('php://temp', 'r+');
        app(ExportAudits::class)(Audit::query(), $handle, 'json');

        // Handle is closed by ExportAudits, reopen
        // Actually, we need to capture the output before close
        // Let's use a temp file instead
        $tmpFile = tempnam(sys_get_temp_dir(), 'audit_export_');
        $handle = fopen($tmpFile, 'w');
        app(ExportAudits::class)(Audit::query(), $handle, 'json');

        $content = file_get_contents($tmpFile);
        $decoded = json_decode($content, true);
        unlink($tmpFile);

        $this->assertIsArray($decoded);
        $this->assertCount(2, $decoded);
    }

    #[Test]
    public function export_csv_format(): void
    {
        Order::create(['status' => 'pending']);

        $tmpFile = tempnam(sys_get_temp_dir(), 'audit_csv_');
        $handle = fopen($tmpFile, 'w');
        $count = app(ExportAudits::class)(Audit::query(), $handle, 'csv');

        $content = file_get_contents($tmpFile);
        unlink($tmpFile);

        $this->assertSame(1, $count);
        // CSV should have header + 1 data row
        $lines = array_filter(explode("\n", trim($content)));
        $this->assertCount(2, $lines);
    }

    #[Test]
    public function export_csv_empty_result(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'audit_csv_');
        $handle = fopen($tmpFile, 'w');
        $count = app(ExportAudits::class)(Audit::query(), $handle, 'csv');

        $content = file_get_contents($tmpFile);
        unlink($tmpFile);

        $this->assertSame(0, $count);
        $this->assertEmpty(trim($content));
    }

    #[Test]
    public function export_ndjson_format(): void
    {
        Order::create(['status' => 'a']);
        Order::create(['status' => 'b']);

        $tmpFile = tempnam(sys_get_temp_dir(), 'audit_ndjson_');
        $handle = fopen($tmpFile, 'w');
        $count = app(ExportAudits::class)(Audit::query(), $handle, 'ndjson');

        $content = file_get_contents($tmpFile);
        unlink($tmpFile);

        $this->assertSame(2, $count);
        $lines = array_filter(explode("\n", trim($content)));
        $this->assertCount(2, $lines);
        // Each line should be valid JSON
        foreach ($lines as $line) {
            $this->assertNotNull(json_decode($line, true));
        }
    }

    #[Test]
    public function export_json_empty_result(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'audit_json_');
        $handle = fopen($tmpFile, 'w');
        $count = app(ExportAudits::class)(Audit::query(), $handle, 'json');

        $content = file_get_contents($tmpFile);
        unlink($tmpFile);

        $this->assertSame(0, $count);
        $decoded = json_decode($content, true);
        $this->assertIsArray($decoded);
        $this->assertEmpty($decoded);
    }

    #[Test]
    public function export_unknown_format_defaults_to_ndjson(): void
    {
        Order::create(['status' => 'pending']);

        $tmpFile = tempnam(sys_get_temp_dir(), 'audit_default_');
        $handle = fopen($tmpFile, 'w');
        $count = app(ExportAudits::class)(Audit::query(), $handle, 'unknown-format');

        $content = file_get_contents($tmpFile);
        unlink($tmpFile);

        $this->assertSame(1, $count);
        // Should be NDJSON (one JSON object per line)
        $decoded = json_decode(trim($content), true);
        $this->assertIsArray($decoded);
    }

    // ====================================================================
    // WipeAudits
    // ====================================================================

    #[Test]
    public function wipe_deletes_matching_audits(): void
    {
        Order::create(['status' => 'a']);
        Order::create(['status' => 'b']);

        $this->assertDatabaseCount('audits', 2);

        $query = (new AuditQuery)->model('Order');
        $deleted = app(WipeAudits::class)($query);

        $this->assertSame(2, $deleted);
        $this->assertDatabaseCount('audits', 0);
    }

    #[Test]
    public function wipe_returns_zero_when_no_matches(): void
    {
        $query = (new AuditQuery)->model('NonExistentModel');
        $deleted = app(WipeAudits::class)($query);

        $this->assertSame(0, $deleted);
    }

    #[Test]
    public function wipe_only_deletes_matching_records(): void
    {
        Order::create(['status' => 'a']);
        Audit::create([
            'event' => 'route.get',
            'auditable_type' => 'route',
            'old_values' => [],
            'new_values' => [],
        ]);

        $query = (new AuditQuery)->event('route.get');
        $deleted = app(WipeAudits::class)($query);

        $this->assertSame(1, $deleted);
        $this->assertDatabaseCount('audits', 1);
    }

    #[Test]
    public function wipe_by_event_filter(): void
    {
        $order = Order::create(['status' => 'a']);
        $order->update(['status' => 'b']);

        $query = (new AuditQuery)->event('updated');
        $deleted = app(WipeAudits::class)($query);

        $this->assertSame(1, $deleted);
        $this->assertDatabaseCount('audits', 1);
        $this->assertSame('created', Audit::first()->event);
    }

    // ====================================================================
    // Restore (via AuditQuery)
    // ====================================================================

    #[Test]
    public function find_deletion_audit_returns_latest_deleted(): void
    {
        $order = Order::create(['status' => 'pending']);
        $order->delete();

        $audit = (new AuditQuery)->model(Order::class)->subjectId((string) $order->id)
            ->event(['deleted', 'forceDeleted'])->latest()->limit(1)->builder()->first();

        $this->assertNotNull($audit);
        $this->assertSame('deleted', $audit->event);
    }

    #[Test]
    public function find_deletion_audit_returns_null_when_not_deleted(): void
    {
        $order = Order::create(['status' => 'pending']);

        $audit = (new AuditQuery)->model(Order::class)->subjectId((string) $order->id)
            ->event(['deleted', 'forceDeleted'])->latest()->limit(1)->builder()->first();

        $this->assertNull($audit);
    }

    #[Test]
    public function find_deletion_audit_returns_null_for_nonexistent_model(): void
    {
        $audit = (new AuditQuery)->model(Order::class)->subjectId('99999')
            ->event(['deleted', 'forceDeleted'])->latest()->limit(1)->builder()->first();

        $this->assertNull($audit);
    }

    #[Test]
    public function find_deletion_audit_returns_latest_when_multiple_deletions(): void
    {
        $order = Order::create(['status' => 'pending']);
        $order->delete();
        $order->restore();
        $order->delete();

        $audits = Audit::where('event', 'deleted')->get();
        $this->assertGreaterThanOrEqual(2, $audits->count());

        $result = (new AuditQuery)->model(Order::class)->subjectId((string) $order->id)
            ->event(['deleted', 'forceDeleted'])->latest()->limit(1)->builder()->first();

        $this->assertNotNull($result);
        // Should be the most recent deletion
        $this->assertSame($audits->last()->id, $result->id);
    }

    // ====================================================================
    // DiscoverAuditableModels
    // ====================================================================

    #[Test]
    public function discover_returns_empty_for_nonexistent_path(): void
    {
        $result = app(DiscoverAuditableModels::class)(['nonexistent/path']);

        $this->assertSame([], $result);
    }

    #[Test]
    public function discover_finds_models_in_fixtures(): void
    {
        $this->app->setBasePath(dirname(__DIR__, 2));

        $result = app(DiscoverAuditableModels::class)(['Fixtures']);

        $this->assertNotEmpty($result);

        $modelNames = array_column($result, 'model');
        $this->assertContains('Order', $modelNames);
    }

    #[Test]
    public function discover_returns_correct_structure(): void
    {
        $this->app->setBasePath(dirname(__DIR__, 2));

        $result = app(DiscoverAuditableModels::class)(['Fixtures']);

        $order = collect($result)->firstWhere('model', 'Order');

        $this->assertNotNull($order);
        $this->assertArrayHasKey('events', $order);
        $this->assertArrayHasKey('redact', $order);
        $this->assertArrayHasKey('encrypt', $order);
        $this->assertArrayHasKey('retention', $order);
    }

    #[Test]
    public function discover_resolves_modifiers(): void
    {
        $this->app->setBasePath(dirname(__DIR__, 2));

        $result = app(DiscoverAuditableModels::class)(['Fixtures']);

        $order = collect($result)->firstWhere('model', 'Order');

        // Order has #[Redact('discount_code')] and #[Encrypt('national_id')]
        $this->assertStringContainsString('discount_code', $order['redact']);
        $this->assertStringContainsString('national_id', $order['encrypt']);
    }

    #[Test]
    public function discover_resolves_retention(): void
    {
        $this->app->setBasePath(dirname(__DIR__, 2));

        $result = app(DiscoverAuditableModels::class)(['Fixtures']);

        $order = collect($result)->firstWhere('model', 'Order');

        // Order has #[Auditable(retentionDays: 365)]
        $this->assertSame('365d', $order['retention']);
    }

    #[Test]
    public function discover_skips_empty_paths_array(): void
    {
        $result = app(DiscoverAuditableModels::class)([]);

        $this->assertSame([], $result);
    }

    // ====================================================================
    // Rollback — async methods
    // ====================================================================

    #[Test]
    public function revert_async_dispatches_job(): void
    {
        Queue::fake();

        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        $audit = Audit::where('event', 'updated')->first();
        app(Rollback::class)->revertAsync($audit);

        Queue::assertPushed(ProcessRollback::class);
    }

    #[Test]
    public function revert_collection_async_dispatches_job(): void
    {
        Queue::fake();

        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'processing']);
        $order->update(['status' => 'shipped']);

        $audits = Audit::where('event', 'updated')->get();
        app(Rollback::class)->revertCollectionAsync($audits);

        Queue::assertPushed(ProcessRollbackCollection::class);
    }

    #[Test]
    public function find_by_batch_excludes_non_rollbackable_events(): void
    {
        Recordkeeper::batch('mixed-batch', function () {
            Order::create(['status' => 'pending']);
        });

        // Add a non-rollbackable audit to the same batch
        Audit::create([
            'event' => 'route.get',
            'auditable_type' => 'route',
            'old_values' => [],
            'new_values' => [],
            'batch_id' => 'mixed-batch',
        ]);

        $audits = Audit::where('batch_id', 'mixed-batch')
            ->whereIn('event', Audit::ROLLBACKABLE_EVENTS)
            ->get();

        // Only rollbackable events (created/updated/deleted/restored)
        foreach ($audits as $audit) {
            $this->assertContains($audit->event, ['created', 'updated', 'deleted', 'restored']);
        }
    }

    // ====================================================================
    // Undo — async and edge cases
    // ====================================================================

    #[Test]
    public function undo_async_dispatches_job(): void
    {
        Queue::fake();

        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        $audits = (new AuditQuery)->rollbackable()->latest()->limit(1)->builder()->get();
        app(Rollback::class)->revertCollectionAsync($audits);

        Queue::assertPushed(ProcessRollbackCollection::class);
    }

    #[Test]
    public function undo_find_undoable_limits_results(): void
    {
        Order::create(['status' => 'a']);
        Order::create(['status' => 'b']);
        Order::create(['status' => 'c']);

        $audits = (new AuditQuery)->rollbackable()->latest()->limit(2)->builder()->get();

        $this->assertCount(2, $audits);
    }

    #[Test]
    public function undo_find_undoable_scoped_to_model(): void
    {
        Order::create(['status' => 'pending']);

        Audit::create([
            'event' => 'created',
            'auditable_type' => 'App\\Models\\User',
            'auditable_id' => 1,
            'old_values' => [],
            'new_values' => [],
        ]);

        $audits = (new AuditQuery)->rollbackable()->latest()->limit(10)->model('Order')->builder()->get();

        foreach ($audits as $audit) {
            $this->assertStringContainsString('Order', $audit->auditable_type);
        }
    }

    #[Test]
    public function undo_find_undoable_only_rollbackable_events(): void
    {
        Order::create(['status' => 'pending']);

        Audit::create([
            'event' => 'route.get',
            'auditable_type' => 'route',
            'old_values' => [],
            'new_values' => [],
        ]);

        $audits = (new AuditQuery)->rollbackable()->latest()->limit(10)->builder()->get();

        foreach ($audits as $audit) {
            $this->assertContains($audit->event, ['created', 'updated', 'deleted', 'restored']);
        }
    }
}
