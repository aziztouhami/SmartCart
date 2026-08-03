<?php

namespace App\DTO\Pagination;

class PaginatedResponse
{
    public array $data;
    public int $total;
    public int $page;
    public int $limit;
    public int $totalPages;

    public static function create(array $data, int $total, int $page, int $limit): self
    {
        $dto = new self();
        $dto->data = $data;
        $dto->total = $total;
        $dto->page = $page;
        $dto->limit = $limit;
        $dto->totalPages = $limit > 0 ? (int) ceil($total / $limit) : 0;

        return $dto;
    }
}
