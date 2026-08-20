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
    /**
     * Get the authentication guard name for global API route auditing.
     *
     * @return string The guard name.
     */
    protected function guard(): string
    {
        return 'api';
    }

    /**
     * Resolve the authenticated actor using multi-guard resolution.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @return mixed The authenticated user, or null if unauthenticated.
     */
    protected function resolveActor(Request $request): mixed
    {
        return ApiActorResolver::resolve();
    }

    /**
     * Handle the incoming request, skipping if excluded or already audited.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @param  Closure  $next  The next middleware closure.
     * @param  string  ...$options  Middleware parameter strings.
     * @return Response The HTTP response.
     */
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
