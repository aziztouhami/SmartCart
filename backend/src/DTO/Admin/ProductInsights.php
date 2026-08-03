<?php

namespace App\DTO\Admin;

use App\DTO\Product\ProductListItem;
use App\Entity\Product;

class ProductInsights
{
    public ProductListItem $product;

    public array $interactions;

    /** @var ProductListItem[] */
    public array $frequentlyBoughtWith;

    /** @var ProductListItem[] */
    public array $frequentlyViewedWith;

    /**
     * @param array{view: int, cart: int, purchase: int, rating: int} $interactionCounts
     * @param Product[]                                               $boughtWith
     * @param Product[]                                               $viewedWith
     */
    public static function build(Product $product, array $interactionCounts, array $boughtWith, array $viewedWith): self
    {
        $dto = new self();
        $dto->product = ProductListItem::fromEntity($product);
        $dto->interactions = [
            'views' => $interactionCounts['view'],
            'cartAdds' => $interactionCounts['cart'],
            'purchases' => $interactionCounts['purchase'],
            'ratings' => $interactionCounts['rating'],
        ];
        $dto->frequentlyBoughtWith = array_map(fn ($p) => ProductListItem::fromEntity($p), $boughtWith);
        $dto->frequentlyViewedWith = array_map(fn ($p) => ProductListItem::fromEntity($p), $viewedWith);

        return $dto;
    }
}
