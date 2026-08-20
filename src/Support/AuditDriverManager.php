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
    /**
     * Get the default driver name from config.
     *
     * @return string The configured default driver name.
     */
    public function getDefaultDriver(): string
    {
        return config('recordkeeper.driver', 'database');
    }

    /**
     * Get an audit driver instance by name.
     *
     * @param  string|null  $driver  The driver name, or null for the default driver.
     * @return AuditDriver The resolved audit driver instance.
     */
    public function driver($driver = null): AuditDriver
    {
        return parent::driver($driver);
    }

    /**
     * Create the default database storage driver.
     */
    protected function createDatabaseDriver(): DatabaseDriver
    {
        return new DatabaseDriver;
    }

    /**
     * Create the Redis storage driver with configured connection and TTL.
     */
    protected function createRedisDriver(): RedisDriver
    {
        $cfg = config('recordkeeper.drivers.redis', []);
        $connection = $this->container->make('redis')->connection($cfg['connection'] ?? null);
        $ttl = (int) ($cfg['ttl'] ?? 0);

        return new RedisDriver($connection, $ttl);
    }

    /**
     * Create the log channel driver with configured channel and level.
     */
    protected function createLogDriver(): LogDriver
    {
        $cfg = config('recordkeeper.drivers.log', []);

        return new LogDriver(
            $cfg['channel'] ?? 'stack',
            $cfg['level'] ?? 'info',
        );
    }

    /**
     * Create the null driver that discards all audits.
     */
    protected function createNullDriver(): NullDriver
    {
        return new NullDriver;
    }
}
