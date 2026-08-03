<?php

namespace App\Controller\Admin;

use App\Service\Analytics\AnomalyAnalysisService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The "Analyze" button in the admin panel — one endpoint per entity type,
 * each delegating straight to AnomalyAnalysisService. Errors (entity not
 * found, AI unavailable) are thrown as \RuntimeException from the service
 * and rendered by the global RuntimeExceptionListener, same convention as
 * the rest of the admin controllers.
 */
#[Route('/api/admin/analytics', name: 'api_admin_analytics_')]
#[IsGranted('ROLE_ADMIN')]
#[OA\Tag(name: 'Admin — Analytics', description: 'Local AI (Ollama) anomaly analysis (ROLE_ADMIN required)')]
class AnalyticsAdminController extends AbstractController
{
    public function __construct(
        private AnomalyAnalysisService $anomalyAnalysisService,
    ) {
    }

    #[Route('/products/{id}/analyze', name: 'analyze_product', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[OA\Post(
        path: '/api/admin/analytics/products/{id}/analyze',
        operationId: 'analyzeProduct',
        summary: 'AI anomaly analysis for one product',
        security: [['Bearer' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Analysis result'),
            new OA\Response(response: 404, description: 'Product not found'),
            new OA\Response(response: 503, description: 'AI analytics unavailable (no model configured or Ollama unreachable)'),
        ]
    )]
    public function analyzeProduct(int $id): JsonResponse
    {
        return $this->json($this->anomalyAnalysisService->analyzeProduct($id));
    }

    #[Route('/categories/{id}/analyze', name: 'analyze_category', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[OA\Post(
        path: '/api/admin/analytics/categories/{id}/analyze',
        operationId: 'analyzeCategory',
        summary: 'AI anomaly analysis for one category',
        security: [['Bearer' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Analysis result'),
            new OA\Response(response: 404, description: 'Category not found'),
            new OA\Response(response: 503, description: 'AI analytics unavailable (no model configured or Ollama unreachable)'),
        ]
    )]
    public function analyzeCategory(int $id): JsonResponse
    {
        return $this->json($this->anomalyAnalysisService->analyzeCategory($id));
    }

    #[Route('/brands/{id}/analyze', name: 'analyze_brand', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[OA\Post(
        path: '/api/admin/analytics/brands/{id}/analyze',
        operationId: 'analyzeBrand',
        summary: 'AI anomaly analysis for one brand',
        security: [['Bearer' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Analysis result'),
            new OA\Response(response: 404, description: 'Brand not found'),
            new OA\Response(response: 503, description: 'AI analytics unavailable (no model configured or Ollama unreachable)'),
        ]
    )]
    public function analyzeBrand(int $id): JsonResponse
    {
        return $this->json($this->anomalyAnalysisService->analyzeBrand($id));
    }

    #[Route('/product-types/{id}/analyze', name: 'analyze_product_type', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[OA\Post(
        path: '/api/admin/analytics/product-types/{id}/analyze',
        operationId: 'analyzeProductType',
        summary: 'AI anomaly analysis for one product type',
        security: [['Bearer' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Analysis result'),
            new OA\Response(response: 404, description: 'Product type not found'),
            new OA\Response(response: 503, description: 'AI analytics unavailable (no model configured or Ollama unreachable)'),
        ]
    )]
    public function analyzeProductType(int $id): JsonResponse
    {
        return $this->json($this->anomalyAnalysisService->analyzeProductType($id));
    }
}
