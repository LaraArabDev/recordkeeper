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
    protected function guard(): string
    {
        return 'api';
    }

    protected function resolveActor(Request $request): mixed
    {
        return ApiActorResolver::resolve();
    }
}
