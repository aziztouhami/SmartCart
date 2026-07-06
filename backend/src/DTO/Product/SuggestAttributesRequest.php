<?php

namespace App\DTO\Product;

use Symfony\Component\Validator\Constraints as Assert;

class SuggestAttributesRequest
{
    #[Assert\NotBlank(message: 'Type name is required')]
    #[Assert\Length(max: 255)]
    public string $name = '';

    /**
     * Names of features the type already has (edit flow) — the AI is asked
     * to suggest new ones on top of these instead of repeating them.
     *
     * @var string[]
     */
    public array $existingNames = [];
}
