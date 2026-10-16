<?php

namespace Webkul\Klaviyo\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Webkul\Klaviyo\Models\Connection;
use Webkul\Klaviyo\Models\SyncJob;

class SyncJobFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = SyncJob::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'connection_id' => Connection::factory(),
            'entity'        => $this->faker->randomElement(['customers', 'orders', 'catalog']),
            'status'        => 'queued',
            'cursor_id'     => 0,
            'processed'     => 0,
            'total'         => 0,
        ];
    }

    /**
     * A job currently running.
     */
    public function running(): static
    {
        return $this->state(fn () => [
            'status'     => 'running',
            'started_at' => now(),
        ]);
    }
}
