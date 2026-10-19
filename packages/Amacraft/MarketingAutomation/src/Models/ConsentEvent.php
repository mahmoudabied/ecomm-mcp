<?php

namespace Amacraft\MarketingAutomation\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Amacraft\MarketingAutomation\Contracts\ConsentEvent as ConsentEventContract;
use Amacraft\MarketingAutomation\Database\Factories\ConsentEventFactory;
use Amacraft\MarketingAutomation\Enums\ConsentSource;
use Amacraft\MarketingAutomation\Enums\ConsentStatus;
use Amacraft\MarketingAutomation\Enums\MessageChannel;

class ConsentEvent extends Model implements ConsentEventContract
{
    use HasFactory;

    /**
     * Consent events are append-only; there is no update timestamp.
     *
     * @var string|null
     */
    const UPDATED_AT = null;

    /**
     * Table name.
     *
     * @var string
     */
    protected $table = 'automation_consent_events';

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
            'channel'    => MessageChannel::class,
            'status'     => ConsentStatus::class,
            'source'     => ConsentSource::class,
            'meta'       => 'array',
            'created_at' => 'datetime',
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
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return ConsentEventFactory::new();
    }
}
