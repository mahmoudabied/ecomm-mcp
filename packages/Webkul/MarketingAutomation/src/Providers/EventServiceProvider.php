<?php

namespace Webkul\MarketingAutomation\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use Webkul\MarketingAutomation\Listeners\CartListener;
use Webkul\MarketingAutomation\Listeners\CustomerListener;
use Webkul\MarketingAutomation\Listeners\GdprListener;
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
        CartListener::class,
        GdprListener::class,
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

        Event::listen('bagisto.shop.checkout.onepage.address.after', function ($viewRenderEventManager) {
            $viewRenderEventManager->addTemplate('marketing_automation::shop.checkout.contact-capture');
        });

        Event::listen('bagisto.shop.customers.signup_form_controls.after', function ($viewRenderEventManager) {
            $viewRenderEventManager->addTemplate('marketing_automation::shop.consent-checkboxes');
        });

        Event::listen('bagisto.shop.customers.account.profile.edit.before', function ($viewRenderEventManager) {
            $viewRenderEventManager->addTemplate('marketing_automation::shop.account.preferences-link');
        });

        Event::listen('bagisto.shop.customers.account.gdpr.list.after', function ($viewRenderEventManager) {
            $viewRenderEventManager->addTemplate('marketing_automation::shop.account.gdpr-export-link');
        });
    }
}
