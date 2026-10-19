<?php

namespace Amacraft\MarketingAutomation\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Webkul\Core\Http\Middleware\NoCacheMiddleware;
use Amacraft\MarketingAutomation\Console\Commands\AuditConsentCommand;
use Amacraft\MarketingAutomation\Console\Commands\DailyTriggersCommand;
use Amacraft\MarketingAutomation\Console\Commands\DetectAbandonedCartsCommand;
use Amacraft\MarketingAutomation\Console\Commands\DispatchDueCommand;
use Amacraft\MarketingAutomation\Console\Commands\InstallCommand;
use Amacraft\MarketingAutomation\Console\Commands\PurgeLogsCommand;
use Amacraft\MarketingAutomation\Services\ConsentService;
use Amacraft\MarketingAutomation\Services\ContactResolver;
use Amacraft\MarketingAutomation\Services\PhoneNormalizer;
use Amacraft\MarketingAutomation\Services\ProviderManager;

class MarketingAutomationServiceProvider extends ServiceProvider
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

        $this->mergeConfigFrom(
            dirname(__DIR__).'/Config/acl.php',
            'acl'
        );

        $this->mergeConfigFrom(
            dirname(__DIR__).'/Config/system.php',
            'core'
        );

        $this->mergeConfigFrom(
            dirname(__DIR__).'/Config/automation-providers.php',
            'automation-providers'
        );

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                DispatchDueCommand::class,
                DailyTriggersCommand::class,
                DetectAbandonedCartsCommand::class,
                PurgeLogsCommand::class,
                AuditConsentCommand::class,
            ]);
        }

        $this->app->bind(PhoneNormalizer::class);
        $this->app->bind(ContactResolver::class);
        $this->app->bind(ConsentService::class);

        $this->app->scoped(ProviderManager::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'marketing_automation');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'marketing_automation');

        Route::middleware(['web', 'admin', NoCacheMiddleware::class])
            ->prefix(config('app.admin_url'))
            ->group(__DIR__.'/../Routes/admin-routes.php');

        Route::middleware(['web', 'shop'])
            ->group(__DIR__.'/../Routes/shop-routes.php');

        Route::middleware(['web'])
            ->withoutMiddleware(VerifyCsrfToken::class)
            ->group(__DIR__.'/../Routes/public-routes.php');

        $this->app->register(EventServiceProvider::class);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('automation:dispatch-due')
                ->everyMinute()
                ->withoutOverlapping()
                ->runInBackground();

            $schedule->command('automation:detect-abandoned-carts')
                ->everyFiveMinutes()
                ->withoutOverlapping();

            $schedule->command('automation:daily-triggers')
                ->hourly()
                ->withoutOverlapping();

            $schedule->command('automation:purge-logs')
                ->daily()
                ->withoutOverlapping();
        });
    }
}
