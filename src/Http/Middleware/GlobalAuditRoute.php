<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Global web route auditing middleware.
 *
 * Pushed into the "web" middleware group by the service provider when
 * `routes.enabled` is true. Skips routes that already carry explicit
 * audit middleware to avoid double writes.
 */
final class GlobalAuditRoute extends BaseAuditMiddleware
{
    /**
     * Get the authentication guard name for global web route auditing.
     *
     * @return string The guard name.
     */
    protected function guard(): string
    {
        return 'web';
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
        if (! config('recordkeeper.routes.web', true)) {
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
