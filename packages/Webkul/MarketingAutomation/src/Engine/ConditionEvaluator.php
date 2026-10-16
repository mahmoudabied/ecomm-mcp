<?php

namespace Webkul\MarketingAutomation\Engine;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\Checkout\Models\Cart;
use Webkul\MarketingAutomation\Enums\ConsentStatus;
use Webkul\MarketingAutomation\Enums\MessageChannel;
use Webkul\MarketingAutomation\Models\Enrolment;
use Webkul\MarketingAutomation\Services\ConsentService;
use Webkul\Sales\Models\Order;

class ConditionEvaluator
{
    /**
     * Create a new condition evaluator instance.
     */
    public function __construct(
        protected ConsentService $consentService,
    ) {}

    /**
     * Evaluate a rule tree: {all|any: [{field, op, value} | nested rule]}.
     */
    public function evaluate(array $rule, Enrolment $enrolment): bool
    {
        if (isset($rule['all'])) {
            foreach ((array) $rule['all'] as $nested) {
                if (! $this->evaluate((array) $nested, $enrolment)) {
                    return false;
                }
            }

            return true;
        }

        if (isset($rule['any'])) {
            foreach ((array) $rule['any'] as $nested) {
                if ($this->evaluate((array) $nested, $enrolment)) {
                    return true;
                }
            }

            return false;
        }

        $field = (string) ($rule['field'] ?? '');
        $op = (string) ($rule['op'] ?? 'eq');
        $value = $rule['value'] ?? null;

        return $this->compare($this->resolve($field, $enrolment, $value), $op, $value);
    }

    /**
     * Read the current value of a condition field.
     */
    protected function resolve(string $field, Enrolment $enrolment, mixed $value): mixed
    {
        $contact = $enrolment->contact;

        switch ($field) {
            case 'has_ordered_since_enrolment':
                return $this->ordersOf($enrolment)
                    ->where('created_at', '>=', $enrolment->created_at)
                    ->exists();

            case 'order_count':
                return $this->ordersOf($enrolment)->count();

            case 'lifetime_spend':
                return (float) $this->ordersOf($enrolment)->sum('base_grand_total');

            case 'last_order_total':
                return (float) ($this->lastOrder($enrolment)?->base_grand_total ?? 0);

            case 'customer_group_id':
                return $contact->customer?->customer_group_id;

            case 'locale':
                return $contact->locale;

            case 'channel_id':
                return $enrolment->channel_id;

            case 'cart_value':
                return (float) ($this->triggerCart($enrolment)?->base_grand_total
                    ?? $this->activeCustomerCart($enrolment)?->base_grand_total);

            case 'cart_contains_product':
            case 'cart_contains_category':
                return $this->cartContains($enrolment, $field, $value);

            case 'order_contains_product':
            case 'order_contains_category':
                return $this->orderContains($enrolment, $field, $value);

            default:
                if (str_starts_with($field, 'has_consent.')) {
                    $channel = MessageChannel::from(substr($field, strlen('has_consent.')));

                    return $this->consentService->status($contact, $channel) === ConsentStatus::Granted;
                }

                return null;
        }
    }

    /**
     * Apply the comparison operator.
     */
    protected function compare(mixed $actual, string $op, mixed $value): bool
    {
        if (in_array($op, ['is_true', 'is_false'], true)) {
            $truthy = filter_var($actual, FILTER_VALIDATE_BOOLEAN);

            return $op === 'is_true' ? $truthy : ! $truthy;
        }

        return match ($op) {
            'eq'     => $actual == $value,
            'neq'    => $actual != $value,
            'gt'     => $actual > $value,
            'gte'    => $actual >= $value,
            'lt'     => $actual < $value,
            'lte'    => $actual <= $value,
            'in'     => in_array($actual, (array) $value),
            'not_in' => ! in_array($actual, (array) $value),
            default  => false,
        };
    }

    /**
     * Non-canceled orders of the enrolment's contact; a contact with no
     * customer and no email can never match an order.
     */
    protected function ordersOf(Enrolment $enrolment): Builder
    {
        $email = $enrolment->contact->email;
        $customerId = $enrolment->contact->customer_id;

        if ($customerId === null && ($email === null || $email === '')) {
            return Order::query()->whereRaw('1 = 0');
        }

        return Order::query()
            ->where(function (Builder $query) use ($customerId, $email) {
                $query
                    ->when($customerId, fn (Builder $q) => $q->orWhere('customer_id', $customerId))
                    ->when($email, fn (Builder $q) => $q->orWhere('customer_email', $email));
            })
            ->where('status', '!=', 'canceled');
    }

    /**
     * The most recent matching order.
     */
    protected function lastOrder(Enrolment $enrolment): ?Order
    {
        return $this->ordersOf($enrolment)->latest('created_at')->first();
    }

    /**
     * The cart attached to the trigger, when the payload carries one.
     */
    protected function triggerCart(Enrolment $enrolment): ?Cart
    {
        $cartId = $enrolment->trigger_payload['cart_id'] ?? null;

        return $cartId !== null ? Cart::query()->find($cartId) : null;
    }

    /**
     * The customer's current active cart, when the contact is a customer.
     */
    protected function activeCustomerCart(Enrolment $enrolment): ?Cart
    {
        if (! $enrolment->contact->customer_id) {
            return null;
        }

        return Cart::query()
            ->where('customer_id', $enrolment->contact->customer_id)
            ->where('is_active', 1)
            ->first();
    }

    /**
     * Whether the trigger cart (or the customer's active cart) contains the product or category.
     */
    protected function cartContains(Enrolment $enrolment, string $field, mixed $wanted): bool
    {
        $cart = $this->triggerCart($enrolment) ?? $this->activeCustomerCart($enrolment);

        if ($cart === null) {
            return false;
        }

        return $this->matchProducts($cart->items->pluck('product_id')->unique()->all(), $field, $wanted);
    }

    /**
     * Whether the trigger order (or the last order) contains the product or category.
     */
    protected function orderContains(Enrolment $enrolment, string $field, mixed $wanted): bool
    {
        $orderId = $enrolment->trigger_payload['order_id'] ?? null;

        $order = $orderId !== null
            ? Order::query()->find($orderId)
            : $this->lastOrder($enrolment);

        if ($order === null) {
            return false;
        }

        return $this->matchProducts($order->items->pluck('product_id')->unique()->all(), $field, $wanted);
    }

    /**
     * Match held product ids against the requested product or category ids.
     */
    protected function matchProducts(array $productIds, string $field, mixed $wanted): bool
    {
        $wanted = array_map('intval', (array) $wanted);

        if ($productIds === [] || $wanted === []) {
            return false;
        }

        if (str_contains($field, 'category')) {
            return DB::table('product_categories')
                ->whereIn('product_id', $productIds)
                ->whereIn('category_id', $wanted)
                ->exists();
        }

        return count(array_intersect($productIds, $wanted)) > 0;
    }
}
