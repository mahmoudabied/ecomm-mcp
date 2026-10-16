<?php

namespace Webkul\MarketingAutomation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Webkul\MarketingAutomation\Enums\StepType;
use Webkul\MarketingAutomation\Models\Flow;
use Webkul\MarketingAutomation\Models\FlowVersion;

class FlowVersionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = FlowVersion::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'flow_id'         => Flow::factory(),
            'version'         => 1,
            'definition'      => ['entry' => 's1', 'steps' => ['s1' => ['type' => StepType::Exit->value]]],
            'definition_hash' => hash('sha256', $this->faker->uuid()),
        ];
    }
}
