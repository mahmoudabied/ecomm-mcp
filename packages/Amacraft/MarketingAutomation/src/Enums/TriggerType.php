<?php

namespace Amacraft\MarketingAutomation\Enums;

enum TriggerType: string
{
    case CartAbandoned = 'cart_abandoned';

    case CustomerRegistered = 'customer_registered';

    case NewsletterSubscribed = 'newsletter_subscribed';

    case OrderPlaced = 'order_placed';

    case OrderStatusChanged = 'order_status_changed';

    case OrderShipped = 'order_shipped';

    case OrderCompleted = 'order_completed';

    case BackInStock = 'back_in_stock';

    case CustomerInactive = 'customer_inactive';

    case CustomerBirthday = 'customer_birthday';
}
