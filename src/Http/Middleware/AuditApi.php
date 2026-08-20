<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Http\Middleware;

use Illuminate\Http\Request;
use LaraArabDev\Recordkeeper\Resolvers\ApiActorResolver;

/**
 * Audit middleware for API routes using multi-guard actor resolution.
 *
 * Iterates non-web guards to resolve the authenticated actor,
 * falling back to the default auth user.
 */
final class AuditApi extends BaseAuditMiddleware
{
    /**
     * Get the authentication guard name for API routes.
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
}
