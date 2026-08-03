<?php

namespace App\Tests\Unit\Domain\Product;

use App\Domain\Product\ProductQueryParams;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class ProductQueryParamsTest extends TestCase
{
    public function testAllFieldsNullWhenNotProvided(): void
    {
        $params = ProductQueryParams::fromRequest(Request::create('/api/products'));

        $this->assertNull($params->search);
        $this->assertNull($params->category);
        $this->assertNull($params->brand);
        $this->assertNull($params->type);
        $this->assertNull($params->minPrice);
        $this->assertNull($params->maxPrice);
        $this->assertNull($params->inStock);
        $this->assertNull($params->attributes);
    }

    public function testParsesScalarFilters(): void
    {
        $params = ProductQueryParams::fromRequest(Request::create(
            '/api/products?q=phone&category=3&brand=5&type=2&minPrice=10&maxPrice=100&inStock=1'
        ));

        $this->assertSame('phone', $params->search);
        $this->assertSame(3, $params->category);
        $this->assertSame(5, $params->brand);
        $this->assertSame(2, $params->type);
        $this->assertSame(10.0, $params->minPrice);
        $this->assertSame(100.0, $params->maxPrice);
        $this->assertTrue($params->inStock);
    }

    public function testParsesAttrFilterBag(): void
    {
        $params = ProductQueryParams::fromRequest(Request::create('/api/products?attr[color]=Black&attr[ram]=8GB'));

        $this->assertSame(['color' => 'Black', 'ram' => '8GB'], $params->attributes);
    }

    public function testDropsEmptyAttrValues(): void
    {
        $params = ProductQueryParams::fromRequest(Request::create('/api/products?attr[color]=Black&attr[size]='));

        $this->assertSame(['color' => 'Black'], $params->attributes);
    }
}
