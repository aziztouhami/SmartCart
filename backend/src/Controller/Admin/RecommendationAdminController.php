<?php

namespace App\Controller\Admin;

use App\Repository\ProductRelationRepository;
use App\Repository\UserRecommendationRepository;
use App\Service\Recommendation\ColdStartRecommendationService;
use App\Service\Recommendation\RecommendationBuilderService;
use App\Service\Recommendation\UserRecommendationBuilderService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Manual trigger for the recommendation batch job — the same work
 * app:rebuild-recommendations does on a schedule, exposed here so an admin
 * (or this dev environment, which has no cron) can refresh on demand.
 */
#[Route('/api/admin/recommendations', name: 'api_admin_recommendations_')]
#[IsGranted('ROLE_ADMIN')]
#[OA\Tag(name: 'Admin — Recommendations', description: 'Recommendation batch jobs (ROLE_ADMIN required)')]
class RecommendationAdminController extends AbstractController
{
    public function __construct(
        private RecommendationBuilderService $itemRelationBuilder,
        private UserRecommendationBuilderService $userRecommendationBuilder,
        private ColdStartRecommendationService $coldStartRecommendationBuilder,
        private ProductRelationRepository $productRelationRepository,
        private UserRecommendationRepository $userRecommendationRepository,
    ) {
    }

    #[Route('/rebuild', name: 'rebuild', methods: ['POST'])]
    #[OA\Post(
        path: '/api/admin/recommendations/rebuild',
        operationId: 'adminRebuildRecommendations',
        summary: 'Recompute cold-start, guest item relations, and logged-in hybrid recommendations now',
        security: [['Bearer' => []]],
        responses: [new OA\Response(response: 200, description: 'Rebuild stats')]
    )]
    public function rebuild(): JsonResponse
    {
        return $this->json([
            'coldStart' => $this->coldStartRecommendationBuilder->rebuild(),
            'itemRelations' => $this->itemRelationBuilder->rebuild(),
            'userRecommendations' => $this->userRecommendationBuilder->rebuild(),
        ]);
    }

    #[Route('/status', name: 'status', methods: ['GET'])]
    #[OA\Get(
        path: '/api/admin/recommendations/status',
        operationId: 'adminRecommendationStatus',
        summary: 'Current recommendation table sizes',
        security: [['Bearer' => []]],
        responses: [new OA\Response(response: 200, description: 'Status')]
    )]
    public function status(): JsonResponse
    {
        return $this->json([
            'relationRows' => $this->productRelationRepository->countAll(),
            'userRecommendationRows' => $this->userRecommendationRepository->countAll(),
        ]);
    }
}
