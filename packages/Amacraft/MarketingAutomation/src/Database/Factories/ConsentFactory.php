<?php

namespace Amacraft\MarketingAutomation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Amacraft\MarketingAutomation\Enums\ConsentSource;
use Amacraft\MarketingAutomation\Enums\ConsentStatus;
use Amacraft\MarketingAutomation\Enums\MessageChannel;
use Amacraft\MarketingAutomation\Models\Consent;
use Amacraft\MarketingAutomation\Models\Contact;

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
