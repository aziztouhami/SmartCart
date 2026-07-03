<?php

namespace App\Tests\Unit\Service;

use App\DTO\Product\CreateAttributeRequest;
use App\DTO\Product\CreateProductTypeRequest;
use App\DTO\Product\UpdateProductTypeRequest;
use App\Entity\Product;
use App\Entity\ProductType;
use App\Entity\ProductTypeAttribute;
use App\Service\ProductTypeService;
use App\Service\SlugService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ProductTypeServiceTest extends TestCase
{
    private EntityManagerInterface $em;
    private SlugService $slugService;
    private ProductTypeService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->slugService = $this->createMock(SlugService::class);
        $this->slugService->method('generateProductTypeSlug')->willReturn('smartwatch');
        $this->slugService->method('slugify')->willReturnCallback(
            fn (string $text) => strtolower(str_replace(' ', '-', $text))
        );

        $this->service = new ProductTypeService($this->em, $this->slugService);
    }

    public function testCreateBuildsTypeWithAttributes(): void
    {
        $dto = new CreateProductTypeRequest();
        $dto->name = 'Smartwatch';
        $dto->attributes = [
            ['name' => 'Color', 'dataType' => 'text'],
            ['name' => 'Battery', 'dataType' => 'number', 'unit' => 'mAh'],
        ];

        $type = $this->service->create($dto);

        $this->assertSame('Smartwatch', $type->getName());
        $this->assertSame('smartwatch', $type->getSlug());
        $this->assertCount(2, $type->getAttributes());
    }

    public function testCreateThrowsWhenAttributeNameBlank(): void
    {
        $dto = new CreateProductTypeRequest();
        $dto->name = 'Smartwatch';
        $dto->attributes = [['name' => '', 'dataType' => 'text']];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Each feature needs a name');

        $this->service->create($dto);
    }

    public function testCreateThrowsWhenDuplicateAttributeSlug(): void
    {
        $dto = new CreateProductTypeRequest();
        $dto->name = 'Smartwatch';
        $dto->attributes = [
            ['name' => 'Color', 'dataType' => 'text'],
            ['name' => 'Color', 'dataType' => 'text'],
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('This type already has a feature named "Color"');

        $this->service->create($dto);
    }

    public function testCreateThrowsWhenSelectHasNoOptions(): void
    {
        $dto = new CreateProductTypeRequest();
        $dto->name = 'Smartwatch';
        $dto->attributes = [['name' => 'Strap', 'dataType' => 'select']];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Feature "Strap" is a select but has no options');

        $this->service->create($dto);
    }

    public function testCreateThrowsOnInvalidDataType(): void
    {
        $dto = new CreateProductTypeRequest();
        $dto->name = 'Smartwatch';
        $dto->attributes = [['name' => 'Color', 'dataType' => 'bogus']];

        $this->expectException(\RuntimeException::class);

        $this->service->create($dto);
    }

    public function testAddAttributeAttachesToExistingType(): void
    {
        $type = new ProductType();
        $type->setName('Smartwatch');
        $type->setSlug('smartwatch');

        $dto = new CreateAttributeRequest();
        $dto->name = 'Strap Material';
        $dto->dataType = 'text';

        $attribute = $this->service->addAttribute($type, $dto);

        $this->assertSame('Strap Material', $attribute->getName());
        $this->assertSame('strap-material', $attribute->getSlug());
        $this->assertCount(1, $type->getAttributes());
    }

    public function testAddAttributeRejectsDuplicateName(): void
    {
        $type = new ProductType();
        $type->setName('Smartwatch');
        $type->setSlug('smartwatch');
        $existing = new ProductTypeAttribute();
        $existing->setName('Color');
        $existing->setSlug('color');
        $existing->setDataType('text');
        $type->addAttribute($existing);

        $dto = new CreateAttributeRequest();
        $dto->name = 'Color';
        $dto->dataType = 'text';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('This type already has a feature named "Color"');

        $this->service->addAttribute($type, $dto);
    }

    public function testRenameUpdatesNameOnly(): void
    {
        $type = new ProductType();
        $type->setName('Old Name');
        $type->setSlug('old-slug');

        $dto = new UpdateProductTypeRequest();
        $dto->name = 'New Name';

        $result = $this->service->rename($type, $dto);

        $this->assertSame('New Name', $result->getName());
        $this->assertSame('old-slug', $result->getSlug());
    }

    private function withId(ProductType $type, int $id): ProductType
    {
        $ref = new \ReflectionProperty(ProductType::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($type, $id);

        return $type;
    }

    public function testRemoveAttributeThrowsWhenAttributeBelongsToAnotherType(): void
    {
        $type = $this->withId(new ProductType(), 1);
        $type->setName('Smartwatch');
        $type->setSlug('smartwatch');

        $otherType = $this->withId(new ProductType(), 2);
        $otherType->setName('Phone');
        $otherType->setSlug('phone');

        $attribute = new ProductTypeAttribute();
        $attribute->setName('Color');
        $attribute->setSlug('color');
        $attribute->setDataType('text');
        $otherType->addAttribute($attribute);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('This feature does not belong to this product type');

        $this->service->removeAttribute($type, $attribute);
    }

    public function testRemoveAttributeSucceedsForOwnAttribute(): void
    {
        $type = new ProductType();
        $type->setName('Smartwatch');
        $type->setSlug('smartwatch');
        $attribute = new ProductTypeAttribute();
        $attribute->setName('Color');
        $attribute->setSlug('color');
        $attribute->setDataType('text');
        $type->addAttribute($attribute);

        $this->em->expects($this->once())->method('remove')->with($attribute);

        $this->service->removeAttribute($type, $attribute);

        $this->assertCount(0, $type->getAttributes());
    }

    public function testDeleteThrowsWhenTypeHasProducts(): void
    {
        $type = new ProductType();
        $type->setName('Smartwatch');
        $type->setSlug('smartwatch');

        $product = new Product();
        $type->getProducts()->add($product);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot delete a type that still has products assigned to it');

        $this->service->delete($type);
    }

    public function testDeleteSucceedsWhenNoProductsAssigned(): void
    {
        $type = new ProductType();
        $type->setName('Smartwatch');
        $type->setSlug('smartwatch');

        $this->em->expects($this->once())->method('remove')->with($type);

        $this->service->delete($type);
    }

    public function testResolveAttributeValuesDropsUnknownKeysAndAppliesDefaults(): void
    {
        $type = new ProductType();
        $type->setName('Smartwatch');
        $type->setSlug('smartwatch');
        $color = new ProductTypeAttribute();
        $color->setName('Color');
        $color->setSlug('color');
        $color->setDataType('text');
        $type->addAttribute($color);

        $resolved = $this->service->resolveAttributeValues($type, ['color' => 'Black', 'unknown' => 'x']);

        $this->assertSame(['color' => 'Black'], $resolved);
    }

    public function testResolveAttributeValuesThrowsWhenRequiredMissing(): void
    {
        $type = new ProductType();
        $type->setName('Smartwatch');
        $type->setSlug('smartwatch');
        $battery = new ProductTypeAttribute();
        $battery->setName('Battery');
        $battery->setSlug('battery');
        $battery->setDataType('number');
        $battery->setRequired(true);
        $type->addAttribute($battery);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Feature "Battery" is required for this product type');

        $this->service->resolveAttributeValues($type, []);
    }

    public function testResolveAttributeValuesCoercesNumberAndRejectsNonNumeric(): void
    {
        $type = new ProductType();
        $type->setName('Smartwatch');
        $type->setSlug('smartwatch');
        $battery = new ProductTypeAttribute();
        $battery->setName('Battery');
        $battery->setSlug('battery');
        $battery->setDataType('number');
        $type->addAttribute($battery);

        $resolved = $this->service->resolveAttributeValues($type, ['battery' => '500']);
        $this->assertSame(['battery' => 500.0], $resolved);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Feature "Battery" must be a number');
        $this->service->resolveAttributeValues($type, ['battery' => 'not-a-number']);
    }

    public function testResolveAttributeValuesValidatesSelectOptions(): void
    {
        $type = new ProductType();
        $type->setName('Smartwatch');
        $type->setSlug('smartwatch');
        $strap = new ProductTypeAttribute();
        $strap->setName('Strap');
        $strap->setSlug('strap');
        $strap->setDataType('select');
        $strap->setOptions(['Leather', 'Silicone']);
        $type->addAttribute($strap);

        $resolved = $this->service->resolveAttributeValues($type, ['strap' => 'Leather']);
        $this->assertSame(['strap' => 'Leather'], $resolved);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Feature "Strap" must be one of: Leather, Silicone');
        $this->service->resolveAttributeValues($type, ['strap' => 'Metal']);
    }

    public function testResolveAttributeValuesCoercesBoolean(): void
    {
        $type = new ProductType();
        $type->setName('Smartwatch');
        $type->setSlug('smartwatch');
        $waterproof = new ProductTypeAttribute();
        $waterproof->setName('Waterproof');
        $waterproof->setSlug('waterproof');
        $waterproof->setDataType('boolean');
        $type->addAttribute($waterproof);

        $resolved = $this->service->resolveAttributeValues($type, ['waterproof' => 'true']);

        $this->assertSame(['waterproof' => true], $resolved);
    }
}
