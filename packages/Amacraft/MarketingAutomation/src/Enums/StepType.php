<?php

namespace Amacraft\MarketingAutomation\Enums;

enum StepType: string
{
    case Wait = 'wait';

    case Condition = 'condition';

    case SendEmail = 'send_email';

    case SendSms = 'send_sms';

    case SendWhatsapp = 'send_whatsapp';

    case Exit = 'exit';
}
