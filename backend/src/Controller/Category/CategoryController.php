<?php

namespace App\Controller\Category;

use App\DTO\Category\CategoryItem;
use App\DTO\Category\CategoryTree;
use App\DTO\Pagination\PaginatedResponse;
use App\DTO\Product\ProductListItem;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Repository\PromotionRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/categories', name: 'api_categories_')]
#[OA\Tag(name: 'Categories', description: 'Category tree and category-level product listing')]
class CategoryController extends AbstractController
{
    public function __construct(
        private CategoryRepository $categoryRepository,
        private ProductRepository $productRepository,
        private PromotionRepository $promotionRepository,
    ) {}

    /**
     * Return all root categories, each with their direct subcategories.
     */
    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/categories',
        operationId: 'listCategories',
        summary: 'Category tree',
        description: 'Returns all root-level categories with their direct subcategories.',
        responses: [new OA\Response(response: 200, description: 'Category tree')]
    )]
    public function list(): JsonResponse
    {
        $roots = $this->categoryRepository->findRoots();

        return $this->json(array_map(
            fn($c) => CategoryTree::fromEntity($c),
            $roots
        ));
    }

    /**
     * Get a single category with its subcategories.
     */
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(
        path: '/api/categories/{id}',
        operationId: 'getCategory',
        summary: 'Get category detail',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Category with subcategories'),
            new OA\Response(response: 404, description: 'Category not found'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $category = $this->categoryRepository->find($id);

        if (!$category) {
            return $this->json(['error' => 'Category not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json(CategoryTree::fromEntity($category));
    }

    /**
     * List products belonging to a category (paginated).
     */
    #[Route('/{id}/products', name: 'products', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(
        path: '/api/categories/{id}/products',
        operationId: 'getCategoryProducts',
        summary: 'Products in a category',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 12)),
            new OA\Parameter(name: 'sort', in: 'query', schema: new OA\Schema(type: 'string', enum: ['name', 'price', 'createdAt'])),
            new OA\Parameter(name: 'order', in: 'query', schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated products for the category'),
            new OA\Response(response: 404, description: 'Category not found'),
        ]
    )]
    public function products(int $id, Request $request): JsonResponse
    {
        $category = $this->categoryRepository->find($id);

        if (!$category) {
            return $this->json(['error' => 'Category not found'], Response::HTTP_NOT_FOUND);
        }

        $page  = max(1, (int) $request->query->get('page', 1));
        $limit = min(50, max(1, (int) $request->query->get('limit', 12)));

        $allowedSorts = ['name', 'price', 'createdAt'];
        $sortBy    = in_array($request->query->get('sort'), $allowedSorts, true)
            ? $request->query->get('sort')
            : 'createdAt';
        $sortOrder = strtoupper($request->query->get('order', 'desc')) === 'ASC' ? 'ASC' : 'DESC';

        $products = $this->productRepository->findWithFilters(
            categoryId: $id,
            sortBy: $sortBy,
            sortOrder: $sortOrder,
            page: $page,
            limit: $limit,
        );

        $total = $this->productRepository->countWithFilters(categoryId: $id);
        $promoMap = $this->promotionRepository->findActiveForProducts($products);

        $paginated = PaginatedResponse::create(
            data: array_map(fn($p) => ProductListItem::fromEntity($p, $promoMap[$p->getId()] ?? null), $products),
            total: $total,
            page: $page,
            limit: $limit,
        );

        return $this->json([
            'category' => CategoryItem::fromEntity($category, $total),
            'products' => $paginated,
        ]);
    }
}
