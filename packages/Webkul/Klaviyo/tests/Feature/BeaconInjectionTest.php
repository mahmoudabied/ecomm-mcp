<?php

use Webkul\Attribute\Models\Attribute;
use Webkul\Product\Models\Product;
use Webkul\Product\Models\ProductAttributeValue;
use Webkul\Product\Models\ProductFlat;

it('fires the viewed product beacon from the product page', function () {
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
        ->assertSee('navigator.sendBeacon', escape: false)
        ->assertSee(route('klaviyo.track.viewed-product'), escape: false)
        ->assertSee('var productId = '.(int) $product->id, escape: false);
});
