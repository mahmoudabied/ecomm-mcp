<?php

namespace Webkul\MarketingAutomation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Webkul\MarketingAutomation\Enums\ConsentSource;
use Webkul\MarketingAutomation\Enums\ConsentStatus;
use Webkul\MarketingAutomation\Enums\MessageChannel;
use Webkul\MarketingAutomation\Models\Consent;
use Webkul\MarketingAutomation\Models\Contact;

class ConsentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Consent::class;

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
            'changed_at' => now(),
        ];
    }

    /**
     * Grant consent for the given message channel.
     */
    public function granted(MessageChannel $channel): static
    {
        return $this->state(fn () => [
            'channel' => $channel->value,
            'status'  => ConsentStatus::Granted->value,
        ]);
    }
}
