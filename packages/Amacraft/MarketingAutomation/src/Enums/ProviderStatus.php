<?php

namespace Amacraft\MarketingAutomation\Enums;

enum ProviderStatus: string
{
    case Untested = 'untested';

    case Connected = 'connected';

    case Error = 'error';
}
