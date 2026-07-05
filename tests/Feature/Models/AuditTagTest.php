<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Feature;

use Illuminate\Support\Facades\DB;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Models\AuditTag;
use LaraArabDev\Recordkeeper\Recordkeeper;
use LaraArabDev\Recordkeeper\Support\AuditQuery;
use LaraArabDev\Recordkeeper\Tests\Fixtures\Order;
use LaraArabDev\Recordkeeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('models')]
#[CoversClass(Audit::class)]
#[CoversClass(AuditTag::class)]
#[CoversClass(AuditQuery::class)]
final class AuditTagTest extends TestCase
{
    // ------------------------------------------------------------------
    // booted() hook — pivot sync on audit creation
    // ------------------------------------------------------------------

    #[Test]
    public function creating_audit_with_tags_syncs_to_pivot_table(): void
    {
        $audit = Audit::create([
            'event' => 'updated',
            'auditable_type' => 'system',
            'old_values' => [],
            'new_values' => [],
            'tags' => 'finance,billing',
        ]);

        $this->assertDatabaseCount('audit_tag', 2);
        $this->assertDatabaseHas('audit_tag', ['audit_id' => $audit->id, 'tag' => 'finance']);
        $this->assertDatabaseHas('audit_tag', ['audit_id' => $audit->id, 'tag' => 'billing']);
    }

    #[Test]
    public function creating_audit_without_tags_creates_no_pivot_rows(): void
    {
        Audit::create([
            'event' => 'created',
            'auditable_type' => 'system',
            'old_values' => [],
            'new_values' => [],
        ]);

        $this->assertDatabaseCount('audit_tag', 0);
    }

    #[Test]
    public function creating_audit_with_empty_tags_string_creates_no_pivot_rows(): void
    {
        Audit::create([
            'event' => 'created',
            'auditable_type' => 'system',
            'old_values' => [],
            'new_values' => [],
            'tags' => '',
        ]);

        $this->assertDatabaseCount('audit_tag', 0);
    }

    #[Test]
    public function tags_with_whitespace_are_trimmed_in_pivot(): void
    {
        $audit = Audit::create([
            'event' => 'updated',
            'auditable_type' => 'system',
            'old_values' => [],
            'new_values' => [],
            'tags' => ' finance , billing ',
        ]);

        $tags = AuditTag::where('audit_id', $audit->id)->pluck('tag')->sort()->values()->all();
        $this->assertSame(['billing', 'finance'], $tags);
    }

    #[Test]
    public function model_audit_with_tags_syncs_to_pivot(): void
    {
        $manager = app(Recordkeeper::class);
        $manager->withTags(['order', 'important']);

        Order::create(['status' => 'pending']);

        $audit = Audit::where('event', 'created')->first();
        $this->assertNotNull($audit->tags);

        $pivotTags = AuditTag::where('audit_id', $audit->id)->pluck('tag')->sort()->values()->all();
        $this->assertSame(['important', 'order'], $pivotTags);

        $manager->withTags([]);
    }

    // ------------------------------------------------------------------
    // auditTags() relationship
    // ------------------------------------------------------------------

    #[Test]
    public function audit_tags_relationship_returns_tag_models(): void
    {
        $audit = Audit::create([
            'event' => 'updated',
            'auditable_type' => 'system',
            'old_values' => [],
            'new_values' => [],
            'tags' => 'alpha,beta',
        ]);

        $tags = $audit->auditTags;
        $this->assertCount(2, $tags);
        $this->assertContainsOnlyInstancesOf(AuditTag::class, $tags);
    }

    #[Test]
    public function deleting_audit_cascades_to_pivot_tags(): void
    {
        // Enable foreign key constraints for SQLite
        DB::statement('PRAGMA foreign_keys = ON');

        $audit = Audit::create([
            'event' => 'updated',
            'auditable_type' => 'system',
            'old_values' => [],
            'new_values' => [],
            'tags' => 'temp',
        ]);

        $this->assertDatabaseCount('audit_tag', 1);

        $audit->forceDelete();

        $this->assertDatabaseCount('audit_tag', 0);
    }

    // ------------------------------------------------------------------
    // scopeForTag
    // ------------------------------------------------------------------

    #[Test]
    public function scope_for_tag_filters_by_exact_tag(): void
    {
        Audit::create([
            'event' => 'created',
            'auditable_type' => 'system',
            'old_values' => [],
            'new_values' => [],
            'tags' => 'billing',
        ]);

        Audit::create([
            'event' => 'updated',
            'auditable_type' => 'system',
            'old_values' => [],
            'new_values' => [],
            'tags' => 'bill',
        ]);

        $results = Audit::forTag('billing')->get();

        $this->assertCount(1, $results);
        $this->assertSame('created', $results->first()->event);
    }

    #[Test]
    public function scope_for_tag_does_not_match_substrings(): void
    {
        Audit::create([
            'event' => 'created',
            'auditable_type' => 'system',
            'old_values' => [],
            'new_values' => [],
            'tags' => 'billing',
        ]);

        $results = Audit::forTag('bill')->get();

        $this->assertCount(0, $results);
    }

    // ------------------------------------------------------------------
    // scopeForSource
    // ------------------------------------------------------------------

    #[Test]
    public function scope_for_source_filters_by_source_column(): void
    {
        Audit::create([
            'event' => 'job.processed',
            'auditable_type' => 'job',
            'old_values' => [],
            'new_values' => [],
            'source' => 'App\\Jobs\\SendEmail',
        ]);

        Audit::create([
            'event' => 'job.processed',
            'auditable_type' => 'job',
            'old_values' => [],
            'new_values' => [],
            'source' => 'App\\Jobs\\ProcessPayment',
        ]);

        $results = Audit::forSource('App\\Jobs\\SendEmail')->get();

        $this->assertCount(1, $results);
    }

    // ------------------------------------------------------------------
    // AuditQuery::tag() — exact match via pivot (no false positives)
    // ------------------------------------------------------------------

    #[Test]
    public function audit_query_tag_uses_exact_match_via_pivot(): void
    {
        Audit::create([
            'event' => 'created',
            'auditable_type' => 'system',
            'old_values' => [],
            'new_values' => [],
            'tags' => 'billing',
        ]);

        Audit::create([
            'event' => 'updated',
            'auditable_type' => 'system',
            'old_values' => [],
            'new_values' => [],
            'tags' => 'bill',
        ]);

        $results = (new AuditQuery)->tag('billing')->builder()->get();

        $this->assertCount(1, $results);
        $this->assertSame('created', $results->first()->event);
    }

    #[Test]
    public function audit_query_tag_array_requires_all_tags(): void
    {
        Audit::create([
            'event' => 'created',
            'auditable_type' => 'system',
            'old_values' => [],
            'new_values' => [],
            'tags' => 'finance,billing',
        ]);

        Audit::create([
            'event' => 'updated',
            'auditable_type' => 'system',
            'old_values' => [],
            'new_values' => [],
            'tags' => 'finance',
        ]);

        $results = (new AuditQuery)->tag(['finance', 'billing'])->builder()->get();

        $this->assertCount(1, $results);
        $this->assertSame('created', $results->first()->event);
    }

    // ------------------------------------------------------------------
    // AuditQuery::source()
    // ------------------------------------------------------------------

    #[Test]
    public function audit_query_source_filters_by_source_column(): void
    {
        Audit::create([
            'event' => 'command.finished',
            'auditable_type' => 'command',
            'old_values' => [],
            'new_values' => [],
            'source' => 'cache:clear',
        ]);

        Audit::create([
            'event' => 'command.finished',
            'auditable_type' => 'command',
            'old_values' => [],
            'new_values' => [],
            'source' => 'migrate',
        ]);

        $results = (new AuditQuery)->source('cache:clear')->builder()->get();

        $this->assertCount(1, $results);
    }

    // ------------------------------------------------------------------
    // Source populated in Recordkeeper::log()
    // ------------------------------------------------------------------

    #[Test]
    public function log_with_subject_sets_source_to_class_name(): void
    {
        $order = Order::create(['status' => 'pending']);
        Audit::query()->delete();

        $audit = app(Recordkeeper::class)
            ->log('order.flagged', $order);

        $this->assertSame(Order::class, $audit->source);
    }

    #[Test]
    public function log_without_subject_sets_source_to_null(): void
    {
        $audit = app(Recordkeeper::class)
            ->log('system.backup');

        $this->assertNull($audit->source);
    }
}
