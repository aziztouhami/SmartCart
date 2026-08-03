<?php

namespace App\DTO\Admin;

use App\DTO\Product\ProductListItem;
use App\Entity\Product;

class BehaviorOverview
{
    public array $overview;

    /** @var ProductListItem[] */
    public array $topViewed;

    /** @var ProductListItem[] */
    public array $topAddedToCart;

    /** @var ProductListItem[] */
    public array $topPurchased;

    /** @var ProductListItem[] */
    public array $topRated;

    /**
     * @param array{view: int, cart: int, purchase: int, rating: int} $typeBreakdown
     * @param Product[]                                               $topViewed
     * @param Product[]                                               $topCarted
     * @param Product[]                                               $topBought
     * @param Product[]                                               $topRated
     */
    public static function build(array $typeBreakdown, array $topViewed, array $topCarted, array $topBought, array $topRated): self
    {
        $dto = new self();
        $dto->overview = [
            'views' => $typeBreakdown['view'],
            'cartAdds' => $typeBreakdown['cart'],
            'purchases' => $typeBreakdown['purchase'],
            'ratings' => $typeBreakdown['rating'],
        ];
        $dto->topViewed = array_map(fn ($p) => ProductListItem::fromEntity($p), $topViewed);
        $dto->topAddedToCart = array_map(fn ($p) => ProductListItem::fromEntity($p), $topCarted);
        $dto->topPurchased = array_map(fn ($p) => ProductListItem::fromEntity($p), $topBought);
        $dto->topRated = array_map(fn ($p) => ProductListItem::fromEntity($p), $topRated);

        return $dto;
    }
}
