<?php

namespace Webkul\Agent\Actions\Products;

use Illuminate\Database\Eloquent\Builder;
use Webkul\Agent\Actions\AgentAction;
use Webkul\Product\Models\Product;
use Webkul\Product\Repositories\ProductRepository;

class SearchProducts extends AgentAction
{
    public function __construct(protected ProductRepository $productRepository) {}

    public function name(): string
    {
        return 'products_search';
    }

    public function summary(): string
    {
        return 'Search catalog products by name or SKU.';
    }

    public function permission(): string
    {
        return 'products';
    }

    public function isMutating(): bool
    {
        return false;
    }

    public function rules(): array
    {
        return [
            'query' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function handle(array $params): array
    {
        /** @var Builder<Product> $builder */
        $builder = $this->productRepository->getModel()->newQuery();

        $products = $builder
            ->when($params['query'] ?? null, function (Builder $query, string $words) {
                $escaped = '%'.addcslashes($words, '%_\\').'%';

                $query->where(function (Builder $sub) use ($escaped) {
                    $sub->where('name', 'like', $escaped)
                        ->orWhere('sku', 'like', $escaped);
                });
            })
            ->latest('id')
            ->limit($params['limit'] ?? 20)
            ->get();

        return [
            'products' => $products->map(fn (Product $product): array => [
                'id' => $product->getKey(),
                'sku' => $product->getAttribute('sku'),
                'name' => $product->getAttribute('name'),
                'description' => $product->getAttribute('description'),
                'price' => ($price = $product->getAttribute('price')) !== null ? (float) $price : 0.0,
                'quantity' => (int) ($product->getAttribute('quantity') ?? 0),
            ])->all(),
        ];
    }
}
