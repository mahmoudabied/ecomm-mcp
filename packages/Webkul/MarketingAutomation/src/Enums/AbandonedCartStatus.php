<?php

namespace Webkul\MarketingAutomation\Enums;

enum AbandonedCartStatus: string
{
    case Abandoned = 'abandoned';

    case Reopened = 'reopened';

    case Recovered = 'recovered';
}
