<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Feature;

use LaraArabDev\Recordkeeper\Facades\Recordkeeper;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Support\AttributeResolver;
use LaraArabDev\Recordkeeper\Tests\Fixtures\Order;
use LaraArabDev\Recordkeeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests for the local query scopes added to the Audit model.
 * All scopes hit indexed columns — guard, user_type, user_id, batch_id, event.
 */
#[Group('models')]
#[CoversClass(Audit::class)]
class ModelScopesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AttributeResolver::clearCache();
    }

    private function makeRouteAudit(string $guard, ?string $userType = null, ?int $userId = null, ?string $batch = null): Audit
    {
        $audit = new Audit;
        $audit->fill([
            'event' => 'route.get',
            'auditable_type' => 'route',
            'auditable_id' => null,
            'old_values' => [],
            'new_values' => [],
            'guard' => $guard,
            'user_type' => $userType,
            'user_id' => $userId,
            'batch_id' => $batch,
        ]);
        $audit->save();

        return $audit;
    }

    // ------------------------------------------------------------------
    // forGuard
    // ------------------------------------------------------------------

    #[Test]
    public function scope_for_guard_filters_by_guard_column(): void
    {
        $this->makeRouteAudit('web');
        $this->makeRouteAudit('api');
        $this->makeRouteAudit('admin');

        $this->assertCount(1, Audit::forGuard('web')->get());
        $this->assertCount(1, Audit::forGuard('api')->get());
        $this->assertCount(1, Audit::forGuard('admin')->get());
    }

    #[Test]
    public function scope_for_guard_returns_empty_for_unknown_guard(): void
    {
        $this->makeRouteAudit('web');

        $this->assertCount(0, Audit::forGuard('unknown')->get());
    }

    // ------------------------------------------------------------------
    // forModel
    // ------------------------------------------------------------------

    #[Test]
    public function scope_for_model_by_short_name(): void
    {
        Order::create(['status' => 'pending']);
        $this->makeRouteAudit('web');

        $results = Audit::forModel('Order')->get();

        $this->assertTrue($results->every(fn ($a) => $a->auditable_type === Order::class));
    }

    #[Test]
    public function scope_for_model_by_fqcn(): void
    {
        Order::create(['status' => 'pending']);

        $results = Audit::forModel(Order::class)->get();

        $this->assertGreaterThan(0, $results->count());
    }

    // ------------------------------------------------------------------
    // forSubject
    // ------------------------------------------------------------------

    #[Test]
    public function scope_for_subject_returns_only_that_record(): void
    {
        $a = Order::create(['status' => 'a']);
        $b = Order::create(['status' => 'b']);

        $resultsA = Audit::forSubject($a)->get();
        $resultsB = Audit::forSubject($b)->get();

        $this->assertTrue($resultsA->every(fn ($audit) => $audit->auditable_id == $a->id));
        $this->assertTrue($resultsB->every(fn ($audit) => $audit->auditable_id == $b->id));
    }

    // ------------------------------------------------------------------
    // forActorType
    // ------------------------------------------------------------------

    #[Test]
    public function scope_for_actor_type_by_short_name(): void
    {
        $this->makeRouteAudit('admin', 'App\\Models\\Admin', 1);
        $this->makeRouteAudit('web', 'App\\Models\\User', 2);

        $adminAudits = Audit::forActorType('Admin')->get();

        $this->assertGreaterThan(0, $adminAudits->count());
        $this->assertTrue($adminAudits->every(fn ($a) => str_contains((string) $a->user_type, 'Admin')));
    }

    #[Test]
    public function scope_for_actor_type_by_fqcn(): void
    {
        $this->makeRouteAudit('admin', 'App\\Models\\Admin', 1);

        $results = Audit::forActorType('App\\Models\\Admin')->get();

        $this->assertGreaterThan(0, $results->count());
    }

    // ------------------------------------------------------------------
    // forActor (polymorphic: model instance or id+type)
    // ------------------------------------------------------------------

    #[Test]
    public function scope_for_actor_with_model_instance(): void
    {
        $order = Order::create(['status' => 'pending']);

        // Manually craft an audit row where the actor is an Order (unusual but valid)
        $audit = new Audit;
        $audit->fill([
            'event' => 'custom.event',
            'auditable_type' => 'system',
            'auditable_id' => null,
            'old_values' => [],
            'new_values' => [],
            'user_type' => Order::class,
            'user_id' => $order->id,
        ]);
        $audit->save();

        $results = Audit::forActor($order)->get();

        $this->assertGreaterThan(0, $results->count());
        $this->assertTrue($results->every(
            fn ($a) => $a->user_type === Order::class && $a->user_id == $order->id
        ));
    }

    #[Test]
    public function scope_for_actor_with_id_only(): void
    {
        $this->makeRouteAudit('api', 'App\\Models\\User', 42);
        $this->makeRouteAudit('api', 'App\\Models\\User', 99);

        $results = Audit::forActor(42)->get();

        $this->assertTrue($results->every(fn ($a) => $a->user_id == 42));
    }

    // ------------------------------------------------------------------
    // forBatch
    // ------------------------------------------------------------------

    #[Test]
    public function scope_for_batch_returns_only_that_batch(): void
    {
        Recordkeeper::batch('import-x', function (): void {
            Order::create(['status' => 'x']);
            Order::create(['status' => 'x2']);
        });
        Order::create(['status' => 'no-batch']);

        $batchAudits = Audit::forBatch('import-x')->get();

        $this->assertCount(2, $batchAudits);
        $this->assertTrue($batchAudits->every(fn ($a) => $a->batch_id === 'import-x'));
    }

    // ------------------------------------------------------------------
    // rollbackable
    // ------------------------------------------------------------------

    #[Test]
    public function scope_rollbackable_excludes_route_events(): void
    {
        Order::create(['status' => 'pending']);
        $this->makeRouteAudit('web');

        $results = Audit::rollbackable()->get();

        $this->assertFalse($results->contains(fn ($a) => str_starts_with((string) $a->event, 'route.')));
        $this->assertTrue($results->contains(fn ($a) => $a->event === 'created'));
    }

    #[Test]
    public function scope_rollbackable_includes_model_events(): void
    {
        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'active']);
        $order->delete();

        $events = Audit::rollbackable()->pluck('event')->all();

        $this->assertContains('created', $events);
        $this->assertContains('updated', $events);
        $this->assertContains('deleted', $events);
    }

    // ------------------------------------------------------------------
    // routeHits
    // ------------------------------------------------------------------

    #[Test]
    public function scope_route_hits_returns_only_route_events(): void
    {
        Order::create(['status' => 'pending']);
        $this->makeRouteAudit('api');
        $this->makeRouteAudit('web');

        $results = Audit::routeHits()->get();

        $this->assertGreaterThan(0, $results->count());
        $this->assertTrue($results->every(fn ($a) => str_starts_with((string) $a->event, 'route.')));
    }

    // ------------------------------------------------------------------
    // Combining scopes
    // ------------------------------------------------------------------

    #[Test]
    public function scopes_are_composable(): void
    {
        $this->makeRouteAudit('api', 'App\\Models\\Admin', 7);
        $this->makeRouteAudit('api', 'App\\Models\\User', 8);
        $this->makeRouteAudit('web', 'App\\Models\\Admin', 7);

        // Only API calls made by Admin #7
        $results = Audit::forGuard('api')->forActorType('Admin')->get();

        $this->assertCount(1, $results);
        $this->assertSame('api', $results->first()->guard);
        $this->assertSame(7, (int) $results->first()->user_id);
    }
}
