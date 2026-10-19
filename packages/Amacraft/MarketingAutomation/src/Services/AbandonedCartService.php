<?php

namespace Amacraft\MarketingAutomation\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Webkul\Checkout\Contracts\Cart;
use Webkul\Core\Contracts\Channel;
use Amacraft\MarketingAutomation\Contracts\AbandonedCart;
use Amacraft\MarketingAutomation\Contracts\Contact;
use Amacraft\MarketingAutomation\DTOs\TriggerEvent;
use Amacraft\MarketingAutomation\Engine\EnrolmentService;
use Amacraft\MarketingAutomation\Enums\AbandonedCartStatus;
use Amacraft\MarketingAutomation\Enums\TriggerType;
use Amacraft\MarketingAutomation\Repositories\AbandonedCartRepository;
use Amacraft\MarketingAutomation\Repositories\FlowRepository;

class AbandonedCartService
{
    /**
     * How far back, in days, an idle cart is still worth recovering.
     */
    protected const LOOKBACK_DAYS = 7;

    /**
     * The default idle time, in minutes, before a cart counts as abandoned.
     */
    protected const DEFAULT_DELAY_MINUTES = 60;

    /**
     * Create a new abandoned cart service instance.
     */
    public function __construct(
        protected AbandonedCartRepository $abandonedCartRepository,
        protected FlowRepository $flowRepository,
        protected ContactResolver $contactResolver,
        protected EnrolmentService $enrolmentService,
        protected RestoreToken $restoreToken,
        protected Settings $settings,
    ) {}

    /**
     * Scan every sales channel and return the number of carts newly recorded as abandoned.
     */
    public function detect(): int
    {
        $detected = 0;

        foreach (core()->getAllChannels() as $channel) {
            $delays = $this->delaysFor($channel);

            $this->abandonedCartRepository->eachIdleCart(
                $channel->id,
                now()->subMinutes($delays->min()),
                now()->subDays(self::LOOKBACK_DAYS),
                function (Cart $cart) use ($channel, $delays, &$detected) {
                    if ($this->process($cart, $channel, $delays->max())) {
                        $detected++;
                    }
                },
            );
        }

        return $detected;
    }

    /**
     * Record one idle cart and enrol its shopper; false when the idle state was already recorded.
     */
    protected function process(Cart $cart, Channel $channel, int $longestDelayMinutes): bool
    {
        $contact = $this->contactFor($cart);

        if ($contact === null) {
            return false;
        }

        $triggerKey = 'cart:'.$cart->id.':'.$cart->updated_at->getTimestamp();

        $record = $this->abandonedCartRepository->findOneWhere(['cart_id' => $cart->id]);

        if ($record?->trigger_key === $triggerKey) {
            $this->offerToLongerDelayFlows($cart, $contact, $record, $longestDelayMinutes);

            return false;
        }

        $record = $this->recordAbandoned($cart, $channel, $contact, $record, $triggerKey);

        $this->enrol($cart, $contact, $record);

        Event::dispatch('marketing_automation.cart.abandoned', [$record]);

        return true;
    }

    /**
     * Store the idle state with a fresh restore token, which retires the links already sent.
     */
    protected function recordAbandoned(Cart $cart, Channel $channel, Contact $contact, ?AbandonedCart $record, string $triggerKey): AbandonedCart
    {
        $attributes = [
            'contact_id'    => $contact->id,
            'channel_id'    => $cart->channel_id,
            'status'        => AbandonedCartStatus::Abandoned->value,
            'trigger_key'   => $triggerKey,
            'detected_at'   => now(),
            'cart_snapshot' => $this->snapshotOf($cart),
        ];

        $record = $record === null
            ? $this->abandonedCartRepository->create($attributes + [
                'cart_id'                  => $cart->id,
                'restore_token_hash'       => $this->restoreToken->hash(Str::random(40)),
                'restore_token_expires_at' => now(),
            ])
            : $this->abandonedCartRepository->update($attributes, $record->id);

        return $this->abandonedCartRepository->update([
            'restore_token_hash'       => $this->restoreToken->hash($this->restoreToken->for($record)),
            'restore_token_expires_at' => now()->addDays($this->settings->restoreLinkDays($channel->code)),
        ], $record->id);
    }

    /**
     * Offer an already recorded cart again while a flow with a longer delay may still be due.
     */
    protected function offerToLongerDelayFlows(Cart $cart, Contact $contact, AbandonedCart $record, int $longestDelayMinutes): void
    {
        $idleAtDetection = (int) $cart->updated_at->diffInMinutes($record->detected_at);

        if ($idleAtDetection < $longestDelayMinutes) {
            $this->enrol($cart, $contact, $record);
        }
    }

    /**
     * Offer the idle cart to the matching flows; the engine drops duplicates by key and re-entry window.
     */
    protected function enrol(Cart $cart, Contact $contact, AbandonedCart $record): void
    {
        $this->enrolmentService->enrol(new TriggerEvent(
            type: TriggerType::CartAbandoned,
            channelId: (int) $cart->channel_id,
            customerId: $contact->customer_id,
            email: $contact->email,
            phone: $contact->phone_e164,
            triggerKey: (string) $record->trigger_key,
            payload: [
                'cart_id'           => $cart->id,
                'abandoned_cart_id' => $record->id,
                'idle_minutes'      => (int) $cart->updated_at->diffInMinutes(now()),
            ],
        ));
    }

    /**
     * The contact behind a cart: the customer, the guest email or the billing address phone.
     */
    protected function contactFor(Cart $cart): ?Contact
    {
        $channelId = (int) $cart->channel_id;

        if (! empty($cart->customer_id)) {
            return $this->contactResolver->resolve($channelId, (int) $cart->customer_id);
        }

        if (filled($cart->customer_email)) {
            return $this->contactResolver->resolve(
                $channelId,
                null,
                (string) $cart->customer_email,
                names: $this->namesFromCart($cart),
            );
        }

        $billing = $cart->billing_address;

        if (! filled($billing?->phone)) {
            return null;
        }

        return $this->contactResolver->resolve(
            $channelId,
            null,
            null,
            (string) $billing->phone,
            (string) $billing->country,
            names: $this->namesFromCart($cart),
        );
    }

    /**
     * The idle delays of the channel's active cart-abandoned flows, or the default when there are none.
     *
     * @return Collection<int, int>
     */
    protected function delaysFor(Channel $channel): Collection
    {
        $delays = $this->flowRepository->activeCartAbandonedDelays($channel->id, self::DEFAULT_DELAY_MINUTES);

        return $delays->isEmpty()
            ? collect([self::DEFAULT_DELAY_MINUTES])
            : $delays;
    }

    /**
     * A small snapshot of the cart at detection time.
     *
     * @return array{items_count: int, grand_total: mixed, items: array<int, array<string, mixed>>}
     */
    protected function snapshotOf(Cart $cart): array
    {
        return [
            'items_count' => (int) $cart->items_count,
            'grand_total' => $cart->grand_total,
            'items'       => $cart->items->map(fn ($item) => [
                'name'  => $item->name,
                'qty'   => $item->quantity,
                'price' => $item->price,
            ])->all(),
        ];
    }

    /**
     * The guest names captured on the cart.
     *
     * @return array{first: ?string, last: ?string}
     */
    protected function namesFromCart(Cart $cart): array
    {
        return [
            'first' => $cart->customer_first_name,
            'last'  => $cart->customer_last_name,
        ];
    }
}
