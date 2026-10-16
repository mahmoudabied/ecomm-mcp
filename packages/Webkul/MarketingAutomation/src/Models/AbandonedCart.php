<?php

namespace Webkul\MarketingAutomation\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Checkout\Models\CartProxy;
use Webkul\Core\Models\ChannelProxy;
use Webkul\MarketingAutomation\Contracts\AbandonedCart as AbandonedCartContract;
use Webkul\MarketingAutomation\Database\Factories\AbandonedCartFactory;
use Webkul\MarketingAutomation\Enums\AbandonedCartStatus;

class AbandonedCart extends Model implements AbandonedCartContract
{
    use HasFactory;

    /**
     * Table name.
     *
     * @var string
     */
    protected $table = 'automation_abandoned_carts';

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
            'status'                   => AbandonedCartStatus::class,
            'detected_at'              => 'datetime',
            'restore_token_expires_at' => 'datetime',
            'cart_snapshot'            => 'array',
        ];
    }

    /**
     * Get the abandoned cart record's original cart.
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(CartProxy::modelClass());
    }

    /**
     * Get the contact the cart belongs to.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(ContactProxy::modelClass());
    }

    /**
     * Get the sales channel of the cart.
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(ChannelProxy::modelClass(), 'channel_id');
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return AbandonedCartFactory::new();
    }
}
