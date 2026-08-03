<?php

namespace App\Domain\Product;

use Symfony\Component\HttpFoundation\Request;

/**
 * A whitelisted sort field + normalized ASC/DESC direction parsed from a
 * request's `sort`/`order` query params.
 */
final class SortParams
{
    public function __construct(
        public readonly string $sortBy,
        public readonly string $sortOrder,
    ) {
    }

    /**
     * @param string[] $allowedFields
     */
    public static function fromRequest(Request $request, array $allowedFields, string $default): self
    {
        $requested = $request->query->get('sort');
        $sortBy = in_array($requested, $allowedFields, true) ? $requested : $default;
        $sortOrder = 'ASC' === strtoupper((string) $request->query->get('order', 'desc')) ? 'ASC' : 'DESC';

        return new self($sortBy, $sortOrder);
    }
}
