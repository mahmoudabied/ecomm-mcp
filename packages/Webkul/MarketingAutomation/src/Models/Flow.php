<?php

namespace Webkul\MarketingAutomation\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Webkul\Core\Models\ChannelProxy;
use Webkul\MarketingAutomation\Contracts\Flow as FlowContract;
use Webkul\MarketingAutomation\Database\Factories\FlowFactory;
use Webkul\MarketingAutomation\Enums\FlowStatus;
use Webkul\MarketingAutomation\Enums\FlowType;
use Webkul\MarketingAutomation\Enums\TriggerType;

class Flow extends Model implements FlowContract
{
    use HasFactory;

    /**
     * Table name.
     *
     * @var string
     */
    protected $table = 'automation_flows';

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
            'trigger_type'     => TriggerType::class,
            'trigger_config'   => 'array',
            'type'             => FlowType::class,
            'status'           => FlowStatus::class,
            'exit_goals'       => 'array',
            'draft_definition' => 'array',
            'activated_at'     => 'datetime',
            'paused_at'        => 'datetime',
            'resumed_at'       => 'datetime',
        ];
    }

    /**
     * Get the related records.
     */
    public function versions()
    {
        return $this->hasMany(FlowVersionProxy::modelClass());
    }

    /**
     * Get the related records.
     */
    public function enrolments()
    {
        return $this->hasMany(EnrolmentProxy::modelClass());
    }

    /**
     * Get the related records.
     */
    public function channels()
    {
        return $this->belongsToMany(ChannelProxy::modelClass(), 'automation_flow_channels');
    }

    /**
     * Get the parent record.
     */
    public function currentVersion()
    {
        return $this->belongsTo(FlowVersionProxy::modelClass(), 'current_version_id');
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return FlowFactory::new();
    }
}
