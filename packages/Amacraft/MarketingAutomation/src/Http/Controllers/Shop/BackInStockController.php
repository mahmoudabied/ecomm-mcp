<?php

namespace Amacraft\MarketingAutomation\Http\Controllers\Shop;

use Amacraft\MarketingAutomation\Models\BackInStockSubscription;
use Amacraft\MarketingAutomation\Services\ContactResolver;
use Amacraft\MarketingAutomation\Services\PhoneNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BackInStockController
{
    /**
     * The number of subscriptions one phone may request per day.
     */
    protected const PHONE_LIMIT_PER_DAY = 3;

    /**
     * The number of subscriptions one IP may request per day.
     */
    protected const IP_LIMIT_PER_DAY = 10;

    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected ContactResolver $contactResolver,
        protected PhoneNormalizer $phoneNormalizer,
    ) {}

    /**
     * Store a back-in-stock subscription. The subscription itself is the
     * scoped consent for this notification; no marketing consent is granted.
     */
    public function store(): JsonResponse
    {
        $data = request()->validate([
            'product_id'      => ['required', 'integer', 'exists:products,id'],
            'email'           => ['nullable', 'email', 'required_without:phone'],
            'phone'           => ['nullable', 'string', 'max:30', 'required_without:email'],
            'consent_channel' => ['required', 'in:email,sms,whatsapp'],
        ]);

        $phoneE164 = $this->validatedPhone($data['phone'] ?? null);

        $customerId = auth()->guard('customer')->id();

        $this->throttle($phoneE164);

        $channelId = (int) (core()->getCurrentChannel()?->id);

        $contact = $this->contactResolver->resolve(
            channelId: $channelId,
            customerId: $customerId,
            email: $data['email'] ?? null,
            phone: $phoneE164,
            trusted: $customerId !== null,
        );

        $subscription = BackInStockSubscription::query()->updateOrCreate(
            [
                'contact_id' => $contact->id,
                'product_id' => $data['product_id'],
                'channel_id' => $channelId,
            ],
            [
                'status'      => 'waiting',
                'trigger_key' => sprintf('bis:%d:%s', $contact->id, (string) Str::ulid()),
            ],
        );

        return response()->json([
            'message' => trans('marketing_automation::app.shop.back-in-stock.created'),
            'data'    => ['id' => $subscription->id],
        ], 201);
    }

    /**
     * The E.164 phone, rejecting unparsable or invalid numbers outright so a
     * typo never turns into an unreachable subscription.
     */
    protected function validatedPhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $normalized = $this->phoneNormalizer->normalize($phone);

        if ($normalized === null) {
            throw ValidationException::withMessages([
                'phone' => trans('marketing_automation::app.shop.back-in-stock.invalid-phone'),
            ]);
        }

        return $normalized;
    }

    /**
     * Cap subscription requests per phone and per IP per day, so a scripted
     * visitor cannot pump SMS sends through the restock notification.
     */
    protected function throttle(?string $phoneE164): void
    {
        if ($phoneE164 !== null && ! RateLimiter::attempt('bis-phone:'.$phoneE164, self::PHONE_LIMIT_PER_DAY, fn () => null, 86400)) {
            throw ValidationException::withMessages([
                'phone' => trans('marketing_automation::app.shop.back-in-stock.throttled'),
            ]);
        }

        if (! RateLimiter::attempt('bis-ip:'.(string) request()->ip(), self::IP_LIMIT_PER_DAY, fn () => null, 86400)) {
            throw ValidationException::withMessages([
                'phone' => trans('marketing_automation::app.shop.back-in-stock.throttled'),
            ]);
        }
    }
}
