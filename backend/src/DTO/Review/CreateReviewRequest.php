<?php

namespace App\DTO\Review;

class CreateReviewRequest
{
    public int $rating = 0;

    public ?string $comment = null;
}
