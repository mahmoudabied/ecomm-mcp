<?php

namespace Amacraft\MarketingAutomation\Providers;

use Webkul\Core\Providers\CoreModuleServiceProvider;
use Amacraft\MarketingAutomation\Models\AbandonedCart;
use Amacraft\MarketingAutomation\Models\Attribution;
use Amacraft\MarketingAutomation\Models\BackInStockSubscription;
use Amacraft\MarketingAutomation\Models\Consent;
use Amacraft\MarketingAutomation\Models\ConsentEvent;
use Amacraft\MarketingAutomation\Models\Contact;
use Amacraft\MarketingAutomation\Models\Enrolment;
use Amacraft\MarketingAutomation\Models\EnrolmentEvent;
use Amacraft\MarketingAutomation\Models\Flow;
use Amacraft\MarketingAutomation\Models\FlowVersion;
use Amacraft\MarketingAutomation\Models\MessageEvent;
use Amacraft\MarketingAutomation\Models\MessageLog;
use Amacraft\MarketingAutomation\Models\ProviderConnection;
use Amacraft\MarketingAutomation\Models\StepStat;
use Amacraft\MarketingAutomation\Models\WhatsAppTemplate;

class ModuleServiceProvider extends CoreModuleServiceProvider
{
    /**
     * Models.
     *
     * @var array
     */
    protected $models = [
        AbandonedCart::class,
        Attribution::class,
        BackInStockSubscription::class,
        Consent::class,
        ConsentEvent::class,
        Contact::class,
        Enrolment::class,
        EnrolmentEvent::class,
        Flow::class,
        FlowVersion::class,
        MessageEvent::class,
        MessageLog::class,
        ProviderConnection::class,
        StepStat::class,
        WhatsAppTemplate::class,
    ];
}
