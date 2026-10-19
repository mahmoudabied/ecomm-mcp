<?php

namespace Amacraft\Klaviyo\Repositories;

use Webkul\Core\Eloquent\Repository;

class SyncJobRepository extends Repository
{
    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return 'Amacraft\Klaviyo\Contracts\SyncJob';
    }
}
