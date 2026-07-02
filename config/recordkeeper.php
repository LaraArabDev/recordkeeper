<?php

declare(strict_types=1);

return [
    'enabled' => env('RECORDKEEPER_ENABLED', true),

    'events' => ['created', 'updated', 'deleted', 'restored'],

    'privacy' => [
        'mode' => env('RECORDKEEPER_PRIVACY', 'redact'), // redact|encrypt|off
        'mask' => '***',
        'sensitive_patterns' => [
            'password', 'secret', 'token', 'api_key',
            'authorization', 'card', 'cvv', 'ssn', 'iban',
        ],
        'global_exclude' => ['remember_token'],
    ],

    'rollback' => [
        'enabled' => true,
        'permission' => 'rollback_audits',
        'restore_deleted' => true,
    ],

    /*
     * Date-based retention / pruning.
     *
     * laravel-auditing only supports count-based pruning ($auditThreshold).
     * Recordkeeper adds date-based pruning via Laravel's MassPrunable trait.
     *
     * Options:
     *   default_days  — delete audits older than this many days (0 = disabled)
     *   per_model     — override per model class: ['App\Models\Order' => 90]
     *
     * To activate, schedule in your console kernel:
     *   $schedule->command('model:prune', ['--model' => \LaraArabDev\Recordkeeper\Models\Audit::class])->daily();
     *
     * Or use: php artisan recordkeeper:prune --days=365
     */
    'retention' => [
        'default_days' => (int) env('RECORDKEEPER_RETENTION_DAYS', 0), // 0 = disabled
        'per_model' => [],
    ],

    'guards' => [
        'web' => true,
        'api' => true,
    ],

    'filament' => [
        'navigation_group' => 'Audit',
        'navigation_icon' => 'heroicon-o-clock',
        'navigation_sort' => 100,
        'polling_interval' => null,
    ],

    'discovery' => [
        'paths' => ['app/Models'],
    ],

    /*
     * Strict mode: a failed audit write throws instead of logging and continuing.
     * Enable in tests. Never in production.
     */
    'strict' => env('RECORDKEEPER_STRICT', false),

    /*
     * Storage driver for audit writes.
     *
     * Built-in drivers: database | redis | log | null
     *
     * database — Eloquent → audits table (default, supports full query + rollback)
     * redis    — Redis sorted set; fast writes, no SQL queries, no rollback
     * log      — Laravel log channel; zero-overhead observability
     * null     — Discard all audits (useful in test environments)
     *
     * Register a custom driver via RecordkeeperServiceProvider::extend():
     *   AuditDriverManager::extend('custom', fn($app) => new MyDriver);
     */
    'driver' => env('RECORDKEEPER_DRIVER', 'database'),

    'drivers' => [
        'database' => [],

        'redis' => [
            'connection' => env('RECORDKEEPER_REDIS_CONNECTION', 'default'),
            'ttl' => (int) env('RECORDKEEPER_REDIS_TTL', 0), // seconds, 0 = no expiry
        ],

        'log' => [
            'channel' => env('RECORDKEEPER_LOG_CHANNEL', 'stack'),
            'level' => env('RECORDKEEPER_LOG_LEVEL', 'info'),
        ],

        'null' => [],
    ],

    /*
     * Read cache for individual audit lookups.
     *
     * Caches Audit::find() results in the configured cache store.
     * Useful when the same audit record is read repeatedly (e.g. in Filament views).
     *
     * Works alongside any driver; cache is populated on first read and
     * invalidated automatically when the audit is updated or deleted.
     */
    'cache' => [
        'enabled' => env('RECORDKEEPER_CACHE', false),
        'store' => env('RECORDKEEPER_CACHE_STORE', null), // null = default Laravel cache store
        'ttl' => (int) env('RECORDKEEPER_CACHE_TTL', 300), // seconds
    ],

    /*
     * Extra pipeline stages for the middleware audit write path.
     * Each entry must be a class-string with handle(AuditPayload, Closure): mixed.
     */
    'pipeline' => [],

    /*
     * Async queue for audit writes (model + route).
     * When enabled, the write is dispatched as a job, adding < 2ms synchronous overhead.
     */
    'queue' => [
        'enabled' => env('RECORDKEEPER_QUEUE', false),
        'connection' => env('RECORDKEEPER_QUEUE_CONNECTION', null),
        'queue' => env('RECORDKEEPER_QUEUE_NAME', 'audits'),
    ],

    'jobs' => [
        'enabled' => env('RECORDKEEPER_JOBS', false),
        'exclude' => [],
    ],

    'commands' => [
        'enabled' => env('RECORDKEEPER_COMMANDS', false),
        'exclude' => [
            'schedule:run',
            'schedule:finish',
            'queue:work',
            'queue:listen',
            'horizon:work',
            'horizon:supervisor',
            'recordkeeper:tail',
        ],
        'metrics' => [
            'memory' => true,
            'audit_count' => true,
            'anomaly' => env('RECORDKEEPER_ANOMALY', false),
            'anomaly_multiplier' => 2.0,
            'anomaly_min_runs' => 5,
        ],
    ],

    'http' => [
        'enabled' => env('RECORDKEEPER_HTTP', false),
        'queue' => env('RECORDKEEPER_HTTP_QUEUE', false),
        'queue_name' => env('RECORDKEEPER_HTTP_QUEUE_NAME', null),
        'capture_headers' => env('RECORDKEEPER_HTTP_HEADERS', false),
        'capture_body' => env('RECORDKEEPER_HTTP_BODY', false),
        'body_limit' => 1000,
        'exclude_hosts' => [],
    ],

    'listen' => [
        // Event class strings to audit, e.g.:
        // \App\Events\UserRegistered::class,
    ],
];
