<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Feature;

use Illuminate\Contracts\Redis\Connection as RedisConnection;
use Illuminate\Support\Facades\Log;
use LaraArabDev\Recordkeeper\Actions\RecordAudit;
use LaraArabDev\Recordkeeper\DataObjects\AuditPayload;
use LaraArabDev\Recordkeeper\Drivers\DatabaseDriver;
use LaraArabDev\Recordkeeper\Drivers\LogDriver;
use LaraArabDev\Recordkeeper\Drivers\NullDriver;
use LaraArabDev\Recordkeeper\Drivers\RedisDriver;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Support\AuditDriverManager;
use LaraArabDev\Recordkeeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;

/**
 * Feature tests for audit storage drivers: Database, Redis, Log, Null, and the DriverManager.
 */
#[Group('drivers')]
#[CoversClass(DatabaseDriver::class)]
#[CoversClass(NullDriver::class)]
#[CoversClass(LogDriver::class)]
#[CoversClass(RedisDriver::class)]
#[CoversClass(AuditDriverManager::class)]
class AuditDriverTest extends TestCase
{
    private function payload(string $event = 'created'): AuditPayload
    {
        return new AuditPayload(
            event: $event,
            auditableType: 'system',
            auditableId: null,
            oldValues: [],
            newValues: ['key' => 'value'],
        );
    }

    #[Test]
    public function database_driver_persists_to_db(): void
    {
        $driver = new DatabaseDriver;
        $audit = $driver->persist($this->payload());

        $this->assertDatabaseHas('audits', ['event' => 'created']);
        $this->assertNotNull($audit->id);
    }

    #[Test]
    public function database_driver_find_returns_audit(): void
    {
        $driver = new DatabaseDriver;
        $created = $driver->persist($this->payload());

        $found = $driver->find($created->id);

        $this->assertNotNull($found);
        $this->assertSame((string) $created->id, (string) $found->id);
    }

    #[Test]
    public function database_driver_find_returns_null_for_missing(): void
    {
        $driver = new DatabaseDriver;

        $this->assertNull($driver->find(99999));
    }

    #[Test]
    public function database_driver_flush_deletes_all(): void
    {
        $driver = new DatabaseDriver;
        $driver->persist($this->payload());
        $driver->persist($this->payload('updated'));

        $driver->flush();

        $this->assertSame(0, Audit::count());
    }

    #[Test]
    public function null_driver_does_not_write_to_db(): void
    {
        $driver = new NullDriver;
        $audit = $driver->persist($this->payload());

        $this->assertSame(0, Audit::count());
        $this->assertSame('created', $audit->event);
    }

    #[Test]
    public function null_driver_find_always_returns_null(): void
    {
        $driver = new NullDriver;

        $this->assertNull($driver->find(1));
    }

    #[Test]
    public function null_driver_flush_is_noop(): void
    {
        $driver = new NullDriver;
        $driver->flush();

        $this->assertTrue(true);
    }

    #[Test]
    public function log_driver_writes_to_log_channel(): void
    {
        $logChannel = \Mockery::mock(LoggerInterface::class);
        $logChannel->shouldReceive('info')->once();

        Log::shouldReceive('channel')->once()->with('stack')->andReturn($logChannel);

        $driver = new LogDriver('stack', 'info');
        $audit = $driver->persist($this->payload('order.placed'));

        $this->assertSame(0, Audit::count());
        $this->assertSame('order.placed', $audit->event);
    }

    #[Test]
    public function log_driver_find_always_returns_null(): void
    {
        $driver = new LogDriver;

        $this->assertNull($driver->find(1));
    }

    #[Test]
    public function log_driver_flush_is_noop(): void
    {
        $driver = new LogDriver;
        $driver->flush();

        $this->assertTrue(true);
    }

    #[Test]
    public function redis_driver_persist_stores_data(): void
    {
        $redis = \Mockery::mock(RedisConnection::class);
        $redis->shouldReceive('command')->with('SET', \Mockery::type('array'))->once()->andReturn(true);
        $redis->shouldReceive('command')->with('ZADD', \Mockery::type('array'))->once()->andReturn(1);

        $driver = new RedisDriver($redis, 0);
        $audit = $driver->persist($this->payload('redis.event'));

        $this->assertSame('redis.event', $audit->event);
        $this->assertSame('system', $audit->auditable_type);
    }

    #[Test]
    public function redis_driver_persist_sets_ttl_when_configured(): void
    {
        $redis = \Mockery::mock(RedisConnection::class);
        $redis->shouldReceive('command')->with('SET', \Mockery::type('array'))->once()->andReturn(true);
        $redis->shouldReceive('command')->with('EXPIRE', \Mockery::type('array'))->once()->andReturn(1);
        $redis->shouldReceive('command')->with('ZADD', \Mockery::type('array'))->once()->andReturn(1);

        $driver = new RedisDriver($redis, 300);
        $audit = $driver->persist($this->payload('ttl.event'));

        $this->assertSame('ttl.event', $audit->event);
    }

    #[Test]
    public function redis_driver_find_returns_audit_when_key_exists(): void
    {
        $data = ['event' => 'found', 'auditable_type' => 'system', 'auditable_id' => null];

        $redis = \Mockery::mock(RedisConnection::class);
        $redis->shouldReceive('command')->with('GET', \Mockery::type('array'))->once()->andReturn(json_encode($data));

        $driver = new RedisDriver($redis);
        $audit = $driver->find('some-uuid');

        $this->assertNotNull($audit);
        $this->assertSame('found', $audit->event);
    }

    #[Test]
    public function redis_driver_find_returns_null_when_key_missing(): void
    {
        $redis = \Mockery::mock(RedisConnection::class);
        $redis->shouldReceive('command')->with('GET', \Mockery::type('array'))->once()->andReturn(null);

        $driver = new RedisDriver($redis);

        $this->assertNull($driver->find('missing-uuid'));
    }

    #[Test]
    public function redis_driver_flush_deletes_all_keys(): void
    {
        $redis = \Mockery::mock(RedisConnection::class);
        $redis->shouldReceive('command')->with('ZRANGE', \Mockery::type('array'))->once()->andReturn(['id-1', 'id-2']);
        $redis->shouldReceive('command')->with('DEL', \Mockery::type('array'))->twice()->andReturn(1);
        $redis->shouldReceive('command')->with('DEL', ['rk:audits:index'])->once()->andReturn(1);

        $driver = new RedisDriver($redis);
        $driver->flush();

        $this->assertTrue(true);
    }

    #[Test]
    public function driver_manager_resolves_redis_driver(): void
    {
        $redisMock = \Mockery::mock(RedisConnection::class);
        $redisManager = \Mockery::mock();
        $redisManager->shouldReceive('connection')->andReturn($redisMock);
        $this->app->instance('redis', $redisManager);

        $manager = new AuditDriverManager($this->app);

        $this->assertInstanceOf(RedisDriver::class, $manager->driver('redis'));
    }

    #[Test]
    public function record_audit_action_uses_configured_driver(): void
    {
        config(['recordkeeper.driver' => 'database']);

        $action = app(RecordAudit::class);
        $audit = $action($this->payload('system.boot'));

        $this->assertDatabaseHas('audits', ['event' => 'system.boot']);
        $this->assertNotNull($audit->id);
    }

    #[Test]
    public function switching_to_null_driver_skips_db_write(): void
    {
        config(['recordkeeper.driver' => 'null']);

        $manager = app(AuditDriverManager::class);
        $manager->forgetDrivers();

        $audit = app(RecordAudit::class)($this->payload('test.event'));

        $this->assertSame(0, Audit::count());
        $this->assertSame('test.event', $audit->event);
    }

    #[Test]
    public function driver_manager_resolves_database_driver(): void
    {
        $manager = app(AuditDriverManager::class);

        $this->assertInstanceOf(DatabaseDriver::class, $manager->driver('database'));
    }

    #[Test]
    public function driver_manager_resolves_null_driver(): void
    {
        $manager = app(AuditDriverManager::class);

        $this->assertInstanceOf(NullDriver::class, $manager->driver('null'));
    }

    #[Test]
    public function driver_manager_resolves_log_driver(): void
    {
        $manager = app(AuditDriverManager::class);

        $this->assertInstanceOf(LogDriver::class, $manager->driver('log'));
    }

    #[Test]
    public function driver_manager_supports_custom_extension(): void
    {
        $manager = app(AuditDriverManager::class);
        $manager->extend('custom', fn () => new NullDriver);

        $this->assertInstanceOf(NullDriver::class, $manager->driver('custom'));
    }

    #[Test]
    public function audit_payload_includes_guard_field(): void
    {
        $payload = new AuditPayload(
            event: 'route.get',
            auditableType: 'route',
            auditableId: null,
            oldValues: [],
            newValues: [],
            guard: 'web',
        );

        $driver = new DatabaseDriver;
        $audit = $driver->persist($payload);

        $this->assertSame('web', $audit->fresh()->guard);
    }
}
