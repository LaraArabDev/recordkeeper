<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use LaraArabDev\Recordkeeper\Resolvers\ApiActorResolver;
use Symfony\Component\HttpFoundation\Response;

/**
 * Global API route auditing middleware.
 *
 * Pushed into the "api" middleware group by the service provider when
 * `routes.enabled` is true. Skips routes that already carry explicit
 * audit middleware to avoid double writes.
 */
final class GlobalAuditApi extends BaseAuditMiddleware
{
    protected function guard(): string
    {
        return 'api';
    }

    protected function resolveActor(Request $request): mixed
    {
        return ApiActorResolver::resolve();
    }

    public function handle(Request $request, Closure $next, string ...$options): Response
    {
        if (! config('recordkeeper.routes.api', true)) {
            return $next($request);
        }

        if ($this->isExcluded($request)) {
            return $next($request);
        }

        if ($this->hasExplicitAuditMiddleware($request)) {
            return $next($request);
        }

        $opts = $this->buildOptionsFromConfig();

        return parent::handle($request, $next, ...$this->optsToStrings($opts));
    }

    private function isExcluded(Request $request): bool
    {
        $exclude = config('recordkeeper.routes.exclude', []);

        return ! empty($exclude) && $request->is(...$exclude);
    }

    private function hasExplicitAuditMiddleware(Request $request): bool
    {
        $route = $request->route();
        if (! $route) {
            return false;
        }

        $middleware = $route->gatherMiddleware();

        foreach ($middleware as $m) {
            if (
                $m === 'audit'
                || $m === 'audit.api'
                || $m === AuditRoute::class
                || $m === AuditApi::class
                || str_starts_with((string) $m, AuditRoute::class.':')
                || str_starts_with((string) $m, AuditApi::class.':')
            ) {
                return true;
            }
        }

        return false;
    }

    /** @return array{tag: ?string, body: bool, response: bool, sample: float} */
    private function buildOptionsFromConfig(): array
    {
        return [
            'tag' => config('recordkeeper.routes.tag'),
            'body' => (bool) config('recordkeeper.routes.body', false),
            'response' => false,
            'sample' => (float) config('recordkeeper.routes.sample', 1.0),
        ];
    }

    /** @return list<string> */
    private function optsToStrings(array $opts): array
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
