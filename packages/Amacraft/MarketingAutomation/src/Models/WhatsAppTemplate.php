<?php

namespace Amacraft\MarketingAutomation\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Amacraft\MarketingAutomation\Contracts\WhatsAppTemplate as WhatsAppTemplateContract;
use Amacraft\MarketingAutomation\Database\Factories\WhatsAppTemplateFactory;

class WhatsAppTemplate extends Model implements WhatsAppTemplateContract
{
    use HasFactory;

    /**
     * Table name.
     *
     * @var string
     */
    protected $table = 'automation_whatsapp_templates';

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
            'components' => 'array',
            'synced_at'  => 'datetime',
        ];
    }

    /**
     * Get the parent record.
     */
    public function providerConnection()
    {
        return $this->belongsTo(ProviderConnectionProxy::modelClass());
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return WhatsAppTemplateFactory::new();
    }
}
