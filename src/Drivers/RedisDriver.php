<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Drivers;

use Illuminate\Contracts\Redis\Connection;
use Illuminate\Support\Str;
use LaraArabDev\Recordkeeper\Contracts\AuditDriver;
use LaraArabDev\Recordkeeper\DataObjects\AuditPayload;
use LaraArabDev\Recordkeeper\Models\Audit;

/**
 * Persist audits to Redis using sorted sets for ordering.
 *
 * Optimized for high-throughput write scenarios. Does not support
 * rollback or complex queries. Optional TTL-based expiry.
 */
final class RedisDriver implements AuditDriver
{
    private const PREFIX = 'rk:audit:';

    private const INDEX = 'rk:audits:index';

    public function __construct(
        private readonly Connection $redis,
        private readonly int $ttl = 0,
    ) {}

    /**
     * Persist an audit payload to Redis.
     */
    public function persist(AuditPayload $payload): Audit
    {
        $id = (string) Str::uuid();
        $now = now();

        $data = array_merge($payload->toArray(), [
            'id' => $id,
            'created_at' => $now->toIso8601String(),
            'updated_at' => $now->toIso8601String(),
        ]);

        $this->redis->command('SET', [self::PREFIX.$id, json_encode($data)]);

        if ($this->ttl > 0) {
            $this->redis->command('EXPIRE', [self::PREFIX.$id, $this->ttl]);
        }

        $this->redis->command('ZADD', [self::INDEX, $now->timestamp, $id]);

        $audit = new Audit;
        $audit->forceFill($data);

        return $audit;
    }

    /**
     * Find an audit record by its ID in Redis.
     */
    public function find(int|string $id): ?Audit
    {
        $json = $this->redis->command('GET', [self::PREFIX.$id]);

        if (! $json) {
            return null;
        }

        $data = json_decode($json, true);

        $audit = new Audit;
        $audit->forceFill($data);

        return $audit;
    }

    /**
     * Delete all audit records from Redis.
     */
    public function flush(): void
    {
        $ids = $this->redis->command('ZRANGE', [self::INDEX, 0, -1]);

        foreach ((array) $ids as $id) {
            $this->redis->command('DEL', [self::PREFIX.$id]);
        }

        $this->redis->command('DEL', [self::INDEX]);
    }
}
