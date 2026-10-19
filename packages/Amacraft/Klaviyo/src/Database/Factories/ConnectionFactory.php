<?php

namespace Amacraft\Klaviyo\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Webkul\Core\Models\Channel;
use Amacraft\Klaviyo\Models\Connection;

class ConnectionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Connection::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'channel_id'              => Channel::query()->first()->id,
            'api_key'                 => $this->faker->uuid(),
            'public_key'              => $this->faker->uuid(),
            'status'                  => 'connected',
            'last_error'              => null,
            'list_id'                 => null,
            'sms_list_id'             => null,
            'consent_cursor'          => null,
            'unsubscribed_metric_ids' => null,
            'connected_at'            => now(),
        ];
    }

    /**
     * A disconnected connection.
     */
    public function disconnected(): static
    {
        return $this->state(fn () => [
            'status'       => 'disconnected',
            'connected_at' => null,
        ]);
    }
}
