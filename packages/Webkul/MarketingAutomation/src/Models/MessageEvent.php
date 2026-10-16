<?php

namespace Webkul\MarketingAutomation\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Webkul\MarketingAutomation\Contracts\MessageEvent as MessageEventContract;
use Webkul\MarketingAutomation\Database\Factories\MessageEventFactory;
use Webkul\MarketingAutomation\Enums\MessageEventType;

class MessageEvent extends Model implements MessageEventContract
{
    use HasFactory;

    /**
     * Message events are append-only; there is no update timestamp.
     *
     * @var string|null
     */
    const UPDATED_AT = null;

    /**
     * Table name.
     *
     * @var string
     */
    protected $table = 'automation_message_events';

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
            'type'        => MessageEventType::class,
            'meta'        => 'array',
            'occurred_at' => 'datetime',
            'created_at'  => 'datetime',
        ];
    }

    /**
     * Get the parent record.
     */
    public function messageLog()
    {
        return $this->belongsTo(MessageLogProxy::modelClass());
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return MessageEventFactory::new();
    }
}
