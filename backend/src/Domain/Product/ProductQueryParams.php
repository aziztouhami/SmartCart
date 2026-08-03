<?php

namespace App\Domain\Product;

use Symfony\Component\HttpFoundation\Request;

/**
 * The shared filter set accepted by both `GET /api/products` and
 * `GET /api/products/facets` — parsed once instead of duplicated across
 * both controller methods.
 */
final class ProductQueryParams
{
    public const ALLOWED_SORTS = ['name', 'price', 'createdAt', 'rating', 'popularity'];

    public function __construct(
        public readonly ?string $search,
        public readonly ?int $category,
        public readonly ?int $brand,
        public readonly ?int $type,
        public readonly ?float $minPrice,
        public readonly ?float $maxPrice,
        public readonly ?bool $inStock,
        public readonly ?array $attributes,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $inStock = $request->query->has('inStock')
            ? filter_var($request->query->get('inStock'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            : null;

        $attributes = array_filter((array) $request->query->all('attr'), fn ($v) => null !== $v && '' !== $v);

        return new self(
            search: $request->query->get('q') ?: null,
            category: $request->query->get('category') ? (int) $request->query->get('category') : null,
            brand: $request->query->get('brand') ? (int) $request->query->get('brand') : null,
            type: $request->query->get('type') ? (int) $request->query->get('type') : null,
            minPrice: null !== $request->query->get('minPrice') ? (float) $request->query->get('minPrice') : null,
            maxPrice: null !== $request->query->get('maxPrice') ? (float) $request->query->get('maxPrice') : null,
            inStock: $inStock,
            attributes: $attributes ?: null,
        );
    }
}
