<?php

namespace Webkul\MarketingAutomation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Webkul\Core\Models\Channel;
use Webkul\MarketingAutomation\Models\BackInStockSubscription;
use Webkul\MarketingAutomation\Models\Contact;
use Webkul\Product\Models\Product;

class BackInStockSubscriptionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = BackInStockSubscription::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'contact_id' => Contact::factory(),
            'product_id' => Product::factory(),
            'channel_id' => Channel::query()->first()->id,
            'status'     => 'waiting',
        ];
    }
}
