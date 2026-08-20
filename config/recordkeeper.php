<?php

declare(strict_types=1);

return [
    'enabled' => env('RECORDKEEPER_ENABLED', true),

    'events' => ['created', 'updated', 'deleted', 'restored'],

    'privacy' => [
        'mode' => env('RECORDKEEPER_PRIVACY', 'redact'), // redact|encrypt|off
        'mask' => '***',
        'sensitive_patterns' => [
            'password',
            'secret',
            'token',
            'api_key',
            'authorization',
            'card',
            'cvv',
            'ssn',
            'iban',
        ],

        'global_exclude' => ['password', 'remember_token'],
    ],

    'rollback' => [
        'enabled' => true,
        'permission' => 'rollback_audits',
        'restore_deleted' => true,
        'track' => env('RECORDKEEPER_ROLLBACK_TRACK', true),
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

    /*
     * Global route auditing.
     *
     * When enabled, ALL routes in the web/api middleware groups are audited
     * automatically — no need to add 'audit' or 'audit.api' middleware to
     * individual route groups. Routes with explicit audit middleware are
     * never double-audited.
     */
    'routes' => [
        'enabled' => env('RECORDKEEPER_ROUTES', false),
        'web' => true,
        'api' => true,
        'exclude' => [
            'horizon/*', 'telescope/*', '_debugbar/*',
            '_ignition/*', 'sanctum/*', 'livewire/*', 'health',
        ],
        'body' => false,
        'sample' => 1.0,
        'tag' => null,
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
        'database' => [
            'chunk_size' => (int) env('RECORDKEEPER_CHUNK_SIZE', 500),
        ],

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
        'mode' => env('RECORDKEEPER_HTTP_MODE', 'auto'), // 'auto' or 'manual'
        'queue' => env('RECORDKEEPER_HTTP_QUEUE', false),
        'queue_name' => env('RECORDKEEPER_HTTP_QUEUE_NAME', null),
        'capture_headers' => env('RECORDKEEPER_HTTP_HEADERS', false),
        'capture_body' => env('RECORDKEEPER_HTTP_BODY', false),
        'body_limit' => 1000,
        'exclude_hosts' => [],
    ],

    /*
     * Application event tracking.
     *
     * When enabled, ALL non-framework events are audited automatically
     * (like jobs.enabled). Use 'exclude' to skip specific event classes.
     * When disabled, only events with #[AuditEvent] or listed in 'listen' are tracked.
     */
    'events_tracking' => [
        'enabled' => env('RECORDKEEPER_EVENTS', false),
        'exclude' => [],
    ],

    /*
     * Explicit event opt-in list (used when events_tracking.enabled = false).
     * Add event class strings here to audit them without modifying the event class.
     */
    'listen' => [
        // \App\Events\UserRegistered::class,
    ],
];
