<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Cache;

use Illuminate\Contracts\Cache\Repository;
use LaraArabDev\Recordkeeper\Models\Audit;

/**
 * Read-through cache for individual audit lookups.
 *
 * Wraps a Laravel cache store with a configurable TTL.
 * Populated on first read, invalidated on update or delete.
 */
final class AuditCache
{
    private const PREFIX = 'rk:audit:';

    public function __construct(
        private readonly Repository $store,
        private readonly int $ttl = 300,
    ) {}

    /** Retrieve an audit from cache or execute the callback and cache the result. */
    public function remember(int|string $id, callable $callback): ?Audit
    {
        $key = self::PREFIX.$id;
        $cached = $this->store->get($key);

        if ($cached !== null) {
            $audit = new Audit;
            $audit->forceFill($cached);

            return $audit;
        }

        $audit = $callback();

        if ($audit !== null) {
            $this->store->put($key, $audit->attributesToArray(), $this->ttl);
        }

        return $audit;
    }

    /**
     * Store an audit record in the cache.
     */
    public function put(Audit $audit): void
    {
        $key = self::PREFIX.$audit->getKey();
        $this->store->put($key, $audit->attributesToArray(), $this->ttl);
    }

    /**
     * Remove an audit record from the cache.
     */
    public function forget(int|string $id): void
    {
        $this->store->forget(self::PREFIX.$id);
    }

    /** @param  list<int|string>  $ids */
    public function flush(array $ids = []): void
    {
        if ($ids === []) {
            return;
        }

        foreach ($ids as $id) {
            $this->forget($id);
        }
    }

    /**
     * Check whether audit caching is enabled in config.
     */
    public function isEnabled(): bool
    {
        return (bool) config('recordkeeper.cache.enabled', false);
    }
}
