<?php

namespace Amacraft\MarketingAutomation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Amacraft\MarketingAutomation\Enums\ConsentSource;
use Amacraft\MarketingAutomation\Enums\ConsentStatus;
use Amacraft\MarketingAutomation\Enums\MessageChannel;
use Amacraft\MarketingAutomation\Models\ConsentEvent;
use Amacraft\MarketingAutomation\Models\Contact;

class ConsentEventFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ConsentEvent::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'contact_id' => Contact::factory(),
            'channel'    => MessageChannel::Email->value,
            'status'     => ConsentStatus::Granted->value,
            'source'     => ConsentSource::Registration->value,
            'ip'         => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'meta'       => [],
            'created_at' => now(),
        ];
    }
}
