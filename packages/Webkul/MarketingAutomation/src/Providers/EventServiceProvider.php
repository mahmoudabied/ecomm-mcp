<?php

namespace Webkul\MarketingAutomation\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use Webkul\MarketingAutomation\Listeners\CustomerListener;
use Webkul\MarketingAutomation\Listeners\NewsletterListener;
use Webkul\MarketingAutomation\Listeners\OrderListener;
use Webkul\MarketingAutomation\Listeners\ProductListener;
use Webkul\MarketingAutomation\Listeners\ShipmentListener;

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
        CustomerListener::class,
        NewsletterListener::class,
        OrderListener::class,
        ShipmentListener::class,
        ProductListener::class,
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
        Event::listen('bagisto.shop.products.view.description.after', function ($viewRenderEventManager) {
            $viewRenderEventManager->addTemplate('marketing_automation::shop.back-in-stock');
        });
    }
}
