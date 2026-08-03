<?php

namespace App\Tests\Unit\Domain\Http;

use App\Domain\Http\Pagination;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class PaginationTest extends TestCase
{
    public function testDefaultsWhenNotProvided(): void
    {
        $pagination = Pagination::fromRequest(Request::create('/api/products'));

        $this->assertSame(1, $pagination->page);
        $this->assertSame(12, $pagination->limit);
    }

    public function testUsesProvidedValues(): void
    {
        $pagination = Pagination::fromRequest(Request::create('/api/products?page=3&limit=5'));

        $this->assertSame(3, $pagination->page);
        $this->assertSame(5, $pagination->limit);
    }

    public function testClampsPageBelowOne(): void
    {
        $pagination = Pagination::fromRequest(Request::create('/api/products?page=0'));

        $this->assertSame(1, $pagination->page);
    }

    public function testClampsLimitToMax(): void
    {
        $pagination = Pagination::fromRequest(Request::create('/api/products?limit=999'), maxLimit: 50);

        $this->assertSame(50, $pagination->limit);
    }
}
