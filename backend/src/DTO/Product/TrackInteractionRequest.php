<?php

namespace App\DTO\Product;

class TrackInteractionRequest
{
    public string $type = '';

    /** Optional integer value (e.g. star rating 1-5). */
    public ?int $value = null;
}
