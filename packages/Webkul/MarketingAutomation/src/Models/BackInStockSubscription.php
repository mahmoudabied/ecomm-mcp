<?php

namespace Webkul\MarketingAutomation\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Webkul\MarketingAutomation\Contracts\BackInStockSubscription as BackInStockSubscriptionContract;
use Webkul\MarketingAutomation\Database\Factories\BackInStockSubscriptionFactory;

class BackInStockSubscription extends Model implements BackInStockSubscriptionContract
{
    use HasFactory;

    /**
     * Table name.
     *
     * @var string
     */
    protected $table = 'automation_back_in_stock_subscriptions';

    /**
     * Guarded attributes.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'notified_at' => 'datetime',
        ];
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return BackInStockSubscriptionFactory::new();
    }
}
