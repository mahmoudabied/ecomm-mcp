<?php

namespace Webkul\MarketingAutomation\Rendering;

use Illuminate\Support\Facades\DB;
use Webkul\Product\Models\Product;

class Recommendations
{
    /**
     * How many candidate products to fetch before filtering, so category and
     * saleability filters still leave enough rows to fill the requested count.
     */
    protected const CANDIDATE_MULTIPLIER = 4;

    /**
     * The products to promote inside a product_grid block, already formatted
     * for the email views.
     *
     * @param  array<string, mixed>  $context
     * @return array<int, array{name: string, price_formatted: string, image_url: string, url: string}>
     */
    public function forContext(array $context, string $source, int $count, int $channelId): array
    {
        $baseProductIds = $this->baseProductIds($context);

        if ($baseProductIds === []) {
            return [];
        }

        $candidateIds = $this->candidateIds($source, $baseProductIds, $count);

        if ($candidateIds === []) {
            return [];
        }

        $products = Product::query()
            ->whereIn('id', $candidateIds)
            ->get()
            ->filter(fn (Product $product) => $product->isSaleable());

        return $products
            ->map(fn (Product $product) => $this->present($product))
            ->take($count)
            ->values()
            ->all();
    }

    /**
     * The products already in the cart or order behind the message; they are
     * the seed of every source and are never recommended themselves.
     *
     * @param  array<string, mixed>  $context
     * @return array<int, int>
     */
    protected function baseProductIds(array $context): array
    {
        $items = $context['cart']['items'] ?? $context['order']['items'] ?? [];

        $ids = [];

        foreach ((array) $items as $item) {
            if (! empty($item['product_id'])) {
                $ids[] = (int) $item['product_id'];
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Resolve the source into product ids, bestsellers falling back from
     * same-category picks to the overall winners.
     *
     * @param  array<int, int>  $baseProductIds
     * @return array<int, int>
     */
    protected function candidateIds(string $source, array $baseProductIds, int $count): array
    {
        $limit = $count * self::CANDIDATE_MULTIPLIER;

        $ids = match ($source) {
            'related'     => $this->relationIds('product_relations', $baseProductIds, $limit),
            'recommended' => $this->relationIds('product_up_sells', $baseProductIds, $limit),
            'bestsellers' => $this->bestsellerIds($baseProductIds, $limit),
            default       => [],
        };

        return array_values(array_diff($ids, $baseProductIds));
    }

    /**
     * The linked products of a mapping table (relations or upsells).
     *
     * @param  array<int, int>  $baseProductIds
     * @return array<int, int>
     */
    protected function relationIds(string $table, array $baseProductIds, int $limit): array
    {
        return DB::table($table)
            ->whereIn('parent_id', $baseProductIds)
            ->limit($limit)
            ->pluck('child_id')
            ->all();
    }

    /**
     * The top sellers of the last 90 days, preferring the categories the
     * customer is already shopping in.
     *
     * @param  array<int, int>  $baseProductIds
     * @return array<int, int>
     */
    protected function bestsellerIds(array $baseProductIds, int $limit): array
    {
        $overall = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.created_at', '>=', now()->subDays(90))
            ->groupBy('order_items.product_id')
            ->orderByDesc(DB::raw('SUM(order_items.qty_ordered)'))
            ->limit($limit)
            ->pluck('order_items.product_id')
            ->all();

        $categoryIds = $this->categoryIdsOf($baseProductIds);

        if ($categoryIds === []) {
            return $overall;
        }

        $inSameCategories = DB::table('product_categories')
            ->whereIn('category_id', $categoryIds)
            ->whereIn('product_id', $overall)
            ->pluck('product_id')
            ->all();

        return array_values(array_unique([
            ...$inSameCategories,
            ...$overall,
        ]));
    }

    /**
     * The categories the base products sit in.
     *
     * @param  array<int, int>  $baseProductIds
     * @return array<int, int>
     */
    protected function categoryIdsOf(array $baseProductIds): array
    {
        return DB::table('product_categories')
            ->whereIn('product_id', $baseProductIds)
            ->pluck('category_id')
            ->all();
    }

    /**
     * Format one product for the email view.
     *
     * @return array{name: string, price_formatted: string, image_url: string, url: string}
     */
    protected function present(Product $product): array
    {
        return [
            'name'            => (string) $product->name,
            'price_formatted' => core()->formatPrice((float) $product->price),
            'image_url'       => (string) (product_image()->getProductBaseImage($product)['medium_image_url'] ?? ''),
            'url'             => route('shop.product_or_category.index', $product->url_key),
        ];
    }
}
