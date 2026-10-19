<?php

namespace Amacraft\MarketingAutomation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Amacraft\MarketingAutomation\Models\ProviderConnection;
use Amacraft\MarketingAutomation\Models\WhatsAppTemplate;

class WhatsAppTemplateFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = WhatsAppTemplate::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'provider_connection_id' => ProviderConnection::factory()->whatsapp(),
            'external_id'            => $this->faker->uuid(),
            'name'                   => 'hello_world',
            'language'               => 'en_US',
            'category'               => 'marketing',
            'status'                 => 'approved',
            'components'             => [],
            'variable_count'         => 1,
            'synced_at'              => now(),
        ];
    }
}
