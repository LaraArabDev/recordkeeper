<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use LaraArabDev\Recordkeeper\Actions\RecordAudit;
use LaraArabDev\Recordkeeper\Actions\RedactValues;
use LaraArabDev\Recordkeeper\DataObjects\AuditPayload;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base middleware for recording HTTP request audits.
 *
 * Captures route, method, status, duration, actor, and optional request body.
 * Subclasses define the authentication guard. Audit writes fail open unless
 * strict mode is enabled.
 *
 * @see AuditRoute
 * @see AuditApi
 */
abstract class BaseAuditMiddleware
{
    public function __construct(
        private readonly RedactValues $redactValues,
        private readonly RecordAudit $recordAudit,
    ) {}

    abstract protected function guard(): string;

    protected function resolveActor(Request $request): mixed
    {
        return auth()->guard($this->guard())->user();
    }

    public function handle(Request $request, Closure $next, string ...$options): Response
    {
        $opts = $this->parseOptions($options);

        if ($opts['sample'] < 1.0 && (mt_rand() / mt_getrandmax()) > $opts['sample']) {
            return $next($request);
        }

        if (! config('recordkeeper.enabled', true)) {
            return $next($request);
        }

        $start = microtime(true);
        $response = $next($request);
        $duration = (int) ((microtime(true) - $start) * 1000);

        try {
            $this->record($request, $response, $opts, $duration);
        } catch (\Throwable $e) {
            if (config('recordkeeper.strict', false)) {
                throw $e;
            }
            Log::error('[Recordkeeper] Middleware audit failed: '.$e->getMessage());
        }

        return $response;
    }

    protected function record(Request $request, Response $response, array $opts, int $duration): void
    {
        $user = $this->resolveActor($request);

        $body = [];
        if ($opts['body']) {
            $body = ($this->redactValues)($request->all());
        }

        $tags = [];
        if (! empty($opts['tag'])) {
            $tags[] = $opts['tag'];
        }

        ($this->recordAudit)(new AuditPayload(
            event: 'route.'.strtolower($request->method()),
            auditableType: 'route',
            auditableId: null,
            oldValues: [],
            newValues: $body,
            userType: $user ? $user::class : null,
            userId: $user?->getKey(),
            url: $request->fullUrl(),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            tags: implode(',', $tags),
            context: [
                'route' => $request->route()?->getName() ?? $request->path(),
                'method' => $request->method(),
                'status' => $response->getStatusCode(),
                'duration_ms' => $duration,
            ],
            guard: $this->guard(),
        ));
    }

    /**
     * Parse middleware parameter string options into a typed array.
     *
     * @param  list<string>  $options
     * @return array{tag: ?string, body: bool, response: bool, sample: float}
     */
    protected function parseOptions(array $options): array
    {
        $opts = [
            'tag' => null,
            'body' => false,
            'response' => false,
            'sample' => 1.0,
        ];

        foreach ($options as $option) {
            if (! str_contains($option, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $option, 2);
            $key = trim($key);
            $value = trim($value);

            $opts[$key] = match ($key) {
                'body', 'response' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                'sample' => (float) $value,
                default => $value,
            };
        }

        return $opts;
    }
}
