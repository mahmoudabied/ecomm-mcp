<?php

namespace Webkul\MarketingAutomation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Webkul\Core\Models\Channel;
use Webkul\MarketingAutomation\Models\Attribution;
use Webkul\MarketingAutomation\Models\Flow;
use Webkul\Sales\Models\Order;

class AttributionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Attribution::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'order_id'      => Order::factory(),
            'flow_id'       => Flow::factory(),
            'step_key'      => 's1',
            'channel_id'    => Channel::query()->first()->id,
            'model'         => 'click',
            'revenue'       => 0,
            'currency'      => 'USD',
            'attributed_at' => now(),
        ];
    }
}
