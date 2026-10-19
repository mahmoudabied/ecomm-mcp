<?php

namespace Amacraft\MarketingAutomation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Webkul\Core\Models\Channel;
use Amacraft\MarketingAutomation\Models\Contact;

class ContactFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Contact::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'channel_id'  => Channel::query()->first()->id,
            'customer_id' => null,
            'email'       => strtolower($this->faker->unique()->safeEmail()),
            'phone_e164'  => null,
            'phone_raw'   => null,
            'first_name'  => $this->faker->firstName(),
            'last_name'   => $this->faker->lastName(),
            'locale'      => 'en',
            'country'     => null,
        ];
    }
}
