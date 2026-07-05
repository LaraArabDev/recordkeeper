<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use LaraArabDev\Recordkeeper\Listeners\RecordOutboundHttp;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Models\AuditHttpRequest;
use LaraArabDev\Recordkeeper\Support\HttpTracker;
use LaraArabDev\Recordkeeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Feature tests for outbound HTTP request tracking via the RecordOutboundHttp listener.
 */
#[Group('listeners')]
#[CoversClass(RecordOutboundHttp::class)]
final class HttpOutboundTrackingTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('recordkeeper.http.enabled', true);
    }

    #[Test]
    public function outbound_http_is_recorded_on_response(): void
    {
        $request = $this->makeRequest('GET', 'https://api.stripe.com/v1/charges');
        $response = $this->makeResponse(200);

        event(new RequestSending($request));
        event(new ResponseReceived($request, $response));

        $record = AuditHttpRequest::first();

        $this->assertNotNull($record);
        $this->assertSame('GET', $record->method);
        $this->assertSame('https://api.stripe.com/v1/charges', $record->url);
        $this->assertSame('api.stripe.com', $record->host);
        $this->assertSame(200, $record->status_code);
        $this->assertFalse($record->failed);
    }

    #[Test]
    public function duration_is_recorded(): void
    {
        $request = $this->makeRequest('POST', 'https://api.hubspot.com/crm/v3/objects/contacts');
        $response = $this->makeResponse(201);

        event(new RequestSending($request));
        usleep(5000);
        event(new ResponseReceived($request, $response));

        $record = AuditHttpRequest::first();

        $this->assertNotNull($record->duration_ms);
        $this->assertGreaterThan(0, $record->duration_ms);
    }

    #[Test]
    public function failed_connection_is_recorded(): void
    {
        $request = $this->makeRequest('GET', 'https://unavailable.example.com/api');
        $exception = new ConnectionException('Connection refused');

        event(new RequestSending($request));
        event(new ConnectionFailed($request, $exception));

        $record = AuditHttpRequest::first();

        $this->assertNotNull($record);
        $this->assertSame('unavailable.example.com', $record->host);
        $this->assertTrue($record->failed);
        $this->assertNull($record->status_code);
    }

    #[Test]
    public function excluded_host_is_not_recorded(): void
    {
        config(['recordkeeper.http.exclude_hosts' => ['internal.example.com']]);

        $request = $this->makeRequest('GET', 'https://internal.example.com/health');
        $response = $this->makeResponse(200);

        event(new RequestSending($request));
        event(new ResponseReceived($request, $response));

        $this->assertSame(0, AuditHttpRequest::count());
    }

    #[Test]
    public function disabled_http_tracking_records_nothing(): void
    {
        config(['recordkeeper.http.enabled' => false]);

        $request = $this->makeRequest('GET', 'https://api.stripe.com/v1/charges');
        $response = $this->makeResponse(200);

        event(new RequestSending($request));
        event(new ResponseReceived($request, $response));

        $this->assertSame(0, AuditHttpRequest::count());
    }

    #[Test]
    public function request_linked_to_job_audit(): void
    {
        $audit = Audit::create([
            'event' => 'job.processing',
            'auditable_type' => 'job',
            'old_values' => [],
            'new_values' => [],
        ]);

        app(HttpTracker::class)->setContext($audit->id);

        $request = $this->makeRequest('GET', 'https://api.stripe.com/v1/charges');
        $response = $this->makeResponse(200);

        event(new RequestSending($request));
        event(new ResponseReceived($request, $response));

        app(HttpTracker::class)->clearContext();

        $record = AuditHttpRequest::first();

        $this->assertSame($audit->id, $record->audit_id);
    }

    #[Test]
    public function context_less_http_hit_has_null_audit_id(): void
    {
        app(HttpTracker::class)->clearContext();

        $request = $this->makeRequest('GET', 'https://api.stripe.com/v1/charges');
        $response = $this->makeResponse(200);

        event(new RequestSending($request));
        event(new ResponseReceived($request, $response));

        $record = AuditHttpRequest::first();

        $this->assertNull($record->audit_id);
    }

    #[Test]
    public function response_body_captured_when_enabled(): void
    {
        config([
            'recordkeeper.http.capture_body' => true,
            'recordkeeper.http.body_limit' => 50,
        ]);

        $request = $this->makeRequest('GET', 'https://api.example.com/data');
        $response = $this->makeResponse(200, str_repeat('x', 100));

        event(new RequestSending($request));
        event(new ResponseReceived($request, $response));

        $record = AuditHttpRequest::first();

        $this->assertNotNull($record->response_body);
        $this->assertSame(50, strlen($record->response_body));
    }

    #[Test]
    public function headers_captured_when_enabled(): void
    {
        config(['recordkeeper.http.capture_headers' => true]);

        $request = $this->makeRequest('GET', 'https://api.example.com/data', ['X-Custom' => 'value']);
        $response = $this->makeResponse(200);

        event(new RequestSending($request));
        event(new ResponseReceived($request, $response));

        $record = AuditHttpRequest::first();

        $this->assertNotNull($record->request_headers);
    }

    #[Test]
    public function audit_has_many_http_requests(): void
    {
        $audit = Audit::create([
            'event' => 'job.processing',
            'auditable_type' => 'job',
            'old_values' => [],
            'new_values' => [],
        ]);

        app(HttpTracker::class)->setContext($audit->id);

        foreach (['https://api.stripe.com/charge', 'https://api.hubspot.com/contact'] as $url) {
            $request = $this->makeRequest('POST', $url);
            $response = $this->makeResponse(201);
            event(new RequestSending($request));
            event(new ResponseReceived($request, $response));
        }

        app(HttpTracker::class)->clearContext();

        $this->assertSame(2, $audit->httpRequests()->count());
    }

    #[Test]
    public function put_method_stored_correctly(): void
    {
        $request = $this->makeRequest('PUT', 'https://api.example.com/users/1');
        $response = $this->makeResponse(200);

        event(new RequestSending($request));
        event(new ResponseReceived($request, $response));

        $this->assertSame('PUT', AuditHttpRequest::first()->method);
    }

    #[Test]
    public function delete_method_stored_correctly(): void
    {
        $request = $this->makeRequest('DELETE', 'https://api.example.com/users/1');
        $response = $this->makeResponse(204);

        event(new RequestSending($request));
        event(new ResponseReceived($request, $response));

        $record = AuditHttpRequest::first();
        $this->assertSame('DELETE', $record->method);
        $this->assertSame(204, $record->status_code);
    }

    public function test_4xx_response_stored_as_not_failed(): void
    {
        $request = $this->makeRequest('GET', 'https://api.example.com/missing');
        $response = $this->makeResponse(404);

        event(new RequestSending($request));
        event(new ResponseReceived($request, $response));

        $record = AuditHttpRequest::first();
        $this->assertSame(404, $record->status_code);
        $this->assertFalse($record->failed);
    }

    public function test_5xx_response_stored_as_not_failed(): void
    {
        $request = $this->makeRequest('POST', 'https://api.example.com/action');
        $response = $this->makeResponse(503);

        event(new RequestSending($request));
        event(new ResponseReceived($request, $response));

        $record = AuditHttpRequest::first();
        $this->assertSame(503, $record->status_code);
        $this->assertFalse($record->failed);
    }

    #[Test]
    public function response_body_not_captured_by_default(): void
    {
        $request = $this->makeRequest('GET', 'https://api.example.com/data');
        $response = $this->makeResponse(200, '{"secret":"value"}');

        event(new RequestSending($request));
        event(new ResponseReceived($request, $response));

        $this->assertNull(AuditHttpRequest::first()->response_body);
    }

    #[Test]
    public function request_headers_not_captured_by_default(): void
    {
        $request = $this->makeRequest('GET', 'https://api.example.com/data', ['Authorization' => 'Bearer token']);
        $response = $this->makeResponse(200);

        event(new RequestSending($request));
        event(new ResponseReceived($request, $response));

        $this->assertNull(AuditHttpRequest::first()->request_headers);
        $this->assertNull(AuditHttpRequest::first()->response_headers);
    }

    #[Test]
    public function body_within_limit_stored_in_full(): void
    {
        config([
            'recordkeeper.http.capture_body' => true,
            'recordkeeper.http.body_limit' => 100,
        ]);

        $body = str_repeat('a', 50);
        $request = $this->makeRequest('GET', 'https://api.example.com/data');
        $response = $this->makeResponse(200, $body);

        event(new RequestSending($request));
        event(new ResponseReceived($request, $response));

        $this->assertSame($body, AuditHttpRequest::first()->response_body);
    }

    #[Test]
    public function excluded_host_also_blocked_on_failed_connection(): void
    {
        config(['recordkeeper.http.exclude_hosts' => ['blocked.example.com']]);

        $request = $this->makeRequest('GET', 'https://blocked.example.com/api');
        $exception = new ConnectionException('refused');

        event(new RequestSending($request));
        event(new ConnectionFailed($request, $exception));

        $this->assertSame(0, AuditHttpRequest::count());
    }

    #[Test]
    public function created_at_timestamp_is_stored(): void
    {
        $request = $this->makeRequest('GET', 'https://api.example.com/data');
        $response = $this->makeResponse(200);

        event(new RequestSending($request));
        event(new ResponseReceived($request, $response));

        $this->assertNotNull(AuditHttpRequest::first()->created_at);
    }

    #[Test]
    public function response_without_prior_sending_records_null_duration(): void
    {
        $request = $this->makeRequest('GET', 'https://api.stripe.com/v1/charges');
        $response = $this->makeResponse(200);

        event(new ResponseReceived($request, $response));

        $record = AuditHttpRequest::first();

        $this->assertNotNull($record);
        $this->assertNull($record->duration_ms);
    }

    #[Test]
    public function failed_connection_without_prior_sending_records_null_duration(): void
    {
        $request = $this->makeRequest('GET', 'https://api.stripe.com/v1/charges');
        $exception = new ConnectionException('refused');

        event(new ConnectionFailed($request, $exception));

        $record = AuditHttpRequest::first();

        $this->assertNotNull($record);
        $this->assertNull($record->duration_ms);
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
