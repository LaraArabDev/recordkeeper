<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Feature;

use LaraArabDev\Recordkeeper\Cache\AuditCache;
use LaraArabDev\Recordkeeper\DataObjects\AuditPayload;
use LaraArabDev\Recordkeeper\Drivers\DatabaseDriver;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Feature tests for AuditCache: remember, flush, and TTL-based cache invalidation.
 */
#[Group('cache')]
#[CoversClass(AuditCache::class)]
class AuditCacheTest extends TestCase
{
    private AuditCache $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $store = app('cache')->store('array');
        $this->cache = new AuditCache($store, 300);
    }

    private function createAudit(string $event = 'created'): Audit
    {
        $driver = new DatabaseDriver;

        return $driver->persist(new AuditPayload(
            event: $event,
            auditableType: 'system',
            auditableId: null,
            oldValues: [],
            newValues: [],
        ));
    }

    #[Test]
    public function remember_returns_audit_from_callback_on_miss(): void
    {
        $audit = $this->createAudit();
        $callCount = 0;

        $result = $this->cache->remember($audit->id, function () use ($audit, &$callCount) {
            $callCount++;

            return Audit::find($audit->id);
        });

        $this->assertNotNull($result);
        $this->assertSame((string) $audit->id, (string) $result->id);
        $this->assertSame(1, $callCount);
    }

    #[Test]
    public function remember_returns_cached_value_on_second_call(): void
    {
        $audit = $this->createAudit();
        $callCount = 0;

        $callback = function () use ($audit, &$callCount) {
            $callCount++;

            return Audit::find($audit->id);
        };

        $this->cache->remember($audit->id, $callback);
        $this->cache->remember($audit->id, $callback);

        $this->assertSame(1, $callCount);
    }

    #[Test]
    public function remember_returns_null_when_callback_returns_null(): void
    {
        $result = $this->cache->remember(99999, fn () => null);

        $this->assertNull($result);
    }

    #[Test]
    public function put_stores_audit_in_cache(): void
    {
        $audit = $this->createAudit('updated');
        $callCount = 0;

        $this->cache->put($audit);

        $result = $this->cache->remember($audit->id, function () use (&$callCount) {
            $callCount++;

            return null;
        });

        $this->assertSame(0, $callCount);
        $this->assertNotNull($result);
        $this->assertSame('updated', $result->event);
    }

    #[Test]
    public function forget_invalidates_cached_entry(): void
    {
        $audit = $this->createAudit();
        $callCount = 0;

        $callback = function () use ($audit, &$callCount) {
            $callCount++;

            return Audit::find($audit->id);
        };

        $this->cache->remember($audit->id, $callback);
        $this->cache->forget($audit->id);
        $this->cache->remember($audit->id, $callback);

        $this->assertSame(2, $callCount);
    }

    #[Test]
    public function flush_invalidates_multiple_entries(): void
    {
        $a1 = $this->createAudit('created');
        $a2 = $this->createAudit('updated');
        $calls = 0;

        $this->cache->remember($a1->id, fn () => Audit::find($a1->id));
        $this->cache->remember($a2->id, fn () => Audit::find($a2->id));

        $this->cache->flush([$a1->id, $a2->id]);

        $this->cache->remember($a1->id, function () use (&$calls) {
            $calls++;

            return null;
        });
        $this->cache->remember($a2->id, function () use (&$calls) {
            $calls++;

            return null;
        });

        $this->assertSame(2, $calls);
    }

    #[Test]
    public function flush_with_empty_array_is_noop(): void
    {
        $audit = $this->createAudit();
        $calls = 0;

        $this->cache->remember($audit->id, fn () => Audit::find($audit->id));
        $this->cache->flush([]);
        $this->cache->remember($audit->id, function () use (&$calls) {
            $calls++;

            return null;
        });

        $this->assertSame(0, $calls);
    }

    #[Test]
    public function is_enabled_reflects_config(): void
    {
        config(['recordkeeper.cache.enabled' => true]);
        $this->assertTrue($this->cache->isEnabled());

        config(['recordkeeper.cache.enabled' => false]);
        $this->assertFalse($this->cache->isEnabled());
    }

    #[Test]
    public function cache_service_is_registered_in_container(): void
    {
        $this->assertInstanceOf(AuditCache::class, app(AuditCache::class));
    }

    #[Test]
    public function remembered_audit_has_correct_attributes(): void
    {
        $audit = $this->createAudit('deleted');

        $result = $this->cache->remember($audit->id, fn () => Audit::find($audit->id));

        $cached = $this->cache->remember($audit->id, fn () => null);

        $this->assertSame('deleted', $cached->event);
        $this->assertSame('system', $cached->auditable_type);
    }
}
