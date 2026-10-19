<?php

namespace Amacraft\MarketingAutomation\Repositories;

use Webkul\Core\Eloquent\Repository;

class FlowVersionRepository extends Repository
{
    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return 'Amacraft\MarketingAutomation\Contracts\FlowVersion';
    }
}
