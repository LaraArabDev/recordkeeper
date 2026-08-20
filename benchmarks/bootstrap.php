<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

use Illuminate\Config\Repository;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Queue\QueueServiceProvider;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\RecordkeeperServiceProvider;
use OwenIt\Auditing\AuditingServiceProvider;
use OwenIt\Auditing\Resolvers\UserResolver;

$app = new Application(realpath(__DIR__.'/..'));

$app->useStoragePath(sys_get_temp_dir().'/recordkeeper-bench');

$app->singleton('config', fn () => new Repository([
    'app' => [
        'key' => 'base64:'.base64_encode(random_bytes(32)),
        'debug' => false,
        'env' => 'testing',
    ],
    'database' => [
        'default' => 'benchmark',
        'connections' => [
            'benchmark' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
        ],
    ],
    'queue' => [
        'default' => 'sync',
        'connections' => ['sync' => ['driver' => 'sync']],
    ],
    'cache' => [
        'default' => 'array',
        'stores' => ['array' => ['driver' => 'array']],
    ],
    'audit' => [
        'driver' => 'database',
        'implementation' => Audit::class,
        'console' => true,
        'user' => ['guards' => [], 'morph_prefix' => 'user', 'models' => []],
        'resolver' => UserResolver::class,
        'events' => ['created', 'updated', 'deleted', 'restored'],
        'strict' => false,
        'timestamps' => true,
        'threshold' => 0,
        'drivers' => [
            'database' => [
                'table' => 'audits',
                'connection' => null,
            ],
        ],
        'redacting' => [],
    ],
    'recordkeeper' => [
        'enabled' => true,
        'events' => ['created', 'updated', 'deleted', 'restored'],
        'privacy' => [
            'mode' => 'redact',
            'mask' => '***',
            'sensitive_patterns' => ['password', 'secret', 'token', 'api_key'],
            'global_exclude' => ['remember_token'],
        ],
        'rollback' => ['enabled' => true, 'restore_deleted' => true],
        'retention' => ['default_days' => 0, 'per_model' => []],
        'guards' => ['web' => true, 'api' => true],
        'filament' => ['navigation_group' => 'Audit', 'navigation_icon' => 'heroicon-o-clock'],
        'strict' => true,
        'pipeline' => [],
        'queue' => ['enabled' => false, 'connection' => null, 'queue' => 'audits'],
        'jobs' => ['enabled' => false, 'exclude' => []],
        'commands' => [
            'enabled' => false,
            'exclude' => ['schedule:run', 'schedule:finish', 'queue:work', 'queue:listen'],
            'metrics' => [
                'memory' => true,
                'audit_count' => true,
                'anomaly' => false,
                'anomaly_multiplier' => 2.0,
                'anomaly_min_runs' => 5,
            ],
        ],
        'http' => [
            'enabled' => false,
            'queue' => false,
            'queue_name' => null,
            'capture_headers' => false,
            'capture_body' => false,
            'body_limit' => 1000,
            'exclude_hosts' => [],
        ],
        'listen' => [],
        'discovery' => ['paths' => ['app/Models']],
    ],
]));

$app->singleton(Kernel::class, fn () => new class
{
    public function all(): array
    {
        return [];
    }
});
$app->singleton(
    ExceptionHandler::class,
    Handler::class
);

$app->register(new EventServiceProvider($app));
$app->register(new DatabaseServiceProvider($app));
$app->register(new QueueServiceProvider($app));
$app->register(new AuditingServiceProvider($app));
$app->register(new RecordkeeperServiceProvider($app));

Facade::setFacadeApplication($app);

$app->boot();

Schema::create('audits', function (Blueprint $table): void {
    $table->bigIncrements('id');
    $table->string('user_type')->nullable();
    $table->unsignedBigInteger('user_id')->nullable();
    $table->string('event');
    $table->string('auditable_type');
    $table->unsignedBigInteger('auditable_id')->nullable();
    $table->longText('old_values')->nullable();
    $table->longText('new_values')->nullable();
    $table->text('url')->nullable();
    $table->ipAddress('ip_address')->nullable();
    $table->string('user_agent', 1023)->nullable();
    $table->string('tags')->nullable();
    $table->timestamps();
    $table->string('guard')->nullable()->index();
    $table->string('batch_id')->nullable()->index();
    $table->json('context')->nullable();
    $table->string('source')->nullable()->index();
    $table->softDeletes();
    $table->index('event');
    $table->index('created_at');
});

Schema::create('audit_http_requests', function (Blueprint $table): void {
    $table->bigIncrements('id');
    $table->unsignedBigInteger('audit_id')->nullable()->index();
    $table->string('method', 10);
    $table->text('url');
    $table->string('host')->nullable()->index();
    $table->integer('status_code')->nullable();
    $table->integer('duration_ms')->nullable();
    $table->boolean('failed')->default(false);
    $table->text('exception')->nullable();
    $table->json('request_headers')->nullable();
    $table->json('response_headers')->nullable();
    $table->text('response_body')->nullable();
    $table->timestamp('created_at')->useCurrent();
});

Schema::create('audit_tag', function (Blueprint $table): void {
    $table->bigIncrements('id');
    $table->unsignedBigInteger('audit_id')->index();
    $table->string('tag')->index();
});

$GLOBALS['bench_app'] = $app;
