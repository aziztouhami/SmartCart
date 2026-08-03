<?php

namespace App\Tests\Unit\Domain\Product;

use App\Domain\Product\SortParams;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class SortParamsTest extends TestCase
{
    public function testFallsBackToDefaultForUnknownSort(): void
    {
        $params = SortParams::fromRequest(Request::create('/api/products?sort=totallyInvalid'), ['name', 'price'], 'createdAt');

        $this->assertSame('createdAt', $params->sortBy);
    }

    public function testAcceptsWhitelistedSort(): void
    {
        $params = SortParams::fromRequest(Request::create('/api/products?sort=price'), ['name', 'price'], 'createdAt');

        $this->assertSame('price', $params->sortBy);
    }

    public function testDefaultsToDescendingOrder(): void
    {
        $params = SortParams::fromRequest(Request::create('/api/products'), ['name'], 'name');

        $this->assertSame('DESC', $params->sortOrder);
    }

    public function testNormalizesAscendingOrderCaseInsensitively(): void
    {
        $params = SortParams::fromRequest(Request::create('/api/products?order=ASC'), ['name'], 'name');

        $this->assertSame('ASC', $params->sortOrder);
    }
}
