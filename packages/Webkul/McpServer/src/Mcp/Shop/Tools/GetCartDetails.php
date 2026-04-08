<?php

namespace Webkul\McpServer\Mcp\Shop\Tools;

use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Webkul\Checkout\Repositories\CartRepository;
use Webkul\McpServer\Concerns\ResolvesChannel;
use Webkul\McpServer\Traits\FormatsResponse;

#[Description('Retrieve the current cart for the authenticated customer.')]
class GetCartDetails extends Tool
{
    use FormatsResponse;
    use ResolvesChannel;

    public function __construct(
        protected CartRepository $cartRepository,
    ) {}

    public function handle(): Response
    {
        $channel = $this->getChannel();

        $cart = cart()->getCart();

        if (! $cart) {
            return $this->jsonResponse([
                'channel_code' => $channel->code,
                'cart_id'      => null,
                'items_count'  => 0,
                'grand_total'  => core()->formatPrice(0),
                'items'        => [],
            ]);
        }

        return $this->jsonResponse([
            'channel_code' => $channel->code,
            'cart_id'      => $cart->id,
            'items_count'  => $cart->items_count,
            'grand_total'  => core()->formatPrice($cart->grand_total),
            'items'        => $cart->items->map(fn ($item) => [
                'id'       => $item->id,
                'sku'      => $item->sku,
                'name'     => $item->name,
                'quantity' => $item->quantity,
                'price'    => core()->formatPrice($item->price),
            ])->values()->all(),
        ]);
    }
}
