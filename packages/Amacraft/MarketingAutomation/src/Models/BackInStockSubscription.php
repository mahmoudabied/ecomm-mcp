<?php

namespace Amacraft\MarketingAutomation\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Amacraft\MarketingAutomation\Contracts\BackInStockSubscription as BackInStockSubscriptionContract;
use Amacraft\MarketingAutomation\Database\Factories\BackInStockSubscriptionFactory;
use Webkul\Product\Models\ProductProxy;

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
     * Get the parent record.
     */
    public function contact()
    {
        return $this->belongsTo(ContactProxy::modelClass());
    }

    /**
     * Get the parent record.
     */
    public function product()
    {
        return $this->belongsTo(ProductProxy::modelClass());
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return BackInStockSubscriptionFactory::new();
    }
}
