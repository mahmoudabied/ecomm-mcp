<?php

namespace Webkul\MarketingAutomation\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Webkul\MarketingAutomation\Contracts\AbandonedCart as AbandonedCartContract;
use Webkul\MarketingAutomation\Database\Factories\AbandonedCartFactory;

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
            'detected_at'              => 'datetime',
            'restore_token_expires_at' => 'datetime',
            'cart_snapshot'            => 'array',
        ];
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return AbandonedCartFactory::new();
    }
}
