<?php

namespace Webkul\MarketingAutomation\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Webkul\MarketingAutomation\Contracts\ProviderConnection as ProviderConnectionContract;
use Webkul\MarketingAutomation\Database\Factories\ProviderConnectionFactory;
use Webkul\MarketingAutomation\Enums\MessageChannel;
use Webkul\MarketingAutomation\Enums\ProviderStatus;

class ProviderConnection extends Model implements ProviderConnectionContract
{
    use HasFactory;

    /**
     * Table name.
     *
     * @var string
     */
    protected $table = 'automation_provider_connections';

    /**
     * Guarded attributes.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * Assign a UUID and a webhook secret when the connection is first created.
     */
    protected static function booted(): void
    {
        static::creating(function (self $connection) {
            if (! $connection->uuid) {
                $connection->uuid = (string) Str::uuid();
            }

            if (! $connection->webhook_secret) {
                $connection->webhook_secret = Str::random(40);
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'credentials'     => 'encrypted:array',
            'message_channel' => MessageChannel::class,
            'status'          => ProviderStatus::class,
            'last_tested_at'  => 'datetime',
        ];
    }

    /**
     * Get the WhatsApp templates synced for this connection.
     */
    public function templates()
    {
        return $this->hasMany(WhatsAppTemplateProxy::modelClass());
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return ProviderConnectionFactory::new();
    }
}
