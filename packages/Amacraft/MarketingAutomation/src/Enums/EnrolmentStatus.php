<?php

namespace Amacraft\MarketingAutomation\Enums;

enum EnrolmentStatus: string
{
    case Active = 'active';

    case Completed = 'completed';

    case Converted = 'converted';

    case Exited = 'exited';

    case Cancelled = 'cancelled';
}
