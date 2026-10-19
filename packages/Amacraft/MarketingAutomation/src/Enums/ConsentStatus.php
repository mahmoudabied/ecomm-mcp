<?php

namespace Amacraft\MarketingAutomation\Enums;

enum ConsentStatus: string
{
    case Granted = 'granted';

    case Pending = 'pending';

    case Revoked = 'revoked';

    case None = 'none';
}
