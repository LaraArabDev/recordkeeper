<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Feature;

use Illuminate\Support\Facades\Route;
use LaraArabDev\Recordkeeper\Http\Middleware\AuditApi;
use LaraArabDev\Recordkeeper\Http\Middleware\AuditRoute;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Feature tests for route-level auditing via AuditRoute and AuditApi middleware.
 */
#[Group('middleware')]
#[CoversClass(AuditRoute::class)]
#[CoversClass(AuditApi::class)]
class RouteAuditingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(AuditRoute::class)->get('/test-web', fn () => response()->json(['ok' => true]));
        Route::middleware([AuditApi::class])->get('/test-api', fn () => response()->json(['ok' => true]));
        Route::middleware([AuditRoute::class.':body=true'])->post('/test-body', fn () => response()->json(['ok' => true]));
        Route::middleware([AuditRoute::class.':sample=0'])->get('/test-sample', fn () => response()->json(['ok' => true]));
    }

    #[Test]
    public function web_route_hit_is_recorded_with_status(): void
    {
        $this->get('/test-web')->assertOk();

        $this->assertDatabaseCount('audits', 1);
        $audit = Audit::first();
        $this->assertSame('route.get', $audit->event);
        $this->assertSame(200, $audit->context['status'] ?? null);
        $this->assertSame('web', $audit->guard);
        $this->assertNotNull($audit->source);
    }

    #[Test]
    public function route_body_sensitive_fields_redacted(): void
    {
        $this->post('/test-body', ['name' => 'John', 'password' => 'secret123'])->assertOk();

        $audit = Audit::where('event', 'route.post')->first();
        $newValues = $audit->new_values ?? [];

        $this->assertSame('***', $newValues['password'] ?? null);
        $this->assertSame('John', $newValues['name'] ?? null);
    }

    #[Test]
    public function api_route_recorded_under_api_guard(): void
    {
        $this->get('/test-api')->assertOk();

        $audit = Audit::first();
        $this->assertSame('api', $audit->guard);
    }

    #[Test]
    public function sampling_skips_some_hits(): void
    {
        // sample=0 means never record
        $this->get('/test-sample')->assertOk();
        $this->get('/test-sample')->assertOk();
        $this->get('/test-sample')->assertOk();

        $this->assertDatabaseCount('audits', 0);
    }
}
