<?php

namespace App\Tests\Unit\Service;

use App\DTO\Review\CreateReviewRequest;
use App\Entity\Product;
use App\Entity\Review;
use App\Entity\User;
use App\Repository\OrderRepository;
use App\Repository\ReviewRepository;
use App\Service\InteractionService;
use App\Service\ReviewService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ReviewServiceTest extends TestCase
{
    private ReviewRepository $reviewRepository;
    private OrderRepository $orderRepository;
    private EntityManagerInterface $em;
    private InteractionService $interactionService;
    private ReviewService $service;

    protected function setUp(): void
    {
        $this->reviewRepository = $this->createMock(ReviewRepository::class);
        $this->orderRepository = $this->createMock(OrderRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->interactionService = $this->createMock(InteractionService::class);

        $this->service = new ReviewService(
            $this->reviewRepository,
            $this->orderRepository,
            $this->em,
            $this->interactionService,
        );
    }

    private function withId(object $entity, int $id): object
    {
        $ref = new \ReflectionProperty($entity, 'id');
        $ref->setAccessible(true);
        $ref->setValue($entity, $id);

        return $entity;
    }

    public function testCreateThrowsWhenNoDeliveredOrderForProduct(): void
    {
        $this->orderRepository->method('hasUserDeliveredOrderWithProduct')->willReturn(false);

        $dto = new CreateReviewRequest();
        $dto->rating = 5;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('You can only review products from delivered orders');

        $this->service->create(new User(), new Product(), $dto);
    }

    public function testCreateThrowsWhenAlreadyReviewed(): void
    {
        $this->orderRepository->method('hasUserDeliveredOrderWithProduct')->willReturn(true);
        $this->reviewRepository->method('findByProductAndUser')->willReturn(new Review());

        $dto = new CreateReviewRequest();
        $dto->rating = 5;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('You have already reviewed this product');

        $this->service->create(new User(), new Product(), $dto);
    }

    public function testCreateSucceedsAndTracksRatingInteraction(): void
    {
        $this->orderRepository->method('hasUserDeliveredOrderWithProduct')->willReturn(true);
        $this->reviewRepository->method('findByProductAndUser')->willReturn(null);

        $user = new User();
        $product = new Product();
        $dto = new CreateReviewRequest();
        $dto->rating = 5;
        $dto->comment = 'Great product';

        $this->em->expects($this->once())->method('persist')->with($this->isInstanceOf(Review::class));
        $this->em->expects($this->once())->method('flush');
        $this->interactionService->expects($this->once())->method('track')->with($user, $product, 'rating', 5);

        $review = $this->service->create($user, $product, $dto);

        $this->assertSame(5, $review->getRating());
        $this->assertSame('Great product', $review->getComment());
    }

    public function testDeleteThrowsWhenReviewBelongsToAnotherUser(): void
    {
        $owner = $this->withId(new User(), 1);
        $review = new Review();
        $review->setUser($owner);

        $requester = $this->withId(new User(), 2);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('You can only delete your own reviews');

        $this->service->delete($requester, $review);
    }

    public function testDeleteSucceedsForOwnReview(): void
    {
        $user = $this->withId(new User(), 1);
        $review = new Review();
        $review->setUser($user);

        $this->em->expects($this->once())->method('remove')->with($review);
        $this->em->expects($this->once())->method('flush');

        $this->service->delete($user, $review);
    }
}
