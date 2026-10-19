<?php

namespace Amacraft\MarketingAutomation\Tests\Concerns;

use Webkul\Product\Models\Product;
use Webkul\Product\Repositories\ProductRepository;

trait CreatesStorefrontProducts
{
    /**
     * A simple product visible on the storefront, built through the repository the admin form uses,
     * so it resolves on every supported Bagisto version.
     */
    public function createStorefrontProduct(int $qty = 100): Product
    {
        $repository = app(ProductRepository::class);

        $sku = fake()->uuid();

        $product = $repository->create([
            'type'                => 'simple',
            'attribute_family_id' => 1,
            'sku'                 => $sku,
        ]);

        $repository->update([
            'sku'                  => $sku,
            'url_key'              => fake()->unique()->slug(),
            'name'                 => 'Storefront Product',
            'short_description'    => 'A short description.',
            'description'          => 'A description.',
            'price'                => 99.5,
            'weight'               => 1,
            'product_number'       => fake()->numerify('ma-#########'),
            'channel'              => core()->getDefaultChannelCode(),
            'locale'               => app()->getLocale(),
            'channels'             => [core()->getDefaultChannel()->id],
            'status'               => 1,
            'visible_individually' => 1,
            'guest_checkout'       => 1,
            'manage_stock'         => 1,
            'inventories'          => [1 => $qty],
        ], $product->id);

        return $product->refresh();
    }
}
