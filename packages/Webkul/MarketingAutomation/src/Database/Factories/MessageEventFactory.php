<?php

namespace Webkul\MarketingAutomation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Webkul\MarketingAutomation\Enums\MessageEventType;
use Webkul\MarketingAutomation\Models\MessageEvent;
use Webkul\MarketingAutomation\Models\MessageLog;

class MessageEventFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = MessageEvent::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'message_log_id' => MessageLog::factory(),
            'type'           => MessageEventType::Sent->value,
            'occurred_at'    => now(),
        ];
    }
}
