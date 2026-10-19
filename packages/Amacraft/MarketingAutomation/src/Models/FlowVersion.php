<?php

namespace Amacraft\MarketingAutomation\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Amacraft\MarketingAutomation\Contracts\FlowVersion as FlowVersionContract;
use Amacraft\MarketingAutomation\Database\Factories\FlowVersionFactory;

class FlowVersion extends Model implements FlowVersionContract
{
    use HasFactory;

    /**
     * Versions are immutable; there is no update timestamp.
     *
     * @var string|null
     */
    const UPDATED_AT = null;

    /**
     * Table name.
     *
     * @var string
     */
    protected $table = 'automation_flow_versions';

    /**
     * Guarded attributes.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * Guard against updates, because versions are immutable snapshots.
     */
    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Flow versions are immutable.');
        });
    }

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'definition' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get the parent record.
     */
    public function flow()
    {
        return $this->belongsTo(FlowProxy::modelClass());
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return FlowVersionFactory::new();
    }
}
