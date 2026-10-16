<?php

namespace Webkul\Klaviyo\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Webkul\Klaviyo\Contracts\OutboxItem as OutboxItemContract;
use Webkul\Klaviyo\Database\Factories\OutboxItemFactory;

class OutboxItem extends Model implements OutboxItemContract
{
    use HasFactory;

    /**
     * Table name.
     *
     * @var string
     */
    protected $table = 'klaviyo_outbox';

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
            'payload'      => 'array',
            'available_at' => 'datetime',
            'sent_at'      => 'datetime',
        ];
    }

    /**
     * Default the availability to now so flush commands pick the row up.
     */
    protected static function booted(): void
    {
        static::creating(function (self $model) {
            $model->available_at ??= now();
        });
    }

    /**
     * Get the parent record.
     */
    public function connection()
    {
        return $this->belongsTo(ConnectionProxy::modelClass());
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return OutboxItemFactory::new();
    }
}
