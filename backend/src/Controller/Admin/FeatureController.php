<?php

namespace App\Controller\Admin;

use App\Service\FeatureService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/features', name: 'api_admin_features_')]
#[IsGranted('ROLE_ADMIN')]
#[OA\Tag(name: 'Admin — Features', description: 'Feature vectors for the recommendation system (ROLE_ADMIN required)')]
class FeatureController extends AbstractController
{
    public function __construct(private FeatureService $featureService)
    {
    }

    /**
     * Per-product feature vector: views, cart adds, purchases, favorites,
     * reviews, conversion rates and recency.
     */
    #[Route('/products', name: 'products', methods: ['GET'])]
    #[OA\Get(
        path: '/api/admin/features/products',
        operationId: 'adminFeatureProducts',
        summary: 'Product feature vectors',
        security: [['Bearer' => []]],
        responses: [new OA\Response(response: 200, description: 'Per-product feature vectors')]
    )]
    public function products(): JsonResponse
    {
        return $this->json($this->featureService->getProductFeatures());
    }

    /**
     * Per-category feature vector, aggregated across the category's products.
     */
    #[Route('/categories', name: 'categories', methods: ['GET'])]
    #[OA\Get(
        path: '/api/admin/features/categories',
        operationId: 'adminFeatureCategories',
        summary: 'Category feature vectors',
        security: [['Bearer' => []]],
        responses: [new OA\Response(response: 200, description: 'Per-category feature vectors')]
    )]
    public function categories(): JsonResponse
    {
        return $this->json($this->featureService->getCategoryFeatures());
    }

    /**
     * Per-brand feature vector, aggregated across the brand's products.
     */
    #[Route('/brands', name: 'brands', methods: ['GET'])]
    #[OA\Get(
        path: '/api/admin/features/brands',
        operationId: 'adminFeatureBrands',
        summary: 'Brand feature vectors',
        security: [['Bearer' => []]],
        responses: [new OA\Response(response: 200, description: 'Per-brand feature vectors')]
    )]
    public function brands(): JsonResponse
    {
        return $this->json($this->featureService->getBrandFeatures());
    }

    /**
     * Per-user feature vector: RFM-style behavior (views/cart/purchases,
     * order value, recency) plus engagement breadth across categories/brands.
     */
    #[Route('/users', name: 'users', methods: ['GET'])]
    #[OA\Get(
        path: '/api/admin/features/users',
        operationId: 'adminFeatureUsers',
        summary: 'User feature vectors',
        security: [['Bearer' => []]],
        responses: [new OA\Response(response: 200, description: 'Per-user feature vectors')]
    )]
    public function users(): JsonResponse
    {
        return $this->json($this->featureService->getUserFeatures());
    }
}
