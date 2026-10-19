<?php

namespace Amacraft\MarketingAutomation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Amacraft\MarketingAutomation\Enums\FlowStatus;
use Amacraft\MarketingAutomation\Enums\FlowType;
use Amacraft\MarketingAutomation\Enums\TriggerType;
use Amacraft\MarketingAutomation\Models\Flow;

class FlowFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Flow::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name'                 => $this->faker->words(3, true),
            'template_key'         => null,
            'trigger_type'         => TriggerType::CustomerRegistered->value,
            'trigger_config'       => [],
            'type'                 => FlowType::Marketing->value,
            'status'               => FlowStatus::Draft->value,
            'reentry_window_hours' => 168,
            'exit_goals'           => [],
            'draft_definition'     => null,
        ];
    }

    /**
     * Make the flow active.
     */
    public function active(): static
    {
        return $this->state(fn () => [
            'status'       => FlowStatus::Active->value,
            'activated_at' => now(),
        ]);
    }

    /**
     * Make the flow transactional.
     */
    public function transactional(): static
    {
        return $this->state(fn () => [
            'type' => FlowType::Transactional->value,
        ]);
    }
}
