<?php

namespace App\Controller\Product;

use App\DTO\Pagination\PaginatedResponse;
use App\DTO\Review\CreateReviewRequest;
use App\DTO\Review\ReviewItem;
use App\Entity\User;
use App\Repository\ProductRepository;
use App\Repository\ReviewRepository;
use App\Service\ReviewService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[OA\Tag(name: 'Reviews', description: 'Product reviews — read publicly, write requires authentication')]
class ReviewController extends AbstractController
{
    public function __construct(
        private ProductRepository $productRepository,
        private ReviewRepository $reviewRepository,
        private ReviewService $reviewService,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator,
    ) {}

    /**
     * List reviews for a product (public).
     */
    #[Route('/api/products/{id}/reviews', name: 'api_product_reviews_list', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(
        path: '/api/products/{id}/reviews',
        operationId: 'listProductReviews',
        summary: 'List reviews for a product',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 10)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated reviews'),
            new OA\Response(response: 404, description: 'Product not found'),
        ]
    )]
    public function list(int $id, Request $request): JsonResponse
    {
        $product = $this->productRepository->find($id);
        if (!$product) {
            return $this->json(['error' => 'Product not found'], Response::HTTP_NOT_FOUND);
        }

        $page  = max(1, (int) $request->query->get('page', 1));
        $limit = min(50, max(1, (int) $request->query->get('limit', 10)));

        $reviews = $this->reviewRepository->findByProductPaginated($product, $page, $limit);
        $total   = $this->reviewRepository->countByProduct($product);
        $avg     = $this->reviewRepository->getAverageRating($product);

        $paginated = PaginatedResponse::create(
            data: array_map(fn($r) => ReviewItem::fromEntity($r), $reviews),
            total: $total,
            page: $page,
            limit: $limit,
        );

        return $this->json([
            'averageRating' => round($avg, 1),
            'reviews' => $paginated,
        ]);
    }

    /**
     * Submit a review for a product (requires authentication).
     * One review per user per product is allowed.
     */
    #[Route('/api/products/{id}/reviews', name: 'api_product_reviews_create', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[OA\Post(
        path: '/api/products/{id}/reviews',
        operationId: 'createProductReview',
        summary: 'Submit a review',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'rating', type: 'integer', minimum: 1, maximum: 5),
                    new OA\Property(property: 'comment', type: 'string'),
                ]
            )
        ),
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 201, description: 'Review created'),
            new OA\Response(response: 400, description: 'Validation error'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 409, description: 'Already reviewed'),
        ]
    )]
    public function create(int $id, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        $product = $this->productRepository->find($id);
        if (!$product) {
            return $this->json(['error' => 'Product not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $reviewRequest = $this->serializer->deserialize($request->getContent(), CreateReviewRequest::class, 'json');
        } catch (\Exception) {
            return $this->json(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        $errors = $this->validator->validate($reviewRequest);
        if (count($errors) > 0) {
            return $this->json(['error' => (string) $errors], Response::HTTP_BAD_REQUEST);
        }

        $review = $this->reviewService->create($user, $product, $reviewRequest);

        return $this->json(ReviewItem::fromEntity($review), Response::HTTP_CREATED);
    }

    /**
     * Delete the authenticated user's own review.
     */
    #[Route('/api/reviews/{id}', name: 'api_reviews_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[OA\Delete(
        path: '/api/reviews/{id}',
        operationId: 'deleteReview',
        summary: 'Delete own review',
        security: [['Bearer' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Review deleted'),
            new OA\Response(response: 403, description: 'Forbidden — not your review'),
            new OA\Response(response: 404, description: 'Review not found'),
        ]
    )]
    public function delete(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        $review = $this->reviewRepository->find($id);
        if (!$review) {
            return $this->json(['error' => 'Review not found'], Response::HTTP_NOT_FOUND);
        }

        $this->reviewService->delete($user, $review);

        return $this->json(['message' => 'Review deleted successfully']);
    }
}
