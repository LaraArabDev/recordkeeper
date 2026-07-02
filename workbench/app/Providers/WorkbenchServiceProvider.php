<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use LaraArabDev\Recordkeeper\Models\Audit;

class WorkbenchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app['config']->set('audit.implementation', Audit::class);
        $this->app['config']->set('audit.console', true);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        Route::get('/', fn () => response()->json(['status' => 'ok']));

        Route::prefix('api')->group(__DIR__.'/../../routes/api.php');
    }
}
