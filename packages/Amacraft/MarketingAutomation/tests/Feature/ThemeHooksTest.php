<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Webkul\AmacommerceTheme\Providers\AmacommerceThemeServiceProvider;
use Webkul\Checkout\Models\Cart;
use Webkul\Checkout\Models\CartItem;
use Webkul\Core\Repositories\CoreConfigRepository;
use Webkul\Customer\Models\Customer;
use Webkul\Product\Models\Product;
use Webkul\Product\Models\ProductInventory;

/**
 * The shop themes installed in this app; the amacommerce theme only exists
 * when its package is required, so stock installs just get the default one.
 */
function installedShopThemes(): array
{
    $themes = ['default'];

    if (class_exists(AmacommerceThemeServiceProvider::class)) {
        $themes[] = 'amacommerce';
    }

    return $themes;
}

/**
 * Point the current channel at the theme under test.
 */
function useTheme(string $theme): void
{
    core()->getCurrentChannel()->forceFill(['theme' => $theme])->save();
}

it('injects the consent checkboxes into the :theme signup page', function (string $theme) {
    useTheme($theme);

    $this->get(route('shop.customers.register.index'))
        ->assertOk()
        ->assertSee('id="ma-consent-checkboxes"', escape: false);
})->with(installedShopThemes());

it('injects the contact capture into the :theme checkout page', function (string $theme) {
    useTheme($theme);

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
})->with(installedShopThemes());

it('injects the back-in-stock form into the :theme product page', function (string $theme) {
    useTheme($theme);

    $product = $this->createStorefrontProduct(qty: 0);

    $this->get(route('shop.product_or_category.index', $product->url_key))
        ->assertOk()
        ->assertSee('id="ma-back-in-stock"', escape: false);
})->with(installedShopThemes());

it('injects the preferences link into the :theme profile page', function (string $theme) {
    useTheme($theme);

    $customer = Customer::factory()->create();

    $this->actingAs($customer, 'customer')
        ->get(route('shop.customers.account.profile.edit'))
        ->assertOk()
        ->assertSee('id="ma-preferences-link"', escape: false);
})->with(installedShopThemes());

it('scopes every precompiled utility under .ma-root so the host theme keeps its own classes', function (string $file) {
    $css = file_get_contents(dirname(__DIR__, 2).'/src/Resources/assets/css/'.$file);

    preg_match_all('/(?:^|[{}])\s*([^{}@][^{}]*)\{/', $css, $matches);

    $unscoped = array_filter(
        $matches[1],
        fn (string $selector) => trim($selector) !== '.ma-root' && ! str_starts_with(trim($selector), '.ma-root '),
    );

    expect($unscoped)->toBe([])
        ->and($css)->not->toContain('prefers-color-scheme');
})->with(['ma-shop.css', 'ma-admin.css']);

it('wraps the injected storefront snippets in the .ma-root scope', function () {
    $html = view('marketing_automation::shop.consent-checkboxes')->render();

    expect($html)->toContain('class="ma-root"')
        ->and($html)->toContain('id="ma-consent-checkboxes"');
});
