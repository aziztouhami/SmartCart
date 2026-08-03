<?php

namespace App\Controller\Admin;

use App\Domain\Http\RequestDtoParser;
use App\DTO\Brand\BrandDetail;
use App\DTO\Brand\CreateBrandRequest;
use App\DTO\Brand\UpdateBrandRequest;
use App\Repository\BrandRepository;
use App\Service\BrandService;
use App\Service\FileUploadService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/brands', name: 'api_admin_brands_')]
#[IsGranted('ROLE_ADMIN')]
#[OA\Tag(name: 'Admin — Brands', description: 'Brand management (ROLE_ADMIN required)')]
class BrandAdminController extends AbstractController
{
    public function __construct(
        private BrandRepository $brandRepository,
        private BrandService $brandService,
        private FileUploadService $fileUploadService,
        private RequestDtoParser $dtoParser,
    ) {
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/admin/brands',
        operationId: 'adminCreateBrand',
        summary: 'Create brand',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'image', type: 'string', nullable: true),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'joinedAt', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Brand created'),
            new OA\Response(response: 400, description: 'Validation error'),
        ]
    )]
    public function create(Request $request): JsonResponse
    {
        $dto = $this->dtoParser->parse($request, CreateBrandRequest::class);
        $brand = $this->brandService->create($dto);

        $stats = $this->brandRepository->getStats($brand);

        return $this->json(BrandDetail::fromEntity($brand, $stats), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[OA\Put(
        path: '/api/admin/brands/{id}',
        operationId: 'adminUpdateBrand',
        summary: 'Update brand',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'image', type: 'string', nullable: true),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'joinedAt', type: 'string', nullable: true),
                ]
            )
        ),
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Brand updated'),
            new OA\Response(response: 404, description: 'Brand not found'),
        ]
    )]
    public function update(int $id, Request $request): JsonResponse
    {
        $brand = $this->brandRepository->find($id);
        if (!$brand) {
            return $this->json(['error' => 'Brand not found'], Response::HTTP_NOT_FOUND);
        }

        $rawData = json_decode($request->getContent(), true) ?? [];
        $dto = $this->dtoParser->parse($request, UpdateBrandRequest::class);
        $brand = $this->brandService->update($brand, $dto, $rawData);

        $stats = $this->brandRepository->getStats($brand);

        return $this->json(BrandDetail::fromEntity($brand, $stats));
    }

    #[Route('/upload-image', name: 'upload_image', methods: ['POST'])]
    #[OA\Post(
        path: '/api/admin/brands/upload-image',
        operationId: 'adminUploadBrandImage',
        summary: 'Upload a brand image',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [new OA\Property(property: 'image', type: 'string', format: 'binary')]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Image uploaded, public URL returned'),
            new OA\Response(response: 400, description: 'No file provided or validation error'),
        ]
    )]
    public function uploadImage(Request $request): JsonResponse
    {
        $file = $request->files->get('image');
        if (!$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
            return $this->json(['error' => 'No image file provided'], Response::HTTP_BAD_REQUEST);
        }

        $relativeUrl = $this->fileUploadService->upload(
            $file,
            'brands',
            ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'],
            5 * 1024 * 1024,
            'brand_',
        );

        return $this->json(['url' => $request->getSchemeAndHttpHost().$relativeUrl]);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[OA\Delete(
        path: '/api/admin/brands/{id}',
        operationId: 'adminDeleteBrand',
        summary: 'Delete brand',
        security: [['Bearer' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Brand deleted'),
            new OA\Response(response: 404, description: 'Brand not found'),
        ]
    )]
    public function delete(int $id): JsonResponse
    {
        $brand = $this->brandRepository->find($id);
        if (!$brand) {
            return $this->json(['error' => 'Brand not found'], Response::HTTP_NOT_FOUND);
        }

        $this->brandService->delete($brand);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
