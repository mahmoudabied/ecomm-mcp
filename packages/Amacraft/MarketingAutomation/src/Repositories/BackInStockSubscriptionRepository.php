<?php

namespace Amacraft\MarketingAutomation\Repositories;

use Webkul\Core\Eloquent\Repository;

class BackInStockSubscriptionRepository extends Repository
{
    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return 'Amacraft\MarketingAutomation\Contracts\BackInStockSubscription';
    }
}
