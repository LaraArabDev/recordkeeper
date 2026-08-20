<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use LaraArabDev\Recordkeeper\Console\ExportCommand;
use LaraArabDev\Recordkeeper\Console\HistoryCommand;
use LaraArabDev\Recordkeeper\Console\InstallCommand;
use LaraArabDev\Recordkeeper\Console\PruneCommand;
use LaraArabDev\Recordkeeper\Console\RestoreCommand;
use LaraArabDev\Recordkeeper\Console\RollbackCommand;
use LaraArabDev\Recordkeeper\Console\SearchCommand;
use LaraArabDev\Recordkeeper\Console\ShowCommand;
use LaraArabDev\Recordkeeper\Console\StatsCommand;
use LaraArabDev\Recordkeeper\Console\SyncCommand;
use LaraArabDev\Recordkeeper\Console\TailCommand;
use LaraArabDev\Recordkeeper\Console\UndoCommand;
use LaraArabDev\Recordkeeper\Console\WipeCommand;
use LaraArabDev\Recordkeeper\Http\Middleware\AuditApi;
use LaraArabDev\Recordkeeper\Http\Middleware\AuditRoute;
use LaraArabDev\Recordkeeper\Http\Middleware\GlobalAuditApi;
use LaraArabDev\Recordkeeper\Http\Middleware\GlobalAuditRoute;
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
    /**
     * Register core singletons and merge package config.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/recordkeeper.php', 'recordkeeper');

        $this->app->singleton(Recordkeeper::class);
        $this->app->singleton(HttpTracker::class);
        $this->app->singleton(AuditDriverManager::class);
    }

    /**
     * Boot package services: migrations, middleware, listeners, publishing, and commands.
     */
    public function boot(): void
    {
        $this->registerMigrations();
        $this->registerMiddlewareAliases();
        $this->registerGlobalRouteAuditing();
        $this->registerAuditModel();
        $this->registerAuditListeners();

        if ($this->app->runningInConsole()) {
            $this->offerPublishing();
            $this->registerCommands();
        }
    }

    /**
     * Register publishable config and migration files.
     */
    private function offerPublishing(): void
    {
        $this->publishes([
            __DIR__.'/../config/recordkeeper.php' => config_path('recordkeeper.php'),
        ], 'recordkeeper-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'recordkeeper-migrations');
    }

    /**
     * Register all Artisan console commands.
     */
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
            HistoryCommand::class,
            UndoCommand::class,
            RestoreCommand::class,
            ExportCommand::class,
            WipeCommand::class,
        ]);
    }

    /**
     * Load package migrations from the database directory.
     */
    private function registerMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    /**
     * Register 'audit' and 'audit.api' middleware aliases.
     */
    private function registerMiddlewareAliases(): void
    {
        $router = $this->app['router'];
        $router->aliasMiddleware('audit', AuditRoute::class);
        $router->aliasMiddleware('audit.api', AuditApi::class);
    }

    /**
     * Push global audit middleware into web/api groups when enabled.
     */
    private function registerGlobalRouteAuditing(): void
    {
        if (! config('recordkeeper.routes.enabled', false)) {
            return;
        }

        $router = $this->app['router'];

        if (config('recordkeeper.routes.web', true)) {
            $router->pushMiddlewareToGroup('web', GlobalAuditRoute::class);
        }

        if (config('recordkeeper.routes.api', true)) {
            $router->pushMiddlewareToGroup('api', GlobalAuditApi::class);
        }
    }

    /**
     * Set the audit implementation and register pseudo-type morph map.
     */
    private function registerAuditModel(): void
    {
        $this->app['config']->set('audit.implementation', Audit::class);

        Relation::morphMap(array_fill_keys(Audit::PSEUDO_TYPES, Audit::class));
    }

    /**
     * Subscribe job, command, event, and HTTP listeners to the event dispatcher.
     */
    private function registerAuditListeners(): void
    {
        $this->app['events']->subscribe(RecordJobAudit::class);
        $this->app['events']->subscribe(RecordCommandAudit::class);
        $this->app['events']->listen('*', [RecordEventAudit::class, 'handle']);

        if (config('recordkeeper.http.enabled', false)) {
            $this->app['events']->subscribe(RecordOutboundHttp::class);
        }
    }
}
