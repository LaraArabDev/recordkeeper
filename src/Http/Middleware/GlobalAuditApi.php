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

        if ($this->isExcludedByConfig($request)) {
            return $next($request);
        }

        if ($this->hasExplicitAuditMiddleware($request)) {
            return $next($request);
        }

        $opts = $this->buildGlobalOptionsFromConfig();

        return parent::handle($request, $next, ...$this->optsToStrings($opts));
    }
}
