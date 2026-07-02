<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Http\Middleware;

/**
 * Audit middleware for web routes using the "web" authentication guard.
 */
final class AuditRoute extends BaseAuditMiddleware
{
    protected function guard(): string
    {
        return 'web';
    }
}
