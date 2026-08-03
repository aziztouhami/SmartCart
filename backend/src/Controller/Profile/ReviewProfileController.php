<?php

namespace App\Controller\Profile;

use App\DTO\Review\ReviewItem;
use App\Entity\User;
use App\Repository\ReviewRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Profile', description: 'Own reviews — requires authentication')]
class ReviewProfileController extends AbstractController
{
    public function __construct(
        private ReviewRepository $reviewRepository,
    ) {
    }

    #[Route('/api/profile/reviews', name: 'api_profile_reviews_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/profile/reviews',
        operationId: 'listOwnReviews',
        summary: "List the current user's own reviews",
        security: [['Bearer' => []]],
        responses: [new OA\Response(response: 200, description: 'Review list')]
    )]
    public function list(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Authentication required'], 401);
        }

        $reviews = $this->reviewRepository->findByUser($user, 200);

        return $this->json(array_map([ReviewItem::class, 'fromEntity'], $reviews));
    }
}
