<?php

namespace App\Domain\Http;

use Symfony\Component\HttpFoundation\Request;

/**
 * Clamped page/limit pair parsed from a request's query string.
 */
final class Pagination
{
    public function __construct(
        public readonly int $page,
        public readonly int $limit,
    ) {
    }

    public static function fromRequest(Request $request, int $defaultLimit = 12, int $maxLimit = 50): self
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min($maxLimit, max(1, (int) $request->query->get('limit', $defaultLimit)));

        return new self($page, $limit);
    }
}
