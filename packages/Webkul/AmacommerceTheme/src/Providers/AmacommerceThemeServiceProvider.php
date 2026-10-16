<?php

namespace Webkul\AmacommerceTheme\Providers;

use Illuminate\Support\ServiceProvider;

class AmacommerceThemeServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../Resources/views' => resource_path('themes/amacommerce/views'),
        ], 'amacommerce-theme-views');

        $this->publishes([
            __DIR__.'/../Resources/assets' => resource_path('themes/amacommerce/assets'),
        ], 'amacommerce-theme-assets');
    }
}
