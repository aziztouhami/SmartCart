<?php

namespace App\DTO\Review;

use Symfony\Component\Validator\Constraints as Assert;

class CreateReviewRequest
{
    #[Assert\NotNull(message: 'Rating is required')]
    #[Assert\Range(min: 1, max: 100, notInRangeMessage: 'Rating must be between 1 and 100')]
    public int $rating = 0;

    #[Assert\Length(max: 2000)]
    public ?string $comment = null;
}
