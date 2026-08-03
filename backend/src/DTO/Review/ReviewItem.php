<?php

namespace App\DTO\Review;

use App\Entity\Review;

class ReviewItem
{
    public int $id;
    public int $productId;
    public int $rating;
    public ?string $comment;
    public string $authorName;
    public string $createdAt;

    public static function fromEntity(Review $review): self
    {
        $dto = new self();
        $dto->id = $review->getId();
        $dto->productId = $review->getProduct()->getId();
        $dto->rating = $review->getRating();
        $dto->comment = $review->getComment();
        $dto->authorName = trim(
            ($review->getUser()->getFirstName() ?? '').' '.($review->getUser()->getLastName() ?? '')
        );
        $dto->createdAt = $review->getCreatedAt()->format(\DateTimeInterface::ATOM);

        return $dto;
    }
}
