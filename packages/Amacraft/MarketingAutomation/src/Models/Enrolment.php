<?php

namespace Amacraft\MarketingAutomation\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Amacraft\MarketingAutomation\Contracts\Enrolment as EnrolmentContract;
use Amacraft\MarketingAutomation\Database\Factories\EnrolmentFactory;
use Amacraft\MarketingAutomation\Enums\EnrolmentStatus;

class Enrolment extends Model implements EnrolmentContract
{
    use HasFactory;

    /**
     * Table name.
     *
     * @var string
     */
    protected $table = 'automation_enrolments';

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
            'trigger_payload' => 'array',
            'status'          => EnrolmentStatus::class,
            'next_run_at'     => 'datetime',
            'locked_until'    => 'datetime',
            'completed_at'    => 'datetime',
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
     * Get the parent record.
     */
    public function flowVersion()
    {
        return $this->belongsTo(FlowVersionProxy::modelClass());
    }

    /**
     * Get the parent record.
     */
    public function contact()
    {
        return $this->belongsTo(ContactProxy::modelClass());
    }

    /**
     * Get the related records.
     */
    public function events()
    {
        return $this->hasMany(EnrolmentEventProxy::modelClass());
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return EnrolmentFactory::new();
    }
}
