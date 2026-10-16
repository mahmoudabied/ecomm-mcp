<?php

namespace Webkul\Klaviyo\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use Webkul\Klaviyo\Listeners\RoutingConfigListener;
use Webkul\Klaviyo\Listeners\StoreEventsListener;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array
     */
    protected $listen = [];

    /**
     * The subscriber classes to register.
     *
     * @var array
     */
    protected $subscribe = [
        StoreEventsListener::class,
        RoutingConfigListener::class,
    ];

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        parent::boot();

        $this->registerViewHooks();
    }

    /**
     * Inject the storefront snippets through Bagisto view events.
     */
    protected function registerViewHooks(): void
    {
        /**
         * Fire the Viewed Product beacon from the product page. The script is
         * static per product, so it also works on response-cached pages; the
         * CSRF token is read from the meta tag at runtime.
         */
        Event::listen('bagisto.shop.products.view.after', fn ($viewRenderEventManager) => $viewRenderEventManager->addTemplate('klaviyo::shop.viewed-product-beacon'));
    }
}
