<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Unit;

use LaraArabDev\Recordkeeper\Resolvers\ApiActorResolver;
use LaraArabDev\Recordkeeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Unit tests for ApiActorResolver guard-based user resolution.
 */
#[Group('resolvers')]
#[CoversClass(ApiActorResolver::class)]
class ApiActorResolverTest extends TestCase
{
    #[Test]
    public function returns_null_when_no_user_authenticated(): void
    {
        // Default config has 'web' and 'api' guards; no user is authenticated
        // so the resolver should return null via the Auth::user() fallback.
        $result = ApiActorResolver::resolve();

        $this->assertNull($result);
    }

    #[Test]
    public function returns_null_when_only_web_guard_configured(): void
    {
        // web guard is explicitly skipped; no other guards → falls through to
        // Auth::user() which returns null in tests.
        config(['auth.guards' => ['web' => config('auth.guards.web')]]);

        $result = ApiActorResolver::resolve();

        $this->assertNull($result);
    }

    #[Test]
    public function returns_null_when_api_guard_has_no_user(): void
    {
        // api token guard exists but no authenticated user.
        config(['auth.guards' => array_merge(
            ['web' => config('auth.guards.web')],
            ['api' => ['driver' => 'token', 'provider' => 'users']],
        )]);

        $result = ApiActorResolver::resolve();

        $this->assertNull($result);
    }
}
