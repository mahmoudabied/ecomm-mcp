<?php

namespace Webkul\Klaviyo\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Webkul\Klaviyo\Models\Connection;
use Webkul\Klaviyo\Models\OutboxItem;

class OutboxItemFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = OutboxItem::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'connection_id' => Connection::factory(),
            'kind'          => 'event',
            'unique_id'     => $this->faker->unique()->slug(),
            'payload'       => ['data' => ['type' => 'event']],
            'status'        => 'pending',
            'attempts'      => 0,
        ];
    }

    /**
     * An already delivered row.
     */
    public function sent(): static
    {
        return $this->state(fn () => [
            'status'  => 'sent',
            'sent_at' => now(),
        ]);
    }

    /**
     * A row that exhausted its attempts.
     */
    public function failed(): static
    {
        return $this->state(fn () => [
            'status'     => 'failed',
            'attempts'   => 10,
            'last_error' => 'Permanent error',
        ]);
    }
}
