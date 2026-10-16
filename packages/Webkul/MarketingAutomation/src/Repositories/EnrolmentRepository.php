<?php

namespace Webkul\MarketingAutomation\Repositories;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Webkul\Core\Eloquent\Repository;
use Webkul\MarketingAutomation\Enums\EnrolmentStatus;

class EnrolmentRepository extends Repository
{
    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return 'Webkul\MarketingAutomation\Contracts\Enrolment';
    }

    /**
     * Active enrolments of the contacts that started no later than the given time.
     */
    public function activeForContactsSince(array $contactIds, CarbonInterface $startedBy): Collection
    {
        return $this->model
            ->whereIn('contact_id', $contactIds)
            ->where('status', EnrolmentStatus::Active->value)
            ->where('created_at', '<=', $startedBy)
            ->with('flow')
            ->get();
    }
}
