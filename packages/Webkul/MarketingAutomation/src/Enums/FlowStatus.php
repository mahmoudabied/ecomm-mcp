<?php

namespace Webkul\MarketingAutomation\Enums;

enum FlowStatus: string
{
    case Draft = 'draft';

    case Active = 'active';

    case Paused = 'paused';
}
