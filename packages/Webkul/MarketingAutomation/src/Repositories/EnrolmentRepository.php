<?php

namespace Webkul\MarketingAutomation\Repositories;

use Webkul\Core\Eloquent\Repository;

class EnrolmentRepository extends Repository
{
    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return 'Webkul\MarketingAutomation\Contracts\Enrolment';
    }
}
