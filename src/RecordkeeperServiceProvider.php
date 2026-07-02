<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use LaraArabDev\Recordkeeper\Cache\AuditCache;
use LaraArabDev\Recordkeeper\Console\InstallCommand;
use LaraArabDev\Recordkeeper\Console\PruneCommand;
use LaraArabDev\Recordkeeper\Console\RollbackCommand;
use LaraArabDev\Recordkeeper\Console\SearchCommand;
use LaraArabDev\Recordkeeper\Console\ShowCommand;
use LaraArabDev\Recordkeeper\Console\StatsCommand;
use LaraArabDev\Recordkeeper\Console\SyncCommand;
use LaraArabDev\Recordkeeper\Console\TailCommand;
use LaraArabDev\Recordkeeper\Http\Middleware\AuditApi;
use LaraArabDev\Recordkeeper\Http\Middleware\AuditRoute;
use LaraArabDev\Recordkeeper\Listeners\RecordCommandAudit;
use LaraArabDev\Recordkeeper\Listeners\RecordEventAudit;
use LaraArabDev\Recordkeeper\Listeners\RecordJobAudit;
use LaraArabDev\Recordkeeper\Listeners\RecordOutboundHttp;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Support\AuditDriverManager;
use LaraArabDev\Recordkeeper\Support\HttpTracker;

/**
 * Register and boot all Recordkeeper services, middleware, listeners, and commands.
 */
class RecordkeeperServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/recordkeeper.php', 'recordkeeper');

        $this->app->singleton(Recordkeeper::class);
        $this->app->singleton(HttpTracker::class);
        $this->app->singleton(AuditDriverManager::class);

        $this->app->singleton(AuditCache::class, function ($app) {
            $store = $app['cache']->store(config('recordkeeper.cache.store'));
            $ttl = (int) config('recordkeeper.cache.ttl', 300);

            return new AuditCache($store, $ttl);
        });
    }

    public function boot(): void
    {
        $this->registerMigrations();
        $this->registerMiddlewareAliases();
        $this->registerAuditModel();
        $this->registerAuditListeners();

        if ($this->app->runningInConsole()) {
            $this->offerPublishing();
            $this->registerCommands();
        }
    }

    private function offerPublishing(): void
    {
        $this->publishes([
            __DIR__.'/../config/recordkeeper.php' => config_path('recordkeeper.php'),
        ], 'recordkeeper-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'recordkeeper-migrations');
    }

    private function registerCommands(): void
    {
        $this->commands([
            InstallCommand::class,
            SyncCommand::class,
            SearchCommand::class,
            ShowCommand::class,
            TailCommand::class,
            StatsCommand::class,
            PruneCommand::class,
            RollbackCommand::class,
        ]);
    }

    private function registerMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    private function registerMiddlewareAliases(): void
    {
        $router = $this->app['router'];
        $router->aliasMiddleware('audit', AuditRoute::class);
        $router->aliasMiddleware('audit.api', AuditApi::class);
    }

    private function registerAuditModel(): void
    {
        $this->app['config']->set(
            'audit.implementation',
            Audit::class
        );

        Relation::morphMap([
            'route' => Audit::class,
            'system' => Audit::class,
            'job' => Audit::class,
            'command' => Audit::class,
            'event' => Audit::class,
        ]);
    }

    private function registerAuditListeners(): void
    {
        $this->app['events']->subscribe(RecordJobAudit::class);

        if (config('recordkeeper.commands.enabled', false)) {
            $this->app['events']->subscribe(RecordCommandAudit::class);
        }

        $this->app['events']->listen('*', [RecordEventAudit::class, 'handle']);

        if (config('recordkeeper.http.enabled', false)) {
            $this->app['events']->subscribe(RecordOutboundHttp::class);
        }
    }
}
