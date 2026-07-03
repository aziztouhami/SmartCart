<?php

namespace App\Controller\Brand;

use App\DTO\Brand\BrandDetail;
use App\DTO\Brand\BrandListItem;
use App\DTO\Pagination\PaginatedResponse;
use App\Repository\BrandRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/brands', name: 'api_brands_')]
#[OA\Tag(name: 'Brands', description: 'Brand catalogue — listing and detail')]
class BrandController extends AbstractController
{
    public function __construct(
        private BrandRepository $brandRepository,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/brands',
        operationId: 'listBrands',
        summary: 'List brands',
        description: 'Paginated brand list with stats.',
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated brand list'),
        ]
    )]
    public function list(Request $request): JsonResponse
    {
        $page  = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));

        $brands = $this->brandRepository->findAllPaginated($page, $limit);
        $total  = $this->brandRepository->countAll();

        $paginated = PaginatedResponse::create(
            data: array_map(
                fn($b) => BrandListItem::fromEntity($b, $this->brandRepository->getStats($b)),
                $brands
            ),
            total: $total,
            page: $page,
            limit: $limit,
        );

        return $this->json($paginated);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(
        path: '/api/brands/{id}',
        operationId: 'getBrand',
        summary: 'Get brand detail',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Brand detail'),
            new OA\Response(response: 404, description: 'Brand not found'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $brand = $this->brandRepository->find($id);
        if (!$brand) {
            return $this->json(['error' => 'Brand not found'], Response::HTTP_NOT_FOUND);
        }

        $stats = $this->brandRepository->getStats($brand);

        return $this->json(BrandDetail::fromEntity($brand, $stats));
    }
}
