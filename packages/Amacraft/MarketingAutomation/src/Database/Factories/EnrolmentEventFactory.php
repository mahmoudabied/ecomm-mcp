<?php

namespace Amacraft\MarketingAutomation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Amacraft\MarketingAutomation\Enums\JourneyEvent;
use Amacraft\MarketingAutomation\Models\Enrolment;
use Amacraft\MarketingAutomation\Models\EnrolmentEvent;

class EnrolmentEventFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = EnrolmentEvent::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'enrolment_id' => Enrolment::factory(),
            'step_key'     => 's1',
            'event'        => JourneyEvent::Entered->value,
            'created_at'   => now(),
        ];
    }
}
