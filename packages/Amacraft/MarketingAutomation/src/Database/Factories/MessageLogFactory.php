<?php

namespace Amacraft\MarketingAutomation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Webkul\Core\Models\Channel;
use Amacraft\MarketingAutomation\Enums\MessageChannel;
use Amacraft\MarketingAutomation\Enums\MessageStatus;
use Amacraft\MarketingAutomation\Models\Contact;
use Amacraft\MarketingAutomation\Models\Flow;
use Amacraft\MarketingAutomation\Models\MessageLog;

class MessageLogFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = MessageLog::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'flow_id'         => Flow::factory(),
            'step_key'        => 's1',
            'contact_id'      => Contact::factory(),
            'channel_id'      => Channel::query()->first()->id,
            'message_channel' => MessageChannel::Email->value,
            'is_marketing'    => true,
            'recipient'       => strtolower($this->faker->safeEmail()),
            'locale'          => 'en',
            'status'          => MessageStatus::Queued->value,
            'attempts'        => 0,
        ];
    }
}
