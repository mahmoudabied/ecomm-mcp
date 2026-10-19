<?php

namespace Amacraft\Klaviyo\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Webkul\Core\Models\ChannelProxy;
use Amacraft\Klaviyo\Contracts\Connection as ConnectionContract;
use Amacraft\Klaviyo\Database\Factories\ConnectionFactory;

class Connection extends Model implements ConnectionContract
{
    use HasFactory;

    /**
     * Table name.
     *
     * @var string
     */
    protected $table = 'klaviyo_connections';

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
            'api_key'                 => 'encrypted',
            'unsubscribed_metric_ids' => 'array',
            'consent_cursor'          => 'datetime',
            'connected_at'            => 'datetime',
        ];
    }

    /**
     * Get the parent record.
     */
    public function channel()
    {
        return $this->belongsTo(ChannelProxy::modelClass());
    }

    /**
     * Get the related records.
     */
    public function outboxItems()
    {
        return $this->hasMany(OutboxItemProxy::modelClass());
    }

    /**
     * Get the related records.
     */
    public function syncJobs()
    {
        return $this->hasMany(SyncJobProxy::modelClass());
    }

    /**
     * Whether the connection is usable for sends.
     */
    public function isActive(): bool
    {
        return $this->status === 'connected';
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return ConnectionFactory::new();
    }
}
