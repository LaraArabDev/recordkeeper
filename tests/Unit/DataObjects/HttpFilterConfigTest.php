<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Unit\DataObjects;

use LaraArabDev\Recordkeeper\DataObjects\HttpFilterConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for HttpFilterConfig value object.
 */
#[Group('data-objects')]
#[CoversClass(HttpFilterConfig::class)]
final class HttpFilterConfigTest extends TestCase
{
    #[Test]
    public function enabled_false_always_returns_false(): void
    {
        $config = new HttpFilterConfig(enabled: false);

        $this->assertFalse($config->allowsHost('api.stripe.com'));
        $this->assertFalse($config->allowsHost('anything.example.com'));
    }

    #[Test]
    public function empty_lists_allows_all_hosts(): void
    {
        $config = new HttpFilterConfig;

        $this->assertTrue($config->allowsHost('api.stripe.com'));
        $this->assertTrue($config->allowsHost('api.hubspot.com'));
        $this->assertTrue($config->allowsHost('example.com'));
    }

    #[Test]
    public function include_hosts_filters_as_allowlist(): void
    {
        $config = new HttpFilterConfig(includeHosts: ['api.stripe.com', 'api.hubspot.com']);

        $this->assertTrue($config->allowsHost('api.stripe.com'));
        $this->assertTrue($config->allowsHost('api.hubspot.com'));
        $this->assertFalse($config->allowsHost('api.slack.com'));
        $this->assertFalse($config->allowsHost('example.com'));
    }

    #[Test]
    public function exclude_hosts_filters_as_denylist(): void
    {
        $config = new HttpFilterConfig(excludeHosts: ['internal.example.com', 'localhost']);

        $this->assertFalse($config->allowsHost('internal.example.com'));
        $this->assertFalse($config->allowsHost('localhost'));
        $this->assertTrue($config->allowsHost('api.stripe.com'));
        $this->assertTrue($config->allowsHost('example.com'));
    }

    #[Test]
    public function include_hosts_takes_precedence_over_exclude_hosts(): void
    {
        $config = new HttpFilterConfig(
            includeHosts: ['api.stripe.com'],
            excludeHosts: ['api.stripe.com', 'api.hubspot.com'],
        );

        // includeHosts takes precedence — only api.stripe.com is allowed
        $this->assertTrue($config->allowsHost('api.stripe.com'));
        $this->assertFalse($config->allowsHost('api.hubspot.com'));
        $this->assertFalse($config->allowsHost('anything-else.com'));
    }

    #[Test]
    public function disabled_with_include_hosts_still_returns_false(): void
    {
        $config = new HttpFilterConfig(
            enabled: false,
            includeHosts: ['api.stripe.com'],
        );

        $this->assertFalse($config->allowsHost('api.stripe.com'));
    }

    #[Test]
    public function default_values_are_correct(): void
    {
        $config = new HttpFilterConfig;

        $this->assertTrue($config->enabled);
        $this->assertSame([], $config->includeHosts);
        $this->assertSame([], $config->excludeHosts);
    }
}
