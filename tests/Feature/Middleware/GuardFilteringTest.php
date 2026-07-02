<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Feature;

use Illuminate\Support\Facades\Route;
use LaraArabDev\Recordkeeper\Http\Middleware\AuditApi;
use LaraArabDev\Recordkeeper\Http\Middleware\AuditRoute;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Support\AuditQuery;
use LaraArabDev\Recordkeeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Guard is stored as a dedicated indexed column — verify it is:
 *  a) Written correctly by each middleware
 *  b) Queryable efficiently via AuditQuery::guard() and Audit::forGuard()
 */
#[Group('middleware')]
#[CoversClass(AuditRoute::class)]
#[CoversClass(AuditApi::class)]
class GuardFilteringTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(AuditRoute::class)->get('/web-endpoint', fn () => response()->json(['ok' => true]));
        Route::middleware(AuditApi::class)->get('/api-endpoint', fn () => response()->json(['ok' => true]));
    }

    #[Test]
    public function web_middleware_writes_guard_column_as_web(): void
    {
        $this->get('/web-endpoint');

        $audit = Audit::first();
        $this->assertNotNull($audit);
        $this->assertSame('web', $audit->guard);
    }

    #[Test]
    public function api_middleware_writes_guard_column_as_api(): void
    {
        $this->get('/api-endpoint');

        $audit = Audit::first();
        $this->assertNotNull($audit);
        $this->assertSame('api', $audit->guard);
    }

    #[Test]
    public function guard_not_stored_inside_context_json(): void
    {
        $this->get('/web-endpoint');

        $audit = Audit::first();
        // guard should be the column, NOT duplicated inside context
        $this->assertArrayNotHasKey('guard', (array) ($audit->context ?? []));
    }

    #[Test]
    public function audit_query_guard_filters_by_indexed_column(): void
    {
        $this->get('/web-endpoint');
        $this->get('/api-endpoint');

        $webAudits = (new AuditQuery)->guard('web')->builder()->get();
        $apiAudits = (new AuditQuery)->guard('api')->builder()->get();

        $this->assertCount(1, $webAudits);
        $this->assertCount(1, $apiAudits);
        $this->assertSame('web', $webAudits->first()->guard);
        $this->assertSame('api', $apiAudits->first()->guard);
    }

    #[Test]
    public function model_scope_for_guard_returns_correct_records(): void
    {
        $this->get('/web-endpoint');
        $this->get('/api-endpoint');

        $this->assertCount(1, Audit::forGuard('web')->get());
        $this->assertCount(1, Audit::forGuard('api')->get());
        $this->assertCount(0, Audit::forGuard('admin')->get());
    }

    #[Test]
    public function multiple_guards_can_coexist_in_same_table(): void
    {
        $this->get('/web-endpoint');
        $this->get('/api-endpoint');
        $this->get('/web-endpoint');

        $this->assertCount(2, Audit::forGuard('web')->get());
        $this->assertCount(1, Audit::forGuard('api')->get());
    }
}
