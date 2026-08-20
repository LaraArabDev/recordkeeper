<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Resolvers;

use Illuminate\Support\Facades\Auth;
use OwenIt\Auditing\Contracts\UserResolver;

/**
 * Resolve the authenticated user by iterating all non-web auth guards.
 *
 * Falls back to the default auth user if no guard yields a result.
 */
final class ApiActorResolver implements UserResolver
{
    /**
     * Resolve the authenticated user by iterating all non-web auth guards.
     *
     * @return mixed The authenticated user, or null if no guard yields a result.
     */
    public static function resolve(): mixed
    {
        foreach (config('auth.guards', []) as $name => $guard) {
            if ($name === 'web') {
                continue;
            }
            $user = Auth::guard($name)->user();
            if ($user !== null) {
                return $user;
            }
        }

        return Auth::user();
    }
}
