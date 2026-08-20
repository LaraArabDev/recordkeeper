<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Benchmarks;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;

abstract class BenchCase
{
    protected Application $app;

    public function setUp(): void
    {
        $this->app = $GLOBALS['bench_app'];

        DB::table('audit_http_requests')->delete();
        DB::table('audits')->delete();
    }

    protected function makeHttpRequest(string $method, string $url): \Illuminate\Http\Client\Request
    {
        $psr = new Request($method, $url, ['Content-Type' => 'application/json']);

        return new \Illuminate\Http\Client\Request($psr);
    }

    protected function makeHttpResponse(int $status = 200, string $body = '{}'): \Illuminate\Http\Client\Response
    {
        $psr = new Response($status, [], $body);

        return new \Illuminate\Http\Client\Response($psr);
    }

    protected function seedAudits(int $count, array $overrides = []): void
    {
        $rows = [];
        $now = now()->toDateTimeString();

        for ($i = 0; $i < $count; $i++) {
            $rows[] = array_merge([
                'event' => 'updated',
                'auditable_type' => 'App\\Models\\Order',
                'auditable_id' => $i + 1,
                'old_values' => '{"status":"pending"}',
                'new_values' => '{"status":"paid"}',
                'user_type' => null,
                'user_id' => null,
                'url' => null,
                'ip_address' => null,
                'user_agent' => null,
                'tags' => null,
                'guard' => null,
                'batch_id' => null,
                'context' => null,
                'source' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ], $overrides);
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('audits')->insert($chunk);
        }
    }
}
