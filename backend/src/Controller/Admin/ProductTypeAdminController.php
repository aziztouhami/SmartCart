<?php

namespace App\Controller\Admin;

use App\Domain\Http\RequestDtoParser;
use App\DTO\Product\CreateAttributeRequest;
use App\DTO\Product\CreateProductTypeRequest;
use App\DTO\Product\ProductTypeDetail;
use App\DTO\Product\SuggestAttributesRequest;
use App\DTO\Product\UpdateProductTypeRequest;
use App\Repository\ProductTypeAttributeRepository;
use App\Repository\ProductTypeRepository;
use App\Service\ProductTypeService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Product types ("Smartphone", "Smart watch", ...) and the per-type list of
 * features (e.g. Color, Battery) the admin fills in when creating a product
 * of that type. New types and new features are created on the fly here, then
 * picked up by ProductAdminController when a product is created/updated.
 */
#[Route('/api/admin/product-types', name: 'api_admin_product_types_')]
#[IsGranted('ROLE_ADMIN')]
#[OA\Tag(name: 'Admin — Product Types', description: 'Product type & feature management (ROLE_ADMIN required)')]
class ProductTypeAdminController extends AbstractController
{
    public function __construct(
        private ProductTypeRepository $productTypeRepository,
        private ProductTypeAttributeRepository $productTypeAttributeRepository,
        private ProductTypeService $productTypeService,
        private RequestDtoParser $dtoParser,
    ) {
    }

    /**
     * List all product types with their feature definitions, for the
     * "select an existing type" dropdown on the product form.
     */
    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/admin/product-types',
        operationId: 'adminListProductTypes',
        summary: 'List product types',
        security: [['Bearer' => []]],
        responses: [new OA\Response(response: 200, description: 'Product types with their features')]
    )]
    public function list(): JsonResponse
    {
        $types = $this->productTypeRepository->findAllOrdered();

        return $this->json(array_map(fn ($t) => ProductTypeDetail::fromEntity($t), $types));
    }

    /**
     * Create a new product type, optionally with its initial set of features.
     */
    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/admin/product-types',
        operationId: 'adminCreateProductType',
        summary: 'Create product type',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Smartphone'),
                    new OA\Property(
                        property: 'attributes',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'name', type: 'string', example: 'Color'),
                                new OA\Property(property: 'dataType', type: 'string', enum: ['text', 'number', 'boolean', 'select']),
                                new OA\Property(property: 'unit', type: 'string', nullable: true, example: 'mAh'),
                                new OA\Property(property: 'options', type: 'array', items: new OA\Items(type: 'string'), nullable: true),
                                new OA\Property(property: 'required', type: 'boolean'),
                            ]
                        )
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Product type created'),
            new OA\Response(response: 400, description: 'Validation error'),
            new OA\Response(response: 409, description: 'A type with this name already exists'),
        ]
    )]
    public function create(Request $request): JsonResponse
    {
        $dto = $this->dtoParser->parse($request, CreateProductTypeRequest::class);
        $type = $this->productTypeService->create($dto);

        return $this->json(ProductTypeDetail::fromEntity($type), Response::HTTP_CREATED);
    }

    /**
     * AI-suggested standard features for a product type name the admin is
     * about to create (e.g. "Casque audio" → Connectivité, Autonomie, ...).
     * Nothing is persisted — the admin reviews/edits the suggestions on the
     * frontend, then submits through the normal POST above to actually
     * create the type.
     */
    #[Route('/suggest-attributes', name: 'suggest_attributes', methods: ['POST'])]
    #[OA\Post(
        path: '/api/admin/product-types/suggest-attributes',
        operationId: 'adminSuggestProductTypeAttributes',
        summary: 'Suggest standard features for a new product type (AI, not persisted)',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(required: ['name'], properties: [
                new OA\Property(property: 'name', type: 'string', example: 'Casque audio'),
                new OA\Property(
                    property: 'existingNames',
                    type: 'array',
                    items: new OA\Items(type: 'string'),
                    description: 'Features the type already has (edit flow) — suggestions will avoid repeating these',
                ),
            ])
        ),
        responses: [
            new OA\Response(response: 200, description: 'Suggested features — review before creating the type'),
            new OA\Response(response: 400, description: 'Validation error'),
        ]
    )]
    public function suggestAttributes(Request $request): JsonResponse
    {
        $dto = $this->dtoParser->parse($request, SuggestAttributesRequest::class);

        return $this->json(['attributes' => $this->productTypeService->suggestAttributes($dto->name, $dto->existingNames)]);
    }

    /**
     * Rename an existing type.
     */
    #[Route('/{id}', name: 'update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[OA\Put(
        path: '/api/admin/product-types/{id}',
        operationId: 'adminUpdateProductType',
        summary: 'Rename a product type',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(required: ['name'], properties: [new OA\Property(property: 'name', type: 'string')])
        ),
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Product type renamed'),
            new OA\Response(response: 400, description: 'Validation error'),
            new OA\Response(response: 404, description: 'Product type not found'),
        ]
    )]
    public function update(int $id, Request $request): JsonResponse
    {
        $type = $this->productTypeRepository->find($id);
        if (!$type) {
            return $this->json(['error' => 'Product type not found'], Response::HTTP_NOT_FOUND);
        }

        $dto = $this->dtoParser->parse($request, UpdateProductTypeRequest::class);
        $this->productTypeService->rename($type, $dto);

        return $this->json(ProductTypeDetail::fromEntity($type));
    }

    /**
     * Add a new feature to an existing type (e.g. realize "Smartwatch" also needs a "Strap material" field).
     */
    #[Route('/{id}/attributes', name: 'add_attribute', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[OA\Post(
        path: '/api/admin/product-types/{id}/attributes',
        operationId: 'adminAddProductTypeAttribute',
        summary: 'Add a feature to a product type',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'dataType'],
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'dataType', type: 'string', enum: ['text', 'number', 'boolean', 'select']),
                    new OA\Property(property: 'unit', type: 'string', nullable: true),
                    new OA\Property(property: 'options', type: 'array', items: new OA\Items(type: 'string'), nullable: true),
                    new OA\Property(property: 'required', type: 'boolean'),
                ]
            )
        ),
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 201, description: 'Feature added'),
            new OA\Response(response: 400, description: 'Validation error'),
            new OA\Response(response: 404, description: 'Product type not found'),
            new OA\Response(response: 409, description: 'A feature with this name already exists on this type'),
        ]
    )]
    public function addAttribute(int $id, Request $request): JsonResponse
    {
        $type = $this->productTypeRepository->find($id);
        if (!$type) {
            return $this->json(['error' => 'Product type not found'], Response::HTTP_NOT_FOUND);
        }

        $dto = $this->dtoParser->parse($request, CreateAttributeRequest::class);
        $this->productTypeService->addAttribute($type, $dto);

        return $this->json(ProductTypeDetail::fromEntity($type), Response::HTTP_CREATED);
    }

    /**
     * Remove a feature from a type.
     */
    #[Route('/{id}/attributes/{attributeId}', name: 'remove_attribute', methods: ['DELETE'], requirements: ['id' => '\d+', 'attributeId' => '\d+'])]
    #[OA\Delete(
        path: '/api/admin/product-types/{id}/attributes/{attributeId}',
        operationId: 'adminRemoveProductTypeAttribute',
        summary: 'Remove a feature from a product type',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'attributeId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Feature removed'),
            new OA\Response(response: 404, description: 'Product type or feature not found'),
        ]
    )]
    public function removeAttribute(int $id, int $attributeId): JsonResponse
    {
        $type = $this->productTypeRepository->find($id);
        if (!$type) {
            return $this->json(['error' => 'Product type not found'], Response::HTTP_NOT_FOUND);
        }

        $attribute = $this->productTypeAttributeRepository->find($attributeId);
        if (!$attribute) {
            return $this->json(['error' => 'Feature not found'], Response::HTTP_NOT_FOUND);
        }

        $this->productTypeService->removeAttribute($type, $attribute);

        return $this->json(ProductTypeDetail::fromEntity($type));
    }

    /**
     * Delete a product type — refused while any product still uses it.
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[OA\Delete(
        path: '/api/admin/product-types/{id}',
        operationId: 'adminDeleteProductType',
        summary: 'Delete product type',
        security: [['Bearer' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Product type deleted'),
            new OA\Response(response: 404, description: 'Product type not found'),
            new OA\Response(response: 409, description: 'Type still has products assigned to it'),
        ]
    )]
    public function delete(int $id): JsonResponse
    {
        $type = $this->productTypeRepository->find($id);
        if (!$type) {
            return $this->json(['error' => 'Product type not found'], Response::HTTP_NOT_FOUND);
        }

        $this->productTypeService->delete($type);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
