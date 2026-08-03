<?php

namespace App\Controller\Admin;

use App\Domain\Http\RequestDtoParser;
use App\DTO\Product\CreateProductRequest;
use App\DTO\Product\ProductDetail;
use App\DTO\Product\UpdateProductRequest;
use App\DTO\Product\UpdateStockRequest;
use App\Repository\ProductRepository;
use App\Repository\ReviewRepository;
use App\Service\ProductService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/products', name: 'api_admin_products_')]
#[IsGranted('ROLE_ADMIN')]
#[OA\Tag(name: 'Admin — Products', description: 'Product management (ROLE_ADMIN required)')]
class ProductAdminController extends AbstractController
{
    public function __construct(
        private ProductRepository $productRepository,
        private ReviewRepository $reviewRepository,
        private ProductService $productService,
        private RequestDtoParser $dtoParser,
    ) {
    }

    /**
     * Create a new product.
     */
    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/admin/products',
        operationId: 'adminCreateProduct',
        summary: 'Create product',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'price', 'stock', 'categoryId'],
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'price', type: 'number'),
                    new OA\Property(property: 'stock', type: 'integer'),
                    new OA\Property(property: 'categoryId', type: 'integer'),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'images', type: 'array', items: new OA\Items(type: 'string')),
                    new OA\Property(property: 'brandId', type: 'integer', nullable: true),
                    new OA\Property(property: 'productTypeId', type: 'integer', nullable: true),
                    new OA\Property(property: 'attributes', type: 'object', description: 'Feature values keyed by the type\'s attribute slug', additionalProperties: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Product created'),
            new OA\Response(response: 400, description: 'Validation error'),
            new OA\Response(response: 404, description: 'Category not found'),
        ]
    )]
    public function create(Request $request): JsonResponse
    {
        $dto = $this->dtoParser->parse($request, CreateProductRequest::class);
        $product = $this->productService->create($dto);

        return $this->json(
            ProductDetail::fromEntity($product, 0.0, 0),
            Response::HTTP_CREATED
        );
    }

    /**
     * Update an existing product (partial update — only supplied fields are changed).
     */
    #[Route('/{id}', name: 'update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[OA\Put(
        path: '/api/admin/products/{id}',
        operationId: 'adminUpdateProduct',
        summary: 'Update product',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'price', type: 'number'),
                    new OA\Property(property: 'stock', type: 'integer'),
                    new OA\Property(property: 'categoryId', type: 'integer'),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'images', type: 'array', items: new OA\Items(type: 'string')),
                    new OA\Property(property: 'brandId', type: 'integer', nullable: true),
                    new OA\Property(property: 'productTypeId', type: 'integer', nullable: true),
                    new OA\Property(property: 'attributes', type: 'object', description: 'Feature values keyed by the type\'s attribute slug', additionalProperties: true),
                ]
            )
        ),
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Product updated'),
            new OA\Response(response: 400, description: 'Validation error'),
            new OA\Response(response: 404, description: 'Product or category not found'),
        ]
    )]
    public function update(int $id, Request $request): JsonResponse
    {
        $product = $this->productRepository->find($id);
        if (!$product) {
            return $this->json(['error' => 'Product not found'], Response::HTTP_NOT_FOUND);
        }

        $dto = $this->dtoParser->parse($request, UpdateProductRequest::class);
        $product = $this->productService->update($product, $dto);

        $avgRating = $this->reviewRepository->getAverageRating($product);
        $reviewCount = $this->reviewRepository->countByProduct($product);

        return $this->json(ProductDetail::fromEntity($product, $avgRating, $reviewCount));
    }

    /**
     * Adjust product stock — supply either "quantity" (absolute) or "adjustment" (relative delta).
     */
    #[Route('/{id}/stock', name: 'update_stock', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[OA\Patch(
        path: '/api/admin/products/{id}/stock',
        operationId: 'adminUpdateStock',
        summary: 'Update product stock',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'quantity', type: 'integer', description: 'Set absolute stock value'),
                    new OA\Property(property: 'adjustment', type: 'integer', description: 'Relative change (+/-)'),
                ]
            )
        ),
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Stock updated'),
            new OA\Response(response: 400, description: 'Invalid payload or negative result'),
            new OA\Response(response: 404, description: 'Product not found'),
        ]
    )]
    public function updateStock(int $id, Request $request): JsonResponse
    {
        $product = $this->productRepository->find($id);
        if (!$product) {
            return $this->json(['error' => 'Product not found'], Response::HTTP_NOT_FOUND);
        }

        $dto = $this->dtoParser->parse($request, UpdateStockRequest::class);
        $product = $this->productService->updateStock($product, $dto);

        return $this->json(['id' => $product->getId(), 'stock' => $product->getStock()]);
    }

    /**
     * Delete a product.
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[OA\Delete(
        path: '/api/admin/products/{id}',
        operationId: 'adminDeleteProduct',
        summary: 'Delete product',
        security: [['Bearer' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Product deleted'),
            new OA\Response(response: 404, description: 'Product not found'),
        ]
    )]
    public function delete(int $id): JsonResponse
    {
        $product = $this->productRepository->find($id);
        if (!$product) {
            return $this->json(['error' => 'Product not found'], Response::HTTP_NOT_FOUND);
        }

        $this->productService->delete($product);

        return $this->json(['message' => 'Product deleted successfully']);
    }
}
