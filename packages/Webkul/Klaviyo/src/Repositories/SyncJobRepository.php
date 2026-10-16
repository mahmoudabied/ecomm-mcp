<?php

namespace Webkul\Klaviyo\Repositories;

use Webkul\Core\Eloquent\Repository;

class SyncJobRepository extends Repository
{
    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return 'Webkul\Klaviyo\Contracts\SyncJob';
    }
}
