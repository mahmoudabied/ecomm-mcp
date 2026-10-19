<?php

namespace Amacraft\Klaviyo\Providers;

use Amacraft\Klaviyo\Console\Commands\FlushOutboxCommand;
use Amacraft\Klaviyo\Console\Commands\PullConsentCommand;
use Amacraft\Klaviyo\Services\KlaviyoClient;
use Amacraft\Klaviyo\Services\OutboxWriter;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Konekt\Concord\ConcordServiceProvider;
use Konekt\Concord\Contracts\Concord as ConcordContract;
use Webkul\Core\Http\Middleware\NoCacheMiddleware;

class KlaviyoServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->register(ConcordServiceProvider::class);

        $this->app->make(ConcordContract::class)->registerModule(ModuleServiceProvider::class);

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

        $this->registerPublishables();

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

    /**
     * Publish the package views and translations for customization.
     */
    protected function registerPublishables(): void
    {
        $this->publishes([
            dirname(__DIR__).'/Resources/views' => resource_path('views/vendor/klaviyo'),
        ], 'klaviyo-views');

        $this->publishes([
            dirname(__DIR__).'/Resources/lang' => lang_path('vendor/klaviyo'),
        ], 'klaviyo-lang');
    }
}
