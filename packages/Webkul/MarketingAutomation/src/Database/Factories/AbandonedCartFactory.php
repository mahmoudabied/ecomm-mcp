<?php

namespace Webkul\MarketingAutomation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Webkul\Checkout\Models\Cart;
use Webkul\Core\Models\Channel;
use Webkul\MarketingAutomation\Models\AbandonedCart;
use Webkul\MarketingAutomation\Models\Contact;

class AbandonedCartFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = AbandonedCart::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'cart_id'                  => Cart::factory(),
            'contact_id'               => Contact::factory(),
            'channel_id'               => Channel::query()->first()->id,
            'detected_at'              => now(),
            'status'                   => 'abandoned',
            'restore_token_hash'       => hash('sha256', $this->faker->uuid()),
            'restore_token_expires_at' => now()->addDays(7),
            'cart_snapshot'            => [],
        ];
    }
}
