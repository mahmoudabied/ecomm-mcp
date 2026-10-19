<?php

namespace Amacraft\Klaviyo\Providers;

use Webkul\Core\Providers\CoreModuleServiceProvider;
use Amacraft\Klaviyo\Models\Connection;
use Amacraft\Klaviyo\Models\OutboxItem;
use Amacraft\Klaviyo\Models\SyncJob;

class ModuleServiceProvider extends CoreModuleServiceProvider
{
    /**
     * Models.
     *
     * @var array
     */
    protected $models = [
        Connection::class,
        OutboxItem::class,
        SyncJob::class,
    ];
}
