<?php

namespace Amacraft\MarketingAutomation\Enums;

enum MessageEventType: string
{
    case Sent = 'sent';

    case Delivered = 'delivered';

    case Read = 'read';

    case Opened = 'opened';

    case Clicked = 'clicked';

    case Failed = 'failed';

    case Bounced = 'bounced';

    case Unsubscribed = 'unsubscribed';

    case Complained = 'complained';
}
