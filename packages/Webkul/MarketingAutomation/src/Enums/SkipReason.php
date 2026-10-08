<?php

namespace Webkul\MarketingAutomation\Enums;

enum SkipReason: string
{
    case NoConsent = 'no_consent';

    case InvalidContact = 'invalid_contact';

    case NoProvider = 'no_provider';

    case FrequencyCap = 'frequency_cap';

    case QuietHours = 'quiet_hours';

    case Expired = 'expired';

    case CartEmpty = 'cart_empty';

    case ProductUnavailable = 'product_unavailable';

    case RoutingDisabled = 'routing_disabled';
}
