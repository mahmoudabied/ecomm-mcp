<?php

it('fires the viewed product beacon from the product page', function () {
    $product = $this->createStorefrontProduct();

    $this->get(route('shop.product_or_category.index', $product->url_key))
        ->assertOk()
        ->assertSee('navigator.sendBeacon', escape: false)
        ->assertSee(route('klaviyo.track.viewed-product'), escape: false)
        ->assertSee('var productId = '.(int) $product->id, escape: false);
});
