<?php

namespace Amacraft\MarketingAutomation\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Amacraft\MarketingAutomation\Contracts\Consent as ConsentContract;
use Amacraft\MarketingAutomation\Database\Factories\ConsentFactory;
use Amacraft\MarketingAutomation\Enums\ConsentSource;
use Amacraft\MarketingAutomation\Enums\ConsentStatus;
use Amacraft\MarketingAutomation\Enums\MessageChannel;

class Consent extends Model implements ConsentContract
{
    use HasFactory;

    /**
     * Table name.
     *
     * @var string
     */
    protected $table = 'automation_consents';

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
            'changed_at' => 'datetime',
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
        return ConsentFactory::new();
    }
}
