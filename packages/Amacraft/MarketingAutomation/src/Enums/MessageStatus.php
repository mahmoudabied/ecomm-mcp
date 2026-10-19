<?php

namespace Amacraft\MarketingAutomation\Enums;

enum MessageStatus: string
{
    case Queued = 'queued';

    case Sent = 'sent';

    case Delivered = 'delivered';

    case Read = 'read';

    case Failed = 'failed';

    case Bounced = 'bounced';
}
