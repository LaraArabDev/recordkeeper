<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Unit;

use LaraArabDev\Recordkeeper\Facades\Recordkeeper;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Support\AttributeResolver;
use LaraArabDev\Recordkeeper\Support\AuditQuery;
use LaraArabDev\Recordkeeper\Tests\Fixtures\Order;
use LaraArabDev\Recordkeeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Unit tests for AuditQuery fluent builder: model, event, guard, actor, batch, tag, and pagination filters.
 */
#[Group('support')]
#[CoversClass(AuditQuery::class)]
class AuditQueryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AttributeResolver::clearCache();
    }

    private function seedAudits(): void
    {
        // model audit
        Recordkeeper::batch('batch-a', function (): void {
            Order::create(['status' => 'pending']);
        });

        $order = Order::create(['status' => 'active']);
        $order->update(['status' => 'shipped']);

        // route audit for web guard
        Audit::create([
            'event' => 'route.get',
            'auditable_type' => 'route',
            'auditable_id' => null,
            'old_values' => [],
            'new_values' => [],
            'guard' => 'web',
            'batch_id' => null,
        ]);

        // route audit for api guard
        Audit::create([
            'event' => 'route.post',
            'auditable_type' => 'route',
            'auditable_id' => null,
            'old_values' => [],
            'new_values' => [],
            'guard' => 'api',
            'user_type' => 'App\\Models\\Admin',
            'user_id' => 99,
            'batch_id' => 'batch-b',
        ]);
    }

    #[Test]
    public function model_filter_by_short_name(): void
    {
        $this->seedAudits();

        $results = (new AuditQuery)->model('Order')->builder()->get();

        $this->assertTrue($results->every(fn ($a) => $a->auditable_type === Order::class));
        $this->assertGreaterThan(0, $results->count());
    }

    #[Test]
    public function model_filter_by_fqcn(): void
    {
        $this->seedAudits();

        $results = (new AuditQuery)->model(Order::class)->builder()->get();

        $this->assertTrue($results->every(fn ($a) => $a->auditable_type === Order::class));
    }

    #[Test]
    public function event_filter_single(): void
    {
        $this->seedAudits();

        $results = (new AuditQuery)->event('updated')->builder()->get();

        $this->assertTrue($results->every(fn ($a) => $a->event === 'updated'));
    }

    #[Test]
    public function event_filter_multiple(): void
    {
        $this->seedAudits();

        $results = (new AuditQuery)->event(['created', 'updated'])->builder()->get();

        foreach ($results as $r) {
            $this->assertContains($r->event, ['created', 'updated']);
        }
    }

    #[Test]
    public function guard_filter_hits_indexed_column(): void
    {
        $this->seedAudits();

        $web = (new AuditQuery)->guard('web')->builder()->get();
        $api = (new AuditQuery)->guard('api')->builder()->get();

        $this->assertTrue($web->every(fn ($a) => $a->guard === 'web'));
        $this->assertTrue($api->every(fn ($a) => $a->guard === 'api'));
    }

    #[Test]
    public function guard_filter_excludes_other_guards(): void
    {
        $this->seedAudits();

        $webOnly = (new AuditQuery)->guard('web')->builder()->get();

        $this->assertFalse($webOnly->contains(fn ($a) => $a->guard === 'api'));
    }

    #[Test]
    public function actor_filter_by_id_only(): void
    {
        $this->seedAudits();

        $results = (new AuditQuery)->actor(99)->builder()->get();

        $this->assertTrue($results->every(fn ($a) => $a->user_id == 99));
    }

    #[Test]
    public function actor_filter_by_id_and_type_short_name(): void
    {
        $this->seedAudits();

        $results = (new AuditQuery)->actor(99, 'Admin')->builder()->get();

        $this->assertGreaterThan(0, $results->count());
        $this->assertTrue($results->every(fn ($a) => $a->user_id == 99));
    }

    #[Test]
    public function actor_filter_by_id_and_type_fqcn(): void
    {
        $this->seedAudits();

        $results = (new AuditQuery)->actor(99, 'App\\Models\\Admin')->builder()->get();

        $this->assertGreaterThan(0, $results->count());
    }

    #[Test]
    public function actor_type_filter_by_short_name(): void
    {
        $this->seedAudits();

        $results = (new AuditQuery)->actorType('Admin')->builder()->get();

        $this->assertTrue($results->every(fn ($a) => str_contains((string) $a->user_type, 'Admin')));
    }

    #[Test]
    public function batch_filter(): void
    {
        $this->seedAudits();

        $results = (new AuditQuery)->batch('batch-a')->builder()->get();

        $this->assertTrue($results->every(fn ($a) => $a->batch_id === 'batch-a'));
    }

    #[Test]
    public function rollbackable_excludes_route_events(): void
    {
        $this->seedAudits();

        $results = (new AuditQuery)->rollbackable()->builder()->get();

        $this->assertFalse($results->contains(fn ($a) => str_starts_with((string) $a->event, 'route.')));
    }

    #[Test]
    public function search_by_event_name(): void
    {
        $this->seedAudits();

        $results = (new AuditQuery)->search('updated')->builder()->get();

        $this->assertGreaterThan(0, $results->count());
    }

    #[Test]
    public function latest_orders_newest_first(): void
    {
        $this->seedAudits();

        $results = (new AuditQuery)->latest()->builder()->get();

        $timestamps = $results->pluck('created_at')->map(fn ($d) => $d->timestamp)->all();
        $sorted = $timestamps;
        rsort($sorted);

        $this->assertSame($sorted, $timestamps);
    }

    #[Test]
    public function limit_and_offset(): void
    {
        $this->seedAudits();

        $total = Audit::count();
        $this->assertGreaterThan(2, $total);

        $page1 = (new AuditQuery)->latest()->limit(2)->offset(0)->builder()->get();
        $page2 = (new AuditQuery)->latest()->limit(2)->offset(2)->builder()->get();

        $this->assertCount(2, $page1);
        $this->assertFalse($page1->pluck('id')->intersect($page2->pluck('id'))->isNotEmpty());
    }

    #[Test]
    public function subject_id_filter(): void
    {
        $this->seedAudits();
        $order = Order::first();

        $results = (new AuditQuery)->subjectId($order->id)->builder()->get();

        $this->assertGreaterThan(0, $results->count());
        $this->assertTrue($results->every(fn ($a) => $a->auditable_id == $order->id));
    }

    #[Test]
    public function only_authenticated_excludes_system_audits(): void
    {
        $this->seedAudits();

        $results = (new AuditQuery)->onlyAuthenticated()->builder()->get();

        $this->assertTrue($results->every(fn ($a) => $a->user_id !== null));
    }

    #[Test]
    public function between_date_range(): void
    {
        $this->seedAudits();

        $results = (new AuditQuery)
            ->between(now()->subMinute(), now()->addMinute())
            ->builder()
            ->get();

        $this->assertGreaterThan(0, $results->count());
    }

    #[Test]
    public function since_date_filter(): void
    {
        $this->seedAudits();

        $results = (new AuditQuery)->since(now()->subMinute())->builder()->get();

        $this->assertGreaterThan(0, $results->count());
    }

    #[Test]
    public function jobs_filter(): void
    {
        Audit::create([
            'event' => 'job.processed',
            'auditable_type' => 'job',
            'old_values' => [],
            'new_values' => [],
        ]);

        $results = (new AuditQuery)->jobs()->builder()->get();

        $this->assertGreaterThan(0, $results->count());
        $this->assertTrue($results->every(fn ($a) => str_starts_with($a->event, 'job.')));
    }

    #[Test]
    public function commands_filter(): void
    {
        Audit::create([
            'event' => 'command.finished',
            'auditable_type' => 'command',
            'old_values' => [],
            'new_values' => [],
        ]);

        $results = (new AuditQuery)->commands()->builder()->get();

        $this->assertGreaterThan(0, $results->count());
        $this->assertTrue($results->every(fn ($a) => str_starts_with($a->event, 'command.')));
    }

    #[Test]
    public function tag_filter_with_array(): void
    {
        $this->seedAudits();

        Audit::create([
            'event' => 'updated',
            'auditable_type' => 'system',
            'old_values' => [],
            'new_values' => [],
            'tags' => 'finance,billing',
        ]);

        $results = (new AuditQuery)->tag(['finance'])->builder()->get();

        $this->assertGreaterThan(0, $results->count());
        $this->assertTrue($results->every(fn ($a) => str_contains((string) $a->tags, 'finance')));
    }

    #[Test]
    public function actor_type_filter_by_fqcn(): void
    {
        $this->seedAudits();

        $results = (new AuditQuery)->actorType('App\\Models\\Admin')->builder()->get();

        $this->assertGreaterThan(0, $results->count());
        $this->assertTrue($results->every(fn ($a) => $a->user_type === 'App\\Models\\Admin'));
    }

    #[Test]
    public function events_filter(): void
    {
        Audit::create([
            'event' => 'event.user.registered',
            'auditable_type' => 'system',
            'old_values' => [],
            'new_values' => [],
        ]);

        Audit::create([
            'event' => 'updated',
            'auditable_type' => 'system',
            'old_values' => [],
            'new_values' => [],
        ]);

        $results = (new AuditQuery)->events()->builder()->get();

        $this->assertGreaterThan(0, $results->count());
        $this->assertTrue($results->every(fn ($a) => str_starts_with($a->event, 'event.')));
    }

    #[Test]
    public function tag_filter_matches_tag_in_middle_of_string(): void
    {
        Audit::create([
            'event' => 'updated',
            'auditable_type' => 'system',
            'old_values' => [],
            'new_values' => [],
            'tags' => 'finance,billing,accounting',
        ]);

        $results = (new AuditQuery)->tag(['billing'])->builder()->get();

        $this->assertGreaterThan(0, $results->count());
        $this->assertTrue($results->every(fn ($a) => str_contains((string) $a->tags, 'billing')));
    }
}
