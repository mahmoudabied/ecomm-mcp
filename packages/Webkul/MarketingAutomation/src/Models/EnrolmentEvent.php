<?php

namespace Webkul\MarketingAutomation\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Webkul\MarketingAutomation\Contracts\EnrolmentEvent as EnrolmentEventContract;
use Webkul\MarketingAutomation\Database\Factories\EnrolmentEventFactory;
use Webkul\MarketingAutomation\Enums\JourneyEvent;
use Webkul\MarketingAutomation\Enums\SkipReason;

class EnrolmentEvent extends Model implements EnrolmentEventContract
{
    use HasFactory;

    /**
     * Journey events are append-only; there is no update timestamp.
     *
     * @var string|null
     */
    const UPDATED_AT = null;

    /**
     * Table name.
     *
     * @var string
     */
    protected $table = 'automation_enrolment_events';

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
            'event'      => JourneyEvent::class,
            'reason'     => SkipReason::class,
            'meta'       => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get the parent record.
     */
    public function enrolment()
    {
        return $this->belongsTo(EnrolmentProxy::modelClass());
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return EnrolmentEventFactory::new();
    }
}
