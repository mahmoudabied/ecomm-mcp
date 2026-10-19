<?php

namespace Amacraft\MarketingAutomation\Enums;

enum ConsentStatus: string
{
    case Granted = 'granted';

    case Revoked = 'revoked';

    case None = 'none';
}
