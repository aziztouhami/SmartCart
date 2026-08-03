<?php

namespace App\Controller\Admin;

use App\Service\DashboardService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/dashboard', name: 'api_admin_dashboard_')]
#[IsGranted('ROLE_ADMIN')]
#[OA\Tag(name: 'Admin — Dashboard', description: 'KPI dashboard (ROLE_ADMIN required)')]
class AdminDashboardController extends AbstractController
{
    public function __construct(
        private DashboardService $dashboardService,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    #[OA\Get(
        path: '/api/admin/dashboard',
        operationId: 'adminDashboard',
        summary: 'Global KPI dashboard',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'lowStockThreshold', in: 'query', schema: new OA\Schema(type: 'integer', default: 5)),
        ],
        responses: [new OA\Response(response: 200, description: 'Dashboard data')]
    )]
    public function index(Request $request): JsonResponse
    {
        $threshold = max(0, (int) $request->query->get('lowStockThreshold', 5));

        return $this->json($this->dashboardService->getKpis($threshold));
    }
}
