<?php

namespace Webkul\MarketingAutomation\Enums;

enum RoutingTarget: string
{
    case Native = 'native';

    case Klaviyo = 'klaviyo';

    case Disabled = 'disabled';
}
