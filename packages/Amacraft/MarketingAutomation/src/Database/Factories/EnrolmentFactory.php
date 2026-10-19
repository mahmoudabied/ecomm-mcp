<?php

namespace Amacraft\MarketingAutomation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Webkul\Core\Models\Channel;
use Amacraft\MarketingAutomation\Enums\EnrolmentStatus;
use Amacraft\MarketingAutomation\Models\Contact;
use Amacraft\MarketingAutomation\Models\Enrolment;
use Amacraft\MarketingAutomation\Models\FlowVersion;

class EnrolmentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Enrolment::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $version = FlowVersion::factory()->create();

        return [
            'flow_id'         => $version->flow_id,
            'flow_version_id' => $version->id,
            'contact_id'      => Contact::factory(),
            'channel_id'      => Channel::query()->first()->id,
            'trigger_key'     => 'trigger:'.$this->faker->uuid(),
            'trigger_payload' => [],
            'status'          => EnrolmentStatus::Active->value,
            'next_run_at'     => now(),
        ];
    }
}
