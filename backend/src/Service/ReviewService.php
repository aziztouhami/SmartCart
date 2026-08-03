<?php

namespace App\Service;

use App\DTO\Review\CreateReviewRequest;
use App\Entity\Product;
use App\Entity\Review;
use App\Entity\User;
use App\Repository\OrderRepository;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;

class ReviewService
{
    public function __construct(
        private ReviewRepository $reviewRepository,
        private OrderRepository $orderRepository,
        private EntityManagerInterface $em,
        private InteractionService $interactionService,
    ) {
    }

    public function create(User $user, Product $product, CreateReviewRequest $dto): Review
    {
        if (!$this->orderRepository->hasUserDeliveredOrderWithProduct($user, $product)) {
            throw new \RuntimeException('You can only review products from delivered orders', 403);
        }

        if ($this->reviewRepository->findByProductAndUser($product, $user)) {
            throw new \RuntimeException('You have already reviewed this product', 409);
        }

        $review = new Review();
        $review->setProduct($product);
        $review->setUser($user);
        $review->setRating($dto->rating);
        $review->setComment($dto->comment);

        $this->em->persist($review);
        $this->em->flush();

        $this->interactionService->track($user, $product, 'rating', $dto->rating);

        return $review;
    }

    public function delete(User $user, Review $review): void
    {
        if ($review->getUser()->getId() !== $user->getId()) {
            throw new \RuntimeException('You can only delete your own reviews', 403);
        }

        $this->em->remove($review);
        $this->em->flush();
    }
}
