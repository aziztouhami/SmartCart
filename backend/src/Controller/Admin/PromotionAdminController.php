<?php

namespace App\Controller\Admin;

use App\DTO\Pagination\PaginatedResponse;
use App\DTO\Promotion\CreatePromotionRequest;
use App\DTO\Promotion\PromotionListItem;
use App\Repository\PromotionRepository;
use App\Service\PromotionService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/admin/promotions', name: 'api_admin_promotions_')]
#[IsGranted('ROLE_ADMIN')]
#[OA\Tag(name: 'Admin — Promotions', description: 'Promotion management (ROLE_ADMIN required)')]
class PromotionAdminController extends AbstractController
{
    public function __construct(
        private PromotionRepository $promotionRepository,
        private PromotionService $promotionService,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/admin/promotions',
        operationId: 'adminListPromotions',
        summary: 'List promotions',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [new OA\Response(response: 200, description: 'Paginated promotion list')]
    )]
    public function list(Request $request): JsonResponse
    {
        $page  = max(1, (int) $request->query->get('page', 1));
        $limit = min(50, max(1, (int) $request->query->get('limit', 20)));

        $promotions = $this->promotionRepository->findAllPaginated($page, $limit);
        $total      = $this->promotionRepository->countAll();

        return $this->json(PaginatedResponse::create(
            data: array_map(fn($p) => PromotionListItem::fromEntity($p), $promotions),
            total: $total,
            page: $page,
            limit: $limit,
        ));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/admin/promotions',
        operationId: 'adminCreatePromotion',
        summary: 'Create promotion',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['type', 'discountType', 'startDate'],
                properties: [
                    new OA\Property(property: 'type', type: 'string', enum: ['product', 'brand', 'all']),
                    new OA\Property(property: 'productId', type: 'integer', nullable: true),
                    new OA\Property(property: 'brandId', type: 'integer', nullable: true),
                    new OA\Property(property: 'discountType', type: 'string', enum: ['percentage', 'fixed']),
                    new OA\Property(property: 'percentage', type: 'number', nullable: true),
                    new OA\Property(property: 'fixedPrice', type: 'number', nullable: true),
                    new OA\Property(property: 'startDate', type: 'string', format: 'date-time'),
                    new OA\Property(property: 'endDate', type: 'string', format: 'date-time', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Promotion created'),
            new OA\Response(response: 400, description: 'Validation error'),
        ]
    )]
    public function create(Request $request): JsonResponse
    {
        try {
            $dto = $this->serializer->deserialize($request->getContent(), CreatePromotionRequest::class, 'json');
        } catch (\Exception) {
            return $this->json(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json(['error' => (string) $errors], Response::HTTP_BAD_REQUEST);
        }

        $promotion = $this->promotionService->create($dto);

        return $this->json(PromotionListItem::fromEntity($promotion), Response::HTTP_CREATED);
    }

    #[Route('/{id}/end', name: 'end', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[OA\Patch(
        path: '/api/admin/promotions/{id}/end',
        operationId: 'adminEndPromotion',
        summary: 'End a promotion immediately',
        security: [['Bearer' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Promotion ended'),
            new OA\Response(response: 404, description: 'Promotion not found'),
        ]
    )]
    public function end(int $id): JsonResponse
    {
        $promotion = $this->promotionRepository->find($id);
        if (!$promotion) {
            return $this->json(['error' => 'Promotion not found'], Response::HTTP_NOT_FOUND);
        }

        $promotion = $this->promotionService->endNow($promotion);

        return $this->json(PromotionListItem::fromEntity($promotion));
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[OA\Delete(
        path: '/api/admin/promotions/{id}',
        operationId: 'adminDeletePromotion',
        summary: 'Delete a promotion',
        security: [['Bearer' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Promotion deleted'),
            new OA\Response(response: 404, description: 'Promotion not found'),
        ]
    )]
    public function delete(int $id): JsonResponse
    {
        $promotion = $this->promotionRepository->find($id);
        if (!$promotion) {
            return $this->json(['error' => 'Promotion not found'], Response::HTTP_NOT_FOUND);
        }

        $this->promotionService->delete($promotion);

        return $this->json(['message' => 'Promotion deleted successfully']);
    }
}
