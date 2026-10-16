<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Webkul\Attribute\Models\Attribute;
use Webkul\Checkout\Models\Cart;
use Webkul\Checkout\Models\CartItem;
use Webkul\Core\Repositories\CoreConfigRepository;
use Webkul\Customer\Models\Customer;
use Webkul\Product\Models\Product;
use Webkul\Product\Models\ProductAttributeValue;
use Webkul\Product\Models\ProductFlat;
use Webkul\Product\Models\ProductInventory;

beforeEach(function () {
    $channel = core()->getCurrentChannel();

    $channel->forceFill(['theme' => 'amacommerce'])->save();
});

it('injects the consent checkboxes into the amacommerce signup page', function () {
    $this->get(route('shop.customers.register.index'))
        ->assertOk()
        ->assertSee('id="ma-consent-checkboxes"', escape: false);
});

it('injects the contact capture into the amacommerce checkout page', function () {
    app(CoreConfigRepository::class)->create([
        'sales'   => ['checkout' => ['shopping_cart' => ['allow_guest_checkout' => 1]]],
        'channel' => core()->getCurrentChannel()->code,
        'locale'  => null,
    ]);

    $product = Product::factory()->create(['type' => 'simple']);

    ProductInventory::factory()->create([
        'product_id'          => $product->id,
        'inventory_source_id' => 1,
        'qty'                 => 100,
    ]);

    DB::table('product_inventory_indices')->insert([
        'qty'        => 100,
        'product_id' => $product->id,
        'channel_id' => core()->getCurrentChannel()->id,
    ]);

    $cart = Cart::factory()->create([
        'channel_id'     => core()->getCurrentChannel()->id,
        'is_active'      => 1,
        'items_count'    => 1,
        'customer_email' => 'guest@example.com',
    ]);

    CartItem::factory()->create([
        'cart_id'    => $cart->id,
        'product_id' => $product->id,
        'sku'        => $product->sku,
        'type'       => 'simple',
        'name'       => $product->name,
        'quantity'   => 1,
    ]);

    Session::put('cart', (object) ['id' => $cart->id]);

    $this->get(route('shop.checkout.onepage.index'))
        ->assertOk()
        ->assertSee('id="ma-contact-capture"', escape: false);
});

it('injects the back-in-stock form into the amacommerce product page', function () {
    $channelCode = core()->getCurrentChannel()->code;

    $product = Product::factory()->create(['type' => 'simple']);

    ProductFlat::query()->create([
        'product_id' => $product->id,
        'sku'        => $product->sku,
        'type'       => 'simple',
        'name'       => $product->name,
        'url_key'    => $product->sku,
        'status'     => 1,
        'channel'    => $channelCode,
        'locale'     => 'en',
    ]);

    ProductAttributeValue::query()->create([
        'product_id'   => $product->id,
        'attribute_id' => Attribute::query()->where('code', 'url_key')->value('id'),
        'text_value'   => $product->sku,
        'locale'       => 'en',
    ]);

    $this->get(route('shop.product_or_category.index', $product->sku))
        ->assertOk()
        ->assertSee('id="ma-back-in-stock"', escape: false);
});

it('injects the preferences link into the amacommerce profile page', function () {
    $customer = Customer::factory()->create();

    $this->actingAs($customer, 'customer')
        ->get(route('shop.customers.account.profile.edit'))
        ->assertOk()
        ->assertSee('id="ma-preferences-link"', escape: false);
});
