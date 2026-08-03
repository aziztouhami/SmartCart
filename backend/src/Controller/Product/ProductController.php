<?php

namespace App\Controller\Product;

use App\Domain\Http\Pagination;
use App\Domain\Product\BestSellersResolver;
use App\Domain\Product\ProductQueryParams;
use App\Domain\Product\PromotedProductsSelector;
use App\Domain\Product\SortParams;
use App\DTO\Pagination\PaginatedResponse;
use App\DTO\Product\ProductActivity;
use App\DTO\Product\ProductAutocompleteItem;
use App\DTO\Product\ProductDetail;
use App\DTO\Product\ProductListItem;
use App\Repository\ProductRepository;
use App\Repository\PromotionRepository;
use App\Repository\ReviewRepository;
use App\Service\ProductActivityService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/products', name: 'api_products_')]
#[OA\Tag(name: 'Products', description: 'Product catalogue — listing, search, filters and detail')]
class ProductController extends AbstractController
{
    public function __construct(
        private ProductRepository $productRepository,
        private ReviewRepository $reviewRepository,
        private PromotionRepository $promotionRepository,
        private ProductActivityService $productActivityService,
        private PromotedProductsSelector $promotedProductsSelector,
        private BestSellersResolver $bestSellersResolver,
    ) {
    }

    /**
     * List products with pagination, filters and sorting.
     *
     * Query params:
     *   page (int), limit (int, max 50)
     *   q (string) — full-text search on name, description, category
     *   category (int) — filter by category id
     *   brand (int) — filter by brand id
     *   type (int) — filter by product type id
     *   attr[slug]=value (repeatable) — filter by feature/attribute value, e.g. attr[color]=Black
     *   minPrice / maxPrice (float)
     *   inStock (bool)
     *   sort: name | price | createdAt | rating | popularity  (default: createdAt)
     *   order: asc | desc               (default: desc)
     */
    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/products',
        operationId: 'listProducts',
        summary: 'List products',
        description: 'Paginated product catalogue with full-text search, filters and sorting.',
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 12)),
            new OA\Parameter(name: 'q', in: 'query', description: 'Search query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'category', in: 'query', description: 'Category ID', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'brand', in: 'query', description: 'Brand ID', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'type', in: 'query', description: 'Product type ID', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'attr', in: 'query', description: 'Feature filters, e.g. attr[color]=Black&attr[ram]=8GB', schema: new OA\Schema(type: 'object', additionalProperties: new OA\AdditionalProperties(type: 'string'))),
            new OA\Parameter(name: 'minPrice', in: 'query', schema: new OA\Schema(type: 'number')),
            new OA\Parameter(name: 'maxPrice', in: 'query', schema: new OA\Schema(type: 'number')),
            new OA\Parameter(name: 'inStock', in: 'query', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'sort', in: 'query', schema: new OA\Schema(type: 'string', enum: ['name', 'price', 'createdAt', 'rating', 'popularity'])),
            new OA\Parameter(name: 'order', in: 'query', schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated product list'),
        ]
    )]
    public function list(Request $request): JsonResponse
    {
        $filters = ProductQueryParams::fromRequest($request);
        $sort = SortParams::fromRequest($request, ProductQueryParams::ALLOWED_SORTS, 'createdAt');
        $pagination = Pagination::fromRequest($request);

        $products = $this->productRepository->findWithFilters(
            search: $filters->search,
            categoryId: $filters->category,
            minPrice: $filters->minPrice,
            maxPrice: $filters->maxPrice,
            inStock: $filters->inStock,
            brandId: $filters->brand,
            productTypeId: $filters->type,
            attributes: $filters->attributes,
            sortBy: $sort->sortBy,
            sortOrder: $sort->sortOrder,
            page: $pagination->page,
            limit: $pagination->limit,
        );

        $total = $this->productRepository->countWithFilters(
            search: $filters->search,
            categoryId: $filters->category,
            minPrice: $filters->minPrice,
            maxPrice: $filters->maxPrice,
            inStock: $filters->inStock,
            brandId: $filters->brand,
            productTypeId: $filters->type,
            attributes: $filters->attributes,
        );

        $promoMap = $this->promotionRepository->findActiveForProducts($products);

        $paginated = PaginatedResponse::create(
            data: array_map(fn ($p) => ProductListItem::fromEntity($p, $promoMap[$p->getId()] ?? null), $products),
            total: $total,
            page: $pagination->page,
            limit: $pagination->limit,
        );

        return $this->json($paginated);
    }

    /**
     * Products with a currently-active promotion (any scope).
     */
    #[Route('/promotions', name: 'promotions', methods: ['GET'])]
    #[OA\Get(
        path: '/api/products/promotions',
        operationId: 'listPromotedProducts',
        summary: 'List currently promoted products',
        parameters: [
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Promoted products'),
        ]
    )]
    public function promotions(Request $request): JsonResponse
    {
        $limit = Pagination::fromRequest($request, defaultLimit: 20)->limit;

        $selection = $this->promotedProductsSelector->select($limit);

        return $this->json(array_map(
            fn ($p) => ProductListItem::fromEntity($p, $selection['promoMap'][$p->getId()]),
            $selection['products']
        ));
    }

    /**
     * Best-selling products by total units sold. Falls back to newest
     * products when there's no order history yet.
     */
    #[Route('/best-sellers', name: 'best_sellers', methods: ['GET'])]
    #[OA\Get(
        path: '/api/products/best-sellers',
        operationId: 'listBestSellers',
        summary: 'List best-selling products',
        parameters: [
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 10)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Best-selling products'),
        ]
    )]
    public function bestSellers(Request $request): JsonResponse
    {
        $limit = Pagination::fromRequest($request, defaultLimit: 10)->limit;

        $products = $this->bestSellersResolver->resolve($limit);
        $promoMap = $this->promotionRepository->findActiveForProducts($products);

        return $this->json(array_map(fn ($p) => ProductListItem::fromEntity($p, $promoMap[$p->getId()] ?? null), $products));
    }

    /**
     * Filter sidebar data for the current search/category/filter scope:
     * which brands, product types and feature values actually occur (with
     * counts), plus the price range — so the frontend only ever offers
     * filters that can return results, feature filters (e.g. "RAM") only
     * show up for product types that actually have that attribute, and
     * each facet narrows the *other* facets to what's still compatible
     * once a filter (e.g. color=grey) is picked.
     */
    #[Route('/facets', name: 'facets', methods: ['GET'])]
    #[OA\Get(
        path: '/api/products/facets',
        operationId: 'getProductFacets',
        summary: 'Get available filter values for the current search/category/filter scope',
        parameters: [
            new OA\Parameter(name: 'q', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'category', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'brand', in: 'query', description: 'Brand ID', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'type', in: 'query', description: 'Product type ID', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'attr', in: 'query', description: 'Feature filters, e.g. attr[color]=Black', schema: new OA\Schema(type: 'object', additionalProperties: new OA\AdditionalProperties(type: 'string'))),
            new OA\Parameter(name: 'minPrice', in: 'query', schema: new OA\Schema(type: 'number')),
            new OA\Parameter(name: 'maxPrice', in: 'query', schema: new OA\Schema(type: 'number')),
            new OA\Parameter(name: 'inStock', in: 'query', schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [new OA\Response(response: 200, description: 'Brands, product types, attribute values and price range available to filter by')]
    )]
    public function facets(Request $request): JsonResponse
    {
        $filters = ProductQueryParams::fromRequest($request);

        return $this->json($this->productRepository->getFacets(
            search: $filters->search,
            categoryId: $filters->category,
            brandId: $filters->brand,
            productTypeId: $filters->type,
            attributes: $filters->attributes,
            minPrice: $filters->minPrice,
            maxPrice: $filters->maxPrice,
            inStock: $filters->inStock,
        ));
    }

    /**
     * Intelligent autocomplete — returns products grouped by match tier:
     *   nameStart → nameContains → byBrand → byCategory
     */
    #[Route('/autocomplete', name: 'autocomplete', methods: ['GET'])]
    #[OA\Get(
        path: '/api/products/autocomplete',
        operationId: 'autocompleteProducts',
        summary: 'Grouped search autocomplete',
        description: 'Returns matching products grouped by match tier: nameStart, nameContains, byBrand, byCategory.',
        parameters: [
            new OA\Parameter(name: 'q', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [new OA\Response(response: 200, description: 'Grouped autocomplete suggestions')]
    )]
    public function autocomplete(Request $request): JsonResponse
    {
        $q = trim($request->query->get('q', ''));
        if (mb_strlen($q) < 1) {
            return $this->json(['nameStart' => [], 'nameContains' => [], 'byBrand' => [], 'byCategory' => []]);
        }

        $grouped = $this->productRepository->findForAutocompleteGrouped($q);

        return $this->json([
            'nameStart' => array_map([ProductAutocompleteItem::class, 'fromEntity'], $grouped['nameStart']),
            'nameContains' => array_map([ProductAutocompleteItem::class, 'fromEntity'], $grouped['nameContains']),
            'byBrand' => array_map([ProductAutocompleteItem::class, 'fromEntity'], $grouped['byBrand']),
            'byCategory' => array_map([ProductAutocompleteItem::class, 'fromEntity'], $grouped['byCategory']),
        ]);
    }

    /**
     * Get full product detail including average rating and review count.
     */
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(
        path: '/api/products/{id}',
        operationId: 'getProduct',
        summary: 'Get product detail',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Product detail'),
            new OA\Response(response: 404, description: 'Product not found'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $product = $this->productRepository->find($id);

        if (!$product) {
            return $this->json(['error' => 'Product not found'], Response::HTTP_NOT_FOUND);
        }

        $avgRating = $this->reviewRepository->getAverageRating($product);
        $reviewCount = $this->reviewRepository->countByProduct($product);
        $promotion = $this->promotionRepository->findActiveForProduct($product);

        return $this->json(ProductDetail::fromEntity($product, $avgRating, $reviewCount, $promotion));
    }

    /**
     * Live social-proof numbers for the product page: how many people are
     * looking at it right now, and how many active carts it's sitting in.
     * Meant to be polled every so often while the page is open, separately
     * from the (heavier, cached-by-the-browser-less-often) product detail.
     */
    #[Route('/{id}/activity', name: 'activity', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(
        path: '/api/products/{id}/activity',
        operationId: 'getProductActivity',
        summary: 'Get live "viewing now" / "in carts" counts for a product',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Live activity counts'),
            new OA\Response(response: 404, description: 'Product not found'),
        ]
    )]
    public function activity(int $id): JsonResponse
    {
        $product = $this->productRepository->find($id);
        if (!$product) {
            return $this->json(['error' => 'Product not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json(ProductActivity::fromArray($this->productActivityService->getActivity($product)));
    }
}
