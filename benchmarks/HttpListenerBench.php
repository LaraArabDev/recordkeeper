<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Benchmarks;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use LaraArabDev\Recordkeeper\Listeners\RecordOutboundHttp;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Groups;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;

#[Groups(['http', 'listener'])]
#[BeforeMethods(['setUp'])]
#[Warmup(2)]
#[Iterations(5)]
final class HttpListenerBench extends BenchCase
{
    public function setUp(): void
    {
        parent::setUp();
        config(['recordkeeper.http.enabled' => false]);
    }

    public function setUpEnabled(): void
    {
        parent::setUp();
        config([
            'recordkeeper.http.enabled' => true,
            'recordkeeper.http.queue' => false,
        ]);

        $this->app['events']->subscribe(
            RecordOutboundHttp::class
        );
    }

    public function setUpEnabledWithHeaders(): void
    {
        $this->setUpEnabled();
        config(['recordkeeper.http.capture_headers' => true]);
    }

    #[Revs(2000)]
    public function benchListenerDisabledOverhead(): void
    {
        $request = $this->makeHttpRequest('GET', 'https://api.stripe.com/v1/charges');
        $response = $this->makeHttpResponse(200);

        event(new RequestSending($request));
        event(new ResponseReceived($request, $response));
    }

    #[BeforeMethods(['setUpEnabled'])]
    #[Revs(20)]
    public function benchListenerEnabledSyncWrite(): void
    {
        $request = $this->makeHttpRequest('POST', 'https://api.stripe.com/v1/charges');
        $response = $this->makeHttpResponse(201);

        event(new RequestSending($request));
        event(new ResponseReceived($request, $response));
    }

    #[BeforeMethods(['setUpEnabled'])]
    #[Revs(20)]
    public function benchListenerOnFailedConnection(): void
    {
        $request = $this->makeHttpRequest('GET', 'https://unavailable.example.com');
        $exception = new ConnectionException('refused');

        event(new RequestSending($request));
        event(new ConnectionFailed($request, $exception));
    }

    #[BeforeMethods(['setUpEnabledWithHeaders'])]
    #[Revs(20)]
    public function benchListenerWithHeaderCapture(): void
    {
        $request = $this->makeHttpRequest('GET', 'https://api.stripe.com/v1/charges');
        $response = $this->makeHttpResponse(200);

        event(new RequestSending($request));
        event(new ResponseReceived($request, $response));
    }

    #[BeforeMethods(['setUpEnabled'])]
    #[Revs(20)]
    public function benchListenerExcludedHostSkipped(): void
    {
        config(['recordkeeper.http.exclude_hosts' => ['api.stripe.com']]);

        $request = $this->makeHttpRequest('GET', 'https://api.stripe.com/v1/charges');
        $response = $this->makeHttpResponse(200);

        event(new RequestSending($request));
        event(new ResponseReceived($request, $response));
    }
}
