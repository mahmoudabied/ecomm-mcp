<?php

namespace Webkul\MarketingAutomation\Enums;

enum ConsentSource: string
{
    case Registration = 'registration';

    case Checkout = 'checkout';

    case Account = 'account';

    case UnsubscribeLink = 'unsubscribe_link';

    case SmsStop = 'sms_stop';

    case WhatsappOptout = 'whatsapp_optout';

    case Admin = 'admin';

    case Klaviyo = 'klaviyo';

    case MigratedNewsletter = 'migrated_newsletter';

    case Newsletter = 'newsletter';
}
