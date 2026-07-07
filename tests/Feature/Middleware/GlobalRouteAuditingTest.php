<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Feature;

use Illuminate\Support\Facades\Route;
use LaraArabDev\Recordkeeper\Http\Middleware\AuditApi;
use LaraArabDev\Recordkeeper\Http\Middleware\AuditRoute;
use LaraArabDev\Recordkeeper\Http\Middleware\GlobalAuditApi;
use LaraArabDev\Recordkeeper\Http\Middleware\GlobalAuditRoute;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('middleware')]
#[CoversClass(GlobalAuditRoute::class)]
#[CoversClass(GlobalAuditApi::class)]
class GlobalRouteAuditingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('recordkeeper.routes.enabled', true);
    }

    #[Test]
    public function global_web_route_is_audited(): void
    {
        Route::middleware([GlobalAuditRoute::class])->get('/global-web', fn () => response()->json(['ok' => true]));

        $this->get('/global-web')->assertOk();

        $this->assertDatabaseCount('audits', 1);
        $audit = Audit::first();
        $this->assertSame('route.get', $audit->event);
        $this->assertSame('web', $audit->guard);
        $this->assertSame(200, $audit->context['status'] ?? null);
    }

    #[Test]
    public function global_api_route_is_audited(): void
    {
        Route::middleware([GlobalAuditApi::class])->get('/global-api', fn () => response()->json(['ok' => true]));

        $this->get('/global-api')->assertOk();

        $this->assertDatabaseCount('audits', 1);
        $audit = Audit::first();
        $this->assertSame('route.get', $audit->event);
        $this->assertSame('api', $audit->guard);
    }

    #[Test]
    public function excluded_paths_are_skipped(): void
    {
        config()->set('recordkeeper.routes.exclude', ['telescope/*', 'health']);

        Route::middleware([GlobalAuditRoute::class])->get('/telescope/requests', fn () => response()->json(['ok' => true]));
        Route::middleware([GlobalAuditRoute::class])->get('/health', fn () => response()->json(['ok' => true]));
        Route::middleware([GlobalAuditRoute::class])->get('/dashboard', fn () => response()->json(['ok' => true]));

        $this->get('/telescope/requests')->assertOk();
        $this->get('/health')->assertOk();
        $this->get('/dashboard')->assertOk();

        $this->assertDatabaseCount('audits', 1);
        $audit = Audit::first();
        $this->assertStringContainsString('dashboard', $audit->url);
    }

    #[Test]
    public function explicit_audit_middleware_prevents_double_audit(): void
    {
        Route::middleware([GlobalAuditRoute::class, AuditRoute::class])
            ->get('/explicit-web', fn () => response()->json(['ok' => true]));

        $this->get('/explicit-web')->assertOk();

        // Only the explicit AuditRoute should record — GlobalAuditRoute should skip
        $this->assertDatabaseCount('audits', 1);
    }

    #[Test]
    public function explicit_audit_api_middleware_prevents_double_audit(): void
    {
        Route::middleware([GlobalAuditApi::class, AuditApi::class])
            ->get('/explicit-api', fn () => response()->json(['ok' => true]));

        $this->get('/explicit-api')->assertOk();

        $this->assertDatabaseCount('audits', 1);
    }

    #[Test]
    public function explicit_audit_alias_prevents_double_audit(): void
    {
        Route::middleware([GlobalAuditRoute::class, 'audit'])
            ->get('/alias-web', fn () => response()->json(['ok' => true]));

        $this->get('/alias-web')->assertOk();

        $this->assertDatabaseCount('audits', 1);
    }

    #[Test]
    public function disabled_web_toggle_skips_web_routes(): void
    {
        config()->set('recordkeeper.routes.web', false);

        Route::middleware([GlobalAuditRoute::class])->get('/disabled-web', fn () => response()->json(['ok' => true]));

        $this->get('/disabled-web')->assertOk();

        $this->assertDatabaseCount('audits', 0);
    }

    #[Test]
    public function disabled_api_toggle_skips_api_routes(): void
    {
        config()->set('recordkeeper.routes.api', false);

        Route::middleware([GlobalAuditApi::class])->get('/disabled-api', fn () => response()->json(['ok' => true]));

        $this->get('/disabled-api')->assertOk();

        $this->assertDatabaseCount('audits', 0);
    }

    #[Test]
    public function global_body_config_captures_request_body(): void
    {
        config()->set('recordkeeper.routes.body', true);

        Route::middleware([GlobalAuditRoute::class])->post('/with-body', fn () => response()->json(['ok' => true]));

        $this->post('/with-body', ['name' => 'Salem'])->assertOk();

        $audit = Audit::first();
        $this->assertSame('Salem', $audit->new_values['name'] ?? null);
    }

    #[Test]
    public function global_sample_zero_skips_all(): void
    {
        config()->set('recordkeeper.routes.sample', 0.0);

        Route::middleware([GlobalAuditRoute::class])->get('/sampled', fn () => response()->json(['ok' => true]));

        $this->get('/sampled')->assertOk();
        $this->get('/sampled')->assertOk();
        $this->get('/sampled')->assertOk();

        $this->assertDatabaseCount('audits', 0);
    }

    #[Test]
    public function global_tag_is_applied(): void
    {
        config()->set('recordkeeper.routes.tag', 'global-audit');

        Route::middleware([GlobalAuditRoute::class])->get('/tagged', fn () => response()->json(['ok' => true]));

        $this->get('/tagged')->assertOk();

        $audit = Audit::first();
        $this->assertSame('global-audit', $audit->tags);
    }

    #[Test]
    public function disabled_by_default(): void
    {
        // Reset to default (false)
        config()->set('recordkeeper.routes.enabled', false);

        // Simulate what the service provider would do — it should NOT push middleware
        $router = $this->app['router'];
        $webMiddleware = $router->getMiddlewareGroups()['web'] ?? [];

        $this->assertNotContains(GlobalAuditRoute::class, $webMiddleware);
    }
}
