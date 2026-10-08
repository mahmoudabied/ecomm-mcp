<?php

namespace Webkul\MarketingAutomation\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Webkul\MarketingAutomation\Contracts\MessageLog as MessageLogContract;
use Webkul\MarketingAutomation\Database\Factories\MessageLogFactory;
use Webkul\MarketingAutomation\Enums\MessageChannel;
use Webkul\MarketingAutomation\Enums\MessageStatus;

class MessageLog extends Model implements MessageLogContract
{
    use HasFactory;

    /**
     * Table name.
     *
     * @var string
     */
    protected $table = 'automation_message_logs';

    /**
     * Guarded attributes.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * Assign a UUID when the record is first created.
     */
    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (! $model->uuid) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'message_channel' => MessageChannel::class,
            'is_marketing'    => 'boolean',
            'is_test'         => 'boolean',
            'is_fallback'     => 'boolean',
            'links'           => 'array',
            'status'          => MessageStatus::class,
            'sent_at'         => 'datetime',
            'delivered_at'    => 'datetime',
            'opened_at'       => 'datetime',
            'clicked_at'      => 'datetime',
        ];
    }

    /**
     * Get the related records.
     */
    public function events()
    {
        return $this->hasMany(MessageEventProxy::modelClass());
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return MessageLogFactory::new();
    }
}
