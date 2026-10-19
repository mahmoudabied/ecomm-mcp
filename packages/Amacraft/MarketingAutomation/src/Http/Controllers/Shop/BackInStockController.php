<?php

namespace Amacraft\MarketingAutomation\Http\Controllers\Shop;

use Amacraft\MarketingAutomation\Models\BackInStockSubscription;
use Amacraft\MarketingAutomation\Services\ContactResolver;
use Illuminate\Http\JsonResponse;

class BackInStockController
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected ContactResolver $contactResolver,
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

        $channelId = (int) (core()->getCurrentChannel()?->id);

        $customerId = auth()->guard('customer')->id();

        $contact = $this->contactResolver->resolve(
            channelId: $channelId,
            customerId: $customerId,
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? null,
            trusted: $customerId !== null,
        );

        $subscription = BackInStockSubscription::query()->updateOrCreate(
            [
                'contact_id' => $contact->id,
                'product_id' => $data['product_id'],
                'channel_id' => $channelId,
            ],
            ['status' => 'waiting'],
        );

        return response()->json([
            'message' => trans('marketing_automation::app.shop.back-in-stock.created'),
            'data'    => ['id' => $subscription->id],
        ], 201);
    }
}
