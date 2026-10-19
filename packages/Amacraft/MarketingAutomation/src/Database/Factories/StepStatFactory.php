<?php

namespace Amacraft\MarketingAutomation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Webkul\Core\Models\Channel;
use Amacraft\MarketingAutomation\Models\Flow;
use Amacraft\MarketingAutomation\Models\StepStat;

class StepStatFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = StepStat::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'date'        => now()->toDateString(),
            'flow_id'     => Flow::factory(),
            'step_key'    => 's1',
            'channel_id'  => Channel::query()->first()->id,
            'metric'      => 'enrolled',
            'skip_reason' => '',
            'count'       => 1,
            'revenue'     => 0,
        ];
    }
}
