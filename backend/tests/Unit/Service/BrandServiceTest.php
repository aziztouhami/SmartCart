<?php

namespace App\Tests\Unit\Service;

use App\DTO\Brand\CreateBrandRequest;
use App\DTO\Brand\UpdateBrandRequest;
use App\Entity\Brand;
use App\Service\BrandService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class BrandServiceTest extends TestCase
{
    private EntityManagerInterface $em;
    private BrandService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->service = new BrandService($this->em);
    }

    public function testCreatePersistsBrandWithDefaults(): void
    {
        $this->em->expects($this->once())->method('persist')->with($this->isInstanceOf(Brand::class));
        $this->em->expects($this->once())->method('flush');

        $dto = new CreateBrandRequest();
        $dto->name = 'Acme';

        $brand = $this->service->create($dto);

        $this->assertSame('Acme', $brand->getName());
        $this->assertNotNull($brand->getJoinedAt());
    }

    public function testCreateUsesProvidedJoinedAt(): void
    {
        $dto = new CreateBrandRequest();
        $dto->name = 'Acme';
        $dto->joinedAt = '2020-01-01';

        $brand = $this->service->create($dto);

        $this->assertSame('2020-01-01', $brand->getJoinedAt()->format('Y-m-d'));
    }

    public function testUpdateOnlyChangesSuppliedFields(): void
    {
        $brand = new Brand();
        $brand->setName('Old Name');
        $brand->setDescription('Old description');
        $brand->setImage('old.png');

        $dto = new UpdateBrandRequest();
        $dto->description = 'New description';

        $result = $this->service->update($brand, $dto, ['description' => 'New description']);

        $this->assertSame('Old Name', $result->getName());
        $this->assertSame('New description', $result->getDescription());
        $this->assertSame('old.png', $result->getImage());
    }

    public function testUpdateClearsImageWhenExplicitlySetToNull(): void
    {
        $brand = new Brand();
        $brand->setName('Acme');
        $brand->setImage('old.png');

        $dto = new UpdateBrandRequest();
        $dto->image = null;

        $result = $this->service->update($brand, $dto, ['image' => null]);

        $this->assertNull($result->getImage());
    }

    public function testUpdateLeavesImageUnchangedWhenKeyAbsentFromRawData(): void
    {
        $brand = new Brand();
        $brand->setName('Acme');
        $brand->setImage('old.png');

        $dto = new UpdateBrandRequest();

        $result = $this->service->update($brand, $dto, []);

        $this->assertSame('old.png', $result->getImage());
    }

    public function testDeleteRemovesBrand(): void
    {
        $brand = new Brand();

        $this->em->expects($this->once())->method('remove')->with($brand);
        $this->em->expects($this->once())->method('flush');

        $this->service->delete($brand);
    }
}
