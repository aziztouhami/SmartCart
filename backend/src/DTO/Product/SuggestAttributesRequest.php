<?php

namespace App\DTO\Product;

class SuggestAttributesRequest
{
    public string $name = '';

    /**
     * Names of features the type already has (edit flow) — the AI is asked
     * to suggest new ones on top of these instead of repeating them.
     *
     * @var string[]
     */
    public array $existingNames = [];
}
