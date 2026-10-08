<?php

namespace Webkul\MarketingAutomation\Providers;

use Webkul\Core\Providers\CoreModuleServiceProvider;
use Webkul\MarketingAutomation\Models\AbandonedCart;
use Webkul\MarketingAutomation\Models\Attribution;
use Webkul\MarketingAutomation\Models\BackInStockSubscription;
use Webkul\MarketingAutomation\Models\Consent;
use Webkul\MarketingAutomation\Models\ConsentEvent;
use Webkul\MarketingAutomation\Models\Contact;
use Webkul\MarketingAutomation\Models\Enrolment;
use Webkul\MarketingAutomation\Models\EnrolmentEvent;
use Webkul\MarketingAutomation\Models\Flow;
use Webkul\MarketingAutomation\Models\FlowVersion;
use Webkul\MarketingAutomation\Models\MessageEvent;
use Webkul\MarketingAutomation\Models\MessageLog;
use Webkul\MarketingAutomation\Models\ProviderConnection;
use Webkul\MarketingAutomation\Models\StepStat;
use Webkul\MarketingAutomation\Models\WhatsAppTemplate;

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
