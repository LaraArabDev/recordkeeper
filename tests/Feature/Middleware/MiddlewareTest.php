<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Feature;

use Illuminate\Support\Facades\Route;
use LaraArabDev\Recordkeeper\Http\Middleware\AuditRoute;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Feature tests for AuditRoute middleware option parsing and sampling behavior.
 */
#[Group('middleware')]
#[CoversClass(AuditRoute::class)]
class MiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware([AuditRoute::class.':tag=payments'])->get('/test-tag', fn () => response()->json(['ok' => true]));
        Route::middleware([AuditRoute::class.':body=true,tag=checkout'])->post('/test-body-tag', fn () => response()->json(['ok' => true]));
        Route::middleware([AuditRoute::class.':sample=1.0'])->get('/test-sample-full', fn () => response()->json(['ok' => true]));
        Route::middleware([AuditRoute::class])->get('/test-disabled', fn () => response()->json(['ok' => true]));
    }

    #[Test]
    public function tag_option_is_stored_in_audit(): void
    {
        $this->get('/test-tag')->assertOk();

        $audit = Audit::where('event', 'route.get')->first();
        $this->assertNotNull($audit);
        $this->assertStringContainsString('payments', $audit->tags ?? '');
    }

    #[Test]
    public function body_and_tag_options_combined(): void
    {
        $this->post('/test-body-tag', ['item' => 'widget'])->assertOk();

        $audit = Audit::where('event', 'route.post')->first();
        $this->assertNotNull($audit);
        $this->assertSame('widget', $audit->new_values['item'] ?? null);
        $this->assertStringContainsString('checkout', $audit->tags ?? '');
    }

    #[Test]
    public function sample_full_records_every_request(): void
    {
        $this->get('/test-sample-full')->assertOk();
        $this->get('/test-sample-full')->assertOk();

        $this->assertSame(2, Audit::where('event', 'route.get')->count());
    }

    #[Test]
    public function disabled_kill_switch_skips_audit(): void
    {
        config(['recordkeeper.enabled' => false]);

        $this->get('/test-disabled')->assertOk();

        $this->assertDatabaseCount('audits', 0);
    }

    #[Test]
    public function parse_options_returns_defaults_for_empty_options(): void
    {
        $middleware = app(AuditRoute::class);
        $parseOptions = new \ReflectionMethod($middleware, 'parseOptions');
        $parseOptions->setAccessible(true);

        $result = $parseOptions->invoke($middleware, []);

        $this->assertSame(null, $result['tag']);
        $this->assertFalse($result['body']);
        $this->assertSame(1.0, $result['sample']);
    }

    #[Test]
    public function parse_options_parses_all_keys(): void
    {
        $middleware = app(AuditRoute::class);
        $parseOptions = new \ReflectionMethod($middleware, 'parseOptions');
        $parseOptions->setAccessible(true);

        $result = $parseOptions->invoke($middleware, ['tag=finance', 'body=true', 'sample=0.5']);

        $this->assertSame('finance', $result['tag']);
        $this->assertTrue($result['body']);
        $this->assertSame(0.5, $result['sample']);
    }

    #[Test]
    public function parse_options_ignores_options_without_equals(): void
    {
        $middleware = app(AuditRoute::class);
        $parseOptions = new \ReflectionMethod($middleware, 'parseOptions');
        $parseOptions->setAccessible(true);

        $result = $parseOptions->invoke($middleware, ['invalid', 'tag=audit']);

        $this->assertSame('audit', $result['tag']);
        $this->assertFalse($result['body']);
    }
}
