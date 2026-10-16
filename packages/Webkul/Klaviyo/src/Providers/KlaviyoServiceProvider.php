<?php

namespace Webkul\Klaviyo\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Webkul\Core\Http\Middleware\NoCacheMiddleware;
use Webkul\Klaviyo\Console\Commands\FlushOutboxCommand;
use Webkul\Klaviyo\Console\Commands\PullConsentCommand;
use Webkul\Klaviyo\Services\KlaviyoClient;
use Webkul\Klaviyo\Services\OutboxWriter;

class KlaviyoServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            dirname(__DIR__).'/Config/menu.php',
            'menu.admin'
        );

        if ($this->app->runningInConsole()) {
            $this->commands([
                FlushOutboxCommand::class,
                PullConsentCommand::class,
            ]);
        }

        $this->app->bind(KlaviyoClient::class);

        $this->app->bind(OutboxWriter::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'klaviyo');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'klaviyo');

        Route::middleware(['web', 'admin', NoCacheMiddleware::class])
            ->prefix(config('app.admin_url'))
            ->group(__DIR__.'/../Routes/admin-routes.php');

        Route::middleware(['web', 'shop'])
            ->group(__DIR__.'/../Routes/shop-routes.php');

        $this->app->register(EventServiceProvider::class);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('klaviyo:flush-outbox')
                ->everyMinute()
                ->withoutOverlapping();

            $schedule->command('klaviyo:pull-consent')
                ->everyFifteenMinutes()
                ->withoutOverlapping();
        });
    }
}
