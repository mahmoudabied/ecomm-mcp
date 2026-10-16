<?php

namespace Webkul\MarketingAutomation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Webkul\MarketingAutomation\Enums\MessageChannel;
use Webkul\MarketingAutomation\Enums\ProviderStatus;
use Webkul\MarketingAutomation\Models\ProviderConnection;

class ProviderConnectionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ProviderConnection::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name'            => $this->faker->company(),
            'message_channel' => MessageChannel::Sms->value,
            'driver'          => 'twilio',
            'credentials'     => [],
            'sender'          => 'Bagisto',
            'status'          => ProviderStatus::Untested->value,
        ];
    }

    /**
     * Scope the connection to the given SMS driver.
     */
    public function sms(string $driver = 'twilio'): static
    {
        return $this->state(fn () => [
            'message_channel' => MessageChannel::Sms->value,
            'driver'          => $driver,
        ]);
    }

    /**
     * Scope the connection to the given WhatsApp driver.
     */
    public function whatsapp(string $driver = 'meta_cloud'): static
    {
        return $this->state(fn () => [
            'message_channel' => MessageChannel::Whatsapp->value,
            'driver'          => $driver,
        ]);
    }

    /**
     * Mark the connection as tested successfully.
     */
    public function connected(): static
    {
        return $this->state(fn () => [
            'status'         => ProviderStatus::Connected->value,
            'last_tested_at' => now(),
        ]);
    }
}
