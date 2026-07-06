<?php

namespace App\Controller\Admin;

use App\DTO\Category\CategoryTree;
use App\DTO\Category\CreateCategoryRequest;
use App\DTO\Category\UpdateCategoryRequest;
use App\Repository\CategoryRepository;
use App\Service\CategoryService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/admin/categories', name: 'api_admin_categories_')]
#[IsGranted('ROLE_ADMIN')]
#[OA\Tag(name: 'Admin — Categories', description: 'Category management (ROLE_ADMIN required)')]
class CategoryAdminController extends AbstractController
{
    public function __construct(
        private CategoryRepository $categoryRepository,
        private CategoryService $categoryService,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator,
    ) {}

    /**
     * Create a new category (optionally under a parent).
     */
    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/admin/categories',
        operationId: 'adminCreateCategory',
        summary: 'Create category',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'parentId', type: 'integer', nullable: true),
                    new OA\Property(property: 'image', type: 'string', nullable: true),
                    new OA\Property(property: 'seasonalMonths', type: 'array', items: new OA\Items(type: 'integer', minimum: 1, maximum: 12), nullable: true, description: 'Calendar months (1-12) this category should get a recommendation boost in'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Category created'),
            new OA\Response(response: 400, description: 'Validation error'),
            new OA\Response(response: 404, description: 'Parent category not found'),
        ]
    )]
    public function create(Request $request): JsonResponse
    {
        try {
            $dto = $this->serializer->deserialize($request->getContent(), CreateCategoryRequest::class, 'json');
        } catch (\Exception) {
            return $this->json(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json(['error' => (string) $errors], Response::HTTP_BAD_REQUEST);
        }

        $category = $this->categoryService->create($dto);

        return $this->json(CategoryTree::fromEntity($category), Response::HTTP_CREATED);
    }

    /**
     * Update a category (partial update).
     */
    #[Route('/{id}', name: 'update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[OA\Put(
        path: '/api/admin/categories/{id}',
        operationId: 'adminUpdateCategory',
        summary: 'Update category',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'parentId', type: 'integer', nullable: true),
                    new OA\Property(property: 'image', type: 'string', nullable: true),
                    new OA\Property(property: 'seasonalMonths', type: 'array', items: new OA\Items(type: 'integer', minimum: 1, maximum: 12), nullable: true, description: 'Calendar months (1-12) this category should get a recommendation boost in'),
                ]
            )
        ),
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Category updated'),
            new OA\Response(response: 404, description: 'Category or parent not found'),
        ]
    )]
    public function update(int $id, Request $request): JsonResponse
    {
        $category = $this->categoryRepository->find($id);
        if (!$category) {
            return $this->json(['error' => 'Category not found'], Response::HTTP_NOT_FOUND);
        }

        $rawData = json_decode($request->getContent(), true) ?? [];

        try {
            $dto = $this->serializer->deserialize($request->getContent(), UpdateCategoryRequest::class, 'json');
        } catch (\Exception) {
            return $this->json(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json(['error' => (string) $errors], Response::HTTP_BAD_REQUEST);
        }

        $category = $this->categoryService->update($category, $dto, $rawData);

        return $this->json(CategoryTree::fromEntity($category));
    }

    /**
     * Delete a category (cascades to its products and subcategories per entity config).
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[OA\Delete(
        path: '/api/admin/categories/{id}',
        operationId: 'adminDeleteCategory',
        summary: 'Delete category',
        security: [['Bearer' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Category deleted'),
            new OA\Response(response: 404, description: 'Category not found'),
        ]
    )]
    public function delete(int $id): JsonResponse
    {
        $category = $this->categoryRepository->find($id);
        if (!$category) {
            return $this->json(['error' => 'Category not found'], Response::HTTP_NOT_FOUND);
        }

        $this->categoryService->delete($category);

        return $this->json(['message' => 'Category deleted successfully']);
    }
}
