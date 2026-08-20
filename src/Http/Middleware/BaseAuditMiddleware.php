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
    /**
     * Create a new base audit middleware instance.
     *
     * @param  RedactValues  $redactValues  The value redaction action.
     * @param  RecordAudit  $recordAudit  The audit recording action.
     */
    public function __construct(
        private readonly RedactValues $redactValues,
        private readonly RecordAudit $recordAudit,
    ) {}

    /**
     * Get the authentication guard name for this middleware.
     *
     * @return string The guard name (e.g. 'web', 'api').
     */
    abstract protected function guard(): string;

    /**
     * Resolve the authenticated actor for the current request.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @return mixed The authenticated user, or null if unauthenticated.
     */
    protected function resolveActor(Request $request): mixed
    {
        return auth()->guard($this->guard())->user();
    }

    /**
     * Handle the incoming request and record an audit entry.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @param  Closure  $next  The next middleware closure.
     * @param  string  ...$options  Middleware parameter strings (e.g. 'tag=foo', 'body=true').
     * @return Response The HTTP response.
     *
     * @throws \Throwable When strict mode is enabled and audit recording fails.
     */
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

    /**
     * Record the audit entry for the given request and response.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @param  Response  $response  The HTTP response.
     * @param  array{tag: ?string, body: bool, sample: float}  $opts  Parsed middleware options.
     * @param  int  $duration  Request duration in milliseconds.
     */
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
            source: $request->route()?->getName() ?? $request->path(),
        ));
    }

    /**
     * Parse middleware parameter string options into a typed array.
     *
     * @param  list<string>  $options
     * @return array{tag: ?string, body: bool, sample: float}
     */
    protected function parseOptions(array $options): array
    {
        $opts = [
            'tag' => null,
            'body' => false,
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
                'body' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                'sample' => (float) $value,
                default => $value,
            };
        }

        return $opts;
    }

    /**
     * Determine whether the request path is excluded by configuration.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @return bool True if the request should be excluded from auditing.
     */
    protected function isExcludedByConfig(Request $request): bool
    {
        $exclude = config('recordkeeper.routes.exclude', []);

        return ! empty($exclude) && $request->is(...$exclude);
    }

    /**
     * Check whether the route already has explicit audit middleware attached.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @return bool True if explicit audit middleware is present on the route.
     */
    protected function hasExplicitAuditMiddleware(Request $request): bool
    {
        $route = $request->route();

        if (! $route) {
            return false;
        }

        $explicit = ['audit', 'audit.api', AuditRoute::class, AuditApi::class];

        foreach ($route->gatherMiddleware() as $m) {
            $name = (string) $m;

            if (in_array($name, $explicit, true)
                || str_starts_with($name, AuditRoute::class.':')
                || str_starts_with($name, AuditApi::class.':')
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build middleware options from the global route auditing configuration.
     *
     * @return array{tag: ?string, body: bool, sample: float} The options array.
     */
    protected function buildGlobalOptionsFromConfig(): array
    {
        return [
            'tag' => config('recordkeeper.routes.tag'),
            'body' => (bool) config('recordkeeper.routes.body', false),
            'sample' => (float) config('recordkeeper.routes.sample', 1.0),
        ];
    }

    /**
     * Convert an options array to middleware parameter strings.
     *
     * @param  array<string, mixed>  $opts  The options to convert.
     * @return list<string> The formatted parameter strings (e.g. 'key=value').
     */
    protected function optsToStrings(array $opts): array
    {
        $strings = [];
        foreach ($opts as $key => $value) {
            if ($value === null || $value === false) {
                continue;
            }
            $strings[] = $key.'='.(is_bool($value) ? 'true' : (string) $value);
        }

        return $strings;
    }
}
