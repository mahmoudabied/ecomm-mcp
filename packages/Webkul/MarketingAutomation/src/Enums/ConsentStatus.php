<?php

namespace Webkul\MarketingAutomation\Enums;

enum ConsentStatus: string
{
    case Granted = 'granted';

    case Revoked = 'revoked';

    case None = 'none';
}
