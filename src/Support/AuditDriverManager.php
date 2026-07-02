<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Support;

use Illuminate\Support\Manager;
use LaraArabDev\Recordkeeper\Contracts\AuditDriver;
use LaraArabDev\Recordkeeper\Drivers\DatabaseDriver;
use LaraArabDev\Recordkeeper\Drivers\LogDriver;
use LaraArabDev\Recordkeeper\Drivers\NullDriver;
use LaraArabDev\Recordkeeper\Drivers\RedisDriver;

/**
 * Manage audit storage driver instances using Laravel's Manager pattern.
 *
 * Built-in drivers: database, redis, log, null.
 * Extend with custom drivers via {@see Manager::extend()}.
 */
final class AuditDriverManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return config('recordkeeper.driver', 'database');
    }

    public function driver($driver = null): AuditDriver
    {
        return parent::driver($driver);
    }

    protected function createDatabaseDriver(): DatabaseDriver
    {
        return new DatabaseDriver;
    }

    protected function createRedisDriver(): RedisDriver
    {
        $cfg = config('recordkeeper.drivers.redis', []);
        $connection = $this->container->make('redis')->connection($cfg['connection'] ?? null);
        $ttl = (int) ($cfg['ttl'] ?? 0);

        return new RedisDriver($connection, $ttl);
    }

    protected function createLogDriver(): LogDriver
    {
        $cfg = config('recordkeeper.drivers.log', []);

        return new LogDriver(
            $cfg['channel'] ?? 'stack',
            $cfg['level'] ?? 'info',
        );
    }

    protected function createNullDriver(): NullDriver
    {
        return new NullDriver;
    }
}
