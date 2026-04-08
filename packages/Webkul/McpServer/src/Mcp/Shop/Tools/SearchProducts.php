<?php

namespace Webkul\McpServer\Mcp\Shop\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request as McpRequest;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Webkul\McpServer\Concerns\ResolvesChannel;
use Webkul\McpServer\Traits\FormatsResponse;
use Webkul\Product\Repositories\ProductRepository;

#[Description('Search the product catalog for the resolved channel.')]
class SearchProducts extends Tool
{
    use FormatsResponse;
    use ResolvesChannel;

    public function __construct(
        protected ProductRepository $productRepository,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'query'       => $schema->string()->description('Search keyword or phrase')->required(),
            'category_id' => $schema->integer()->description('Filter by category ID'),
            'sort'        => $schema->string()
                ->enum(['name-asc', 'name-desc', 'price-asc', 'price-desc', 'created-desc'])
                ->default('created-desc')
                ->description('Sort order'),
            'per_page'    => $schema->integer()->default(15)->min(1)->max(100)->description('Results per page (1-100)'),
            'page'        => $schema->integer()->default(1)->description('Page number'),
        ];
    }

    public function handle(McpRequest $request): Response
    {
        $channel = $this->getChannel();

        $products = $this->productRepository->getAll([
            'search'      => $request->get('query'),
            'category_id' => $request->get('category_id'),
            'sort'        => $request->get('sort', 'created-desc'),
            'limit'       => $request->get('per_page', 15),
            'page'        => $request->get('page', 1),
        ]);

        return $this->jsonResponse([
            'channel_code' => $channel->code,
            'total'        => $products->total(),
            'current_page' => $products->currentPage(),
            'products'     => $products->map(fn ($product) => [
                'id'       => $product->id,
                'sku'      => $product->sku,
                'name'     => $product->name,
                'price'    => core()->formatPrice($product->price),
                'url_key'  => $product->url_key,
                'in_stock' => $product->isSaleable(),
            ])->values()->all(),
        ]);
    }
}
