<?php

namespace Amacraft\MarketingAutomation\Enums;

enum MessageChannel: string
{
    case Email = 'email';

    case Sms = 'sms';

    case Whatsapp = 'whatsapp';
}
