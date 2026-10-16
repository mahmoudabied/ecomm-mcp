<?php

namespace Webkul\Klaviyo\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Webkul\Core\Models\Channel;
use Webkul\Klaviyo\Services\OutboxWriter;
use Webkul\Klaviyo\Services\PayloadMapper;
use Webkul\MarketingAutomation\Models\Contact;
use Webkul\MarketingAutomation\Services\ContactResolver;
use Webkul\Product\Models\Product;

class TrackController
{
    /**
     * Create a new track controller instance.
     */
    public function __construct(
        protected OutboxWriter $outbox,
        protected PayloadMapper $mapper,
        protected ContactResolver $contactResolver,
    ) {}

    /**
     * Record one Viewed Product for the known visitor, if any.
     */
    public function viewedProduct(Request $request): Response
    {
        $contact = $this->visitorContact();

        if ($contact === null) {
            return response()->noContent();
        }

        $product = Product::query()->find((int) $request->input('product_id'));

        if ($product === null) {
            return response()->noContent();
        }

        $payload = $this->mapper->viewedProduct($product, $contact);

        $uniqueId = sprintf(
            '%s:viewed_product:%d:%d:%s',
            $this->channelCodeOf($contact),
            $product->id,
            $contact->id,
            now()->format('Y-m-d-H')
        );

        $this->outbox->writeToChannel((int) $contact->channel_id, fn () => [['event', substr($uniqueId, 0, 191), $payload]]);

        return response()->noContent();
    }

    /**
     * The logged-in customer's contact, else the one behind the capture cookie.
     */
    protected function visitorContact(): ?Contact
    {
        $customer = auth()->guard('customer')->user();

        if ($customer) {
            return $this->contactResolver->forCustomer($customer, (int) core()->getCurrentChannel()->id);
        }

        $uuid = request()->cookie('ma_contact');

        if (! filled($uuid)) {
            return null;
        }

        return Contact::query()->where('uuid', $uuid)->first();
    }

    /**
     * The channel code of a contact's channel.
     */
    protected function channelCodeOf(Contact $contact): string
    {
        return Channel::query()->where('id', $contact->channel_id)->value('code') ?? 'default';
    }
}
