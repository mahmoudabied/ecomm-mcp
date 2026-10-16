<?php

namespace Webkul\Klaviyo\Providers;

use Webkul\Core\Providers\CoreModuleServiceProvider;
use Webkul\Klaviyo\Models\Connection;
use Webkul\Klaviyo\Models\OutboxItem;
use Webkul\Klaviyo\Models\SyncJob;

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
