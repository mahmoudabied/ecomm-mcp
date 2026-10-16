<?php

namespace Webkul\MarketingAutomation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Webkul\MarketingAutomation\Enums\ConsentSource;
use Webkul\MarketingAutomation\Enums\ConsentStatus;
use Webkul\MarketingAutomation\Enums\MessageChannel;
use Webkul\MarketingAutomation\Models\ConsentEvent;
use Webkul\MarketingAutomation\Models\Contact;

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
