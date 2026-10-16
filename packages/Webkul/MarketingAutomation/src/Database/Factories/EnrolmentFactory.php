<?php

namespace Webkul\MarketingAutomation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Webkul\Core\Models\Channel;
use Webkul\MarketingAutomation\Enums\EnrolmentStatus;
use Webkul\MarketingAutomation\Models\Contact;
use Webkul\MarketingAutomation\Models\Enrolment;
use Webkul\MarketingAutomation\Models\FlowVersion;

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
