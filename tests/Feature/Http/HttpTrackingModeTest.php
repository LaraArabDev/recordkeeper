<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Feature\Http;

use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use LaraArabDev\Recordkeeper\DataObjects\HttpFilterConfig;
use LaraArabDev\Recordkeeper\Listeners\RecordOutboundHttp;
use LaraArabDev\Recordkeeper\Models\AuditHttpRequest;
use LaraArabDev\Recordkeeper\Support\HttpTracker;
use LaraArabDev\Recordkeeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Feature tests for HTTP tracking mode (auto/manual) and per-class filter config.
 */
#[Group('http')]
#[CoversClass(RecordOutboundHttp::class)]
#[CoversClass(HttpTracker::class)]
#[CoversClass(HttpFilterConfig::class)]
final class HttpTrackingModeTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('recordkeeper.http.enabled', true);
    }

    #[Test]
    public function auto_mode_tracks_everything_by_default(): void
    {
        config(['recordkeeper.http.mode' => 'auto']);

        $this->fireHttpEvent('https://api.stripe.com/v1/charges');

        $this->assertSame(1, AuditHttpRequest::count());
    }

    #[Test]
    public function manual_mode_blocks_when_no_filter_config(): void
    {
        config(['recordkeeper.http.mode' => 'manual']);

        // No context set on HttpTracker — should be blocked
        app(HttpTracker::class)->clearContext();

        $this->fireHttpEvent('https://api.stripe.com/v1/charges');

        $this->assertSame(0, AuditHttpRequest::count());
    }

    #[Test]
    public function manual_mode_blocks_when_context_set_without_filter_config(): void
    {
        config(['recordkeeper.http.mode' => 'manual']);

        // Context set but no filter config — should be blocked
        app(HttpTracker::class)->setContext(1);

        $this->fireHttpEvent('https://api.stripe.com/v1/charges');

        app(HttpTracker::class)->clearContext();

        $this->assertSame(0, AuditHttpRequest::count());
    }

    #[Test]
    public function manual_mode_allows_with_filter_config(): void
    {
        config(['recordkeeper.http.mode' => 'manual']);

        app(HttpTracker::class)->setContext(1, new HttpFilterConfig);

        $this->fireHttpEvent('https://api.stripe.com/v1/charges');

        app(HttpTracker::class)->clearContext();

        $this->assertSame(1, AuditHttpRequest::count());
    }

    #[Test]
    public function manual_mode_respects_filter_config_include_hosts(): void
    {
        config(['recordkeeper.http.mode' => 'manual']);

        app(HttpTracker::class)->setContext(1, new HttpFilterConfig(
            includeHosts: ['api.stripe.com'],
        ));

        $this->fireHttpEvent('https://api.stripe.com/v1/charges');
        $this->fireHttpEvent('https://api.hubspot.com/crm');

        app(HttpTracker::class)->clearContext();

        $this->assertSame(1, AuditHttpRequest::count());
        $this->assertSame('api.stripe.com', AuditHttpRequest::first()->host);
    }

    #[Test]
    public function manual_mode_respects_filter_config_exclude_hosts(): void
    {
        config(['recordkeeper.http.mode' => 'manual']);

        app(HttpTracker::class)->setContext(1, new HttpFilterConfig(
            excludeHosts: ['internal.example.com'],
        ));

        $this->fireHttpEvent('https://api.stripe.com/v1/charges');
        $this->fireHttpEvent('https://internal.example.com/health');

        app(HttpTracker::class)->clearContext();

        $this->assertSame(1, AuditHttpRequest::count());
        $this->assertSame('api.stripe.com', AuditHttpRequest::first()->host);
    }

    #[Test]
    public function auto_mode_applies_filter_config_when_present(): void
    {
        config(['recordkeeper.http.mode' => 'auto']);

        app(HttpTracker::class)->setContext(1, new HttpFilterConfig(
            excludeHosts: ['excluded.example.com'],
        ));

        $this->fireHttpEvent('https://api.stripe.com/v1/charges');
        $this->fireHttpEvent('https://excluded.example.com/api');

        app(HttpTracker::class)->clearContext();

        $this->assertSame(1, AuditHttpRequest::count());
        $this->assertSame('api.stripe.com', AuditHttpRequest::first()->host);
    }

    #[Test]
    public function auto_mode_allows_all_when_no_filter_config(): void
    {
        config(['recordkeeper.http.mode' => 'auto']);

        // No filter config, just audit context
        app(HttpTracker::class)->setContext(1);

        $this->fireHttpEvent('https://api.stripe.com/v1/charges');
        $this->fireHttpEvent('https://api.hubspot.com/crm');

        app(HttpTracker::class)->clearContext();

        $this->assertSame(2, AuditHttpRequest::count());
    }

    #[Test]
    public function global_exclude_hosts_applies_in_auto_mode(): void
    {
        config([
            'recordkeeper.http.mode' => 'auto',
            'recordkeeper.http.exclude_hosts' => ['blocked.example.com'],
        ]);

        $this->fireHttpEvent('https://blocked.example.com/api');
        $this->fireHttpEvent('https://api.stripe.com/v1/charges');

        $this->assertSame(1, AuditHttpRequest::count());
        $this->assertSame('api.stripe.com', AuditHttpRequest::first()->host);
    }

    #[Test]
    public function global_exclude_hosts_applies_in_manual_mode(): void
    {
        config([
            'recordkeeper.http.mode' => 'manual',
            'recordkeeper.http.exclude_hosts' => ['blocked.example.com'],
        ]);

        app(HttpTracker::class)->setContext(1, new HttpFilterConfig(
            includeHosts: ['blocked.example.com', 'api.stripe.com'],
        ));

        $this->fireHttpEvent('https://blocked.example.com/api');
        $this->fireHttpEvent('https://api.stripe.com/v1/charges');

        app(HttpTracker::class)->clearContext();

        // Global exclude takes precedence over filter config include
        $this->assertSame(1, AuditHttpRequest::count());
        $this->assertSame('api.stripe.com', AuditHttpRequest::first()->host);
    }

    #[Test]
    public function filter_config_with_enabled_false_blocks_all(): void
    {
        config(['recordkeeper.http.mode' => 'manual']);

        app(HttpTracker::class)->setContext(1, new HttpFilterConfig(enabled: false));

        $this->fireHttpEvent('https://api.stripe.com/v1/charges');

        app(HttpTracker::class)->clearContext();

        $this->assertSame(0, AuditHttpRequest::count());
    }

    #[Test]
    public function include_hosts_allowlist_works_in_auto_mode(): void
    {
        config(['recordkeeper.http.mode' => 'auto']);

        app(HttpTracker::class)->setContext(1, new HttpFilterConfig(
            includeHosts: ['api.stripe.com'],
        ));

        $this->fireHttpEvent('https://api.stripe.com/v1/charges');
        $this->fireHttpEvent('https://api.hubspot.com/crm');

        app(HttpTracker::class)->clearContext();

        $this->assertSame(1, AuditHttpRequest::count());
        $this->assertSame('api.stripe.com', AuditHttpRequest::first()->host);
    }

    #[Test]
    public function has_active_context_reflects_state(): void
    {
        $tracker = app(HttpTracker::class);

        $this->assertFalse($tracker->hasActiveContext());

        $tracker->setContext(1);
        $this->assertTrue($tracker->hasActiveContext());

        $tracker->clearContext();
        $this->assertFalse($tracker->hasActiveContext());
    }

    #[Test]
    public function clear_context_resets_filter_config(): void
    {
        $tracker = app(HttpTracker::class);

        $tracker->setContext(1, new HttpFilterConfig(includeHosts: ['api.stripe.com']));
        $this->assertNotNull($tracker->currentFilterConfig());

        $tracker->clearContext();
        $this->assertNull($tracker->currentFilterConfig());
    }

    /**
     * Fire a RequestSending + ResponseReceived event pair for the given URL.
     */
    private function fireHttpEvent(string $url, int $status = 200): void
    {
        $request = $this->makeRequest('GET', $url);
        $response = $this->makeResponse($status);

        event(new RequestSending($request));
        event(new ResponseReceived($request, $response));
    }

    private function makeRequest(string $method, string $url, array $headers = []): Request
    {
        $psrRequest = new \GuzzleHttp\Psr7\Request(
            $method,
            $url,
            array_merge(['Content-Type' => 'application/json'], $headers)
        );

        return new Request($psrRequest);
    }

    private function makeResponse(int $status, string $body = '{}'): Response
    {
        $psrResponse = new \GuzzleHttp\Psr7\Response($status, [], $body);

        return new Response($psrResponse);
    }
}
