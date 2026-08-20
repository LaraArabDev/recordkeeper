<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Http\Middleware;

/**
 * Audit middleware for web routes using the "web" authentication guard.
 */
final class AuditRoute extends BaseAuditMiddleware
{
    /**
     * Get the authentication guard name for web routes.
     *
     * @return string The guard name.
     */
    protected function guard(): string
    {
        return 'web';
    }
}
