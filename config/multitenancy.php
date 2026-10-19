<?php

use Amacraft\Klaviyo\Jobs\FlushOutboxBatch;
use Amacraft\Klaviyo\Jobs\HistoricalSync;
use Amacraft\MarketingAutomation\Jobs\AttributeOrder;
use Amacraft\MarketingAutomation\Jobs\IngestTrigger;
use Amacraft\MarketingAutomation\Jobs\ProcessBackInStockSubscriptions;
use Amacraft\MarketingAutomation\Jobs\ProcessEnrolmentStep;
use Amacraft\MarketingAutomation\Jobs\ProcessWebhook;
use Amacraft\MarketingAutomation\Jobs\RecordMessageEvent;
use Amacraft\MarketingAutomation\Jobs\SendConsentConfirmation;
use Amacraft\MarketingAutomation\Jobs\SyncWhatsAppTemplates;
use App\Tenant\AppTenantDomainTenantFinder;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Queue\CallQueuedClosure;
use Spatie\Multitenancy\Actions\ForgetCurrentTenantAction;
use Spatie\Multitenancy\Actions\MakeQueueTenantAwareAction;
use Spatie\Multitenancy\Actions\MakeTenantCurrentAction;
use Spatie\Multitenancy\Actions\MigrateTenantAction;
use Spatie\Multitenancy\Jobs\NotTenantAware;
use Spatie\Multitenancy\Jobs\TenantAware;
use Spatie\Multitenancy\Models\Tenant;
use Webkul\CatalogRule\Jobs\DeleteCatalogRuleIndex;
use Webkul\CatalogRule\Jobs\UpdateCreateCatalogRuleIndex;
use Webkul\CatalogRule\Jobs\UpdateCreateProductIndex;
use Webkul\Core\Jobs\UpdateCreateVisitableIndex;
use Webkul\Core\Jobs\UpdateCreateVisitIndex;
use Webkul\Marketing\Jobs\UpdateCreateSearchTerm;
use Webkul\Notification\Events\CreateOrderNotification;
use Webkul\Notification\Events\UpdateOrderNotification;
use Webkul\Product\Jobs\ElasticSearch\DeleteIndex as DeleteElasticSearchIndex;
use Webkul\Product\Jobs\ElasticSearch\UpdateCreateIndex as UpdateCreateElasticSearchIndex;
use Webkul\Product\Jobs\UpdateCreateInventoryIndex;
use Webkul\Product\Jobs\UpdateCreatePriceIndex;
use Webkul\Sitemap\Jobs\ProcessSitemap;

return [
    /*
     * This class is responsible for determining which tenant should be current
     * for the given request.
     *
     * This class should extend `Spatie\Multitenancy\TenantFinder\TenantFinder`
     *
     */
    'tenant_finder' => AppTenantDomainTenantFinder::class,

    /*
     * These fields are used by tenant:artisan command to match one or more tenant.
     */
    'tenant_artisan_search_fields' => [
        'id',
    ],

    /*
     * These tasks will be performed when switching tenants.
     *
     * A valid task is any class that implements Spatie\Multitenancy\Tasks\SwitchTenantTask
     */
    'switch_tenant_tasks' => [
        // \Spatie\Multitenancy\Tasks\PrefixCacheTask::class,
        // \Spatie\Multitenancy\Tasks\SwitchTenantDatabaseTask::class,
        // \Spatie\Multitenancy\Tasks\SwitchRouteCacheTask::class,
    ],

    /*
     * This class is the model used for storing configuration on tenants.
     *
     * It must  extend `Spatie\Multitenancy\Models\Tenant::class` or
     * implement `Spatie\Multitenancy\Contracts\IsTenant::class` interface
     */
    'tenant_model' => Aps\Models\Tenant::class,

    /*
     * If there is a current tenant when dispatching a job, the id of the current tenant
     * will be automatically set on the job. When the job is executed, the set
     * tenant on the job will be made current.
     */
    'queues_are_tenant_aware_by_default' => true,

    /*
     * The connection name to reach the tenant database.
     *
     * Set to `null` to use the default connection.
     */
    'tenant_database_connection_name' => null,

    /*
     * The connection name to reach the landlord database.
     */
    'landlord_database_connection_name' => null,

    /*
     * This key will be used to associate the current tenant in the context
     */
    'current_tenant_context_key' => 'tenantId',

    /*
     * This key will be used to bind the current tenant in the container.
     */
    'current_tenant_container_key' => 'currentTenant',

    /*
     * Set it to `true` if you like to cache the tenant(s) routes
     * in a shared file using the `SwitchRouteCacheTask`.
     */
    'shared_routes_cache' => false,

    /*
     * You can customize some of the behavior of this package by using your own custom action.
     * Your custom action should always extend the default one.
     */
    'actions' => [
        'make_tenant_current_action'     => MakeTenantCurrentAction::class,
        'forget_current_tenant_action'   => ForgetCurrentTenantAction::class,
        'make_queue_tenant_aware_action' => MakeQueueTenantAwareAction::class,
        'migrate_tenant'                 => MigrateTenantAction::class,
    ],

    /*
     * You can customize the way in which the package resolves the queueable to a job.
     *
     * For example, using the package laravel-actions (by Loris Leiva), you can
     * resolve JobDecorator to getAction() like so: JobDecorator::class => 'getAction'
     */
    'queueable_to_job' => [
        SendQueuedMailable::class      => 'mailable',
        SendQueuedNotifications::class => 'notification',
        CallQueuedClosure::class       => 'closure',
        CallQueuedListener::class      => 'class',
        BroadcastEvent::class          => 'event',
    ],

    /*
    * Interface that once implemented, will make the job tenant aware
    */
    'tenant_aware_interface' => TenantAware::class,

    /*
     * Interface that once implemented, will make the job not tenant aware
     */
    'not_tenant_aware_interface' => NotTenantAware::class,

    /*
     * Jobs tenant aware even if these don't implement the TenantAware interface.
     */
    'tenant_aware_jobs' => [
        // ...
    ],

    /*
     * Jobs not tenant aware even if these don't implement the NotTenantAware interface.
     */
    'not_tenant_aware_jobs' => [
        // Broadcast payloads carry no tenant context in this app.
        BroadcastEvent::class,

        // Core notification broadcasts resolve to these events through
        // queueable_to_job; they run on the broadcastable queue only.
        CreateOrderNotification::class,
        UpdateOrderNotification::class,

        // Core index and statistic jobs operate on the shared database.
        DeleteCatalogRuleIndex::class,
        UpdateCreateCatalogRuleIndex::class,
        UpdateCreateProductIndex::class,
        UpdateCreateVisitableIndex::class,
        UpdateCreateVisitIndex::class,
        UpdateCreateSearchTerm::class,
        UpdateCreateInventoryIndex::class,
        UpdateCreatePriceIndex::class,
        UpdateCreateElasticSearchIndex::class,
        DeleteElasticSearchIndex::class,
        ProcessSitemap::class,

        // Marketing automation packages own a single shared database.
        AttributeOrder::class,
        FlushOutboxBatch::class,
        HistoricalSync::class,
        IngestTrigger::class,
        ProcessBackInStockSubscriptions::class,
        ProcessEnrolmentStep::class,
        ProcessWebhook::class,
        RecordMessageEvent::class,
        SendConsentConfirmation::class,
        SyncWhatsAppTemplates::class,
    ],
];
