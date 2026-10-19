<?php

namespace Amacraft\MarketingAutomation\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Amacraft\MarketingAutomation\Contracts\Attribution as AttributionContract;
use Amacraft\MarketingAutomation\Database\Factories\AttributionFactory;

class Attribution extends Model implements AttributionContract
{
    use HasFactory;

    /**
     * Table name.
     *
     * @var string
     */
    protected $table = 'automation_attributions';

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
            'attributed_at' => 'datetime',
        ];
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return AttributionFactory::new();
    }
}
