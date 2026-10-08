<?php

namespace Webkul\MarketingAutomation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Webkul\Core\Models\Channel;
use Webkul\MarketingAutomation\Enums\MessageChannel;
use Webkul\MarketingAutomation\Enums\MessageStatus;
use Webkul\MarketingAutomation\Models\Contact;
use Webkul\MarketingAutomation\Models\Flow;
use Webkul\MarketingAutomation\Models\MessageLog;

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
