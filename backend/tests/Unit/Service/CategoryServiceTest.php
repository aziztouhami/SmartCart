<?php

namespace App\Tests\Unit\Service;

use App\DTO\Category\CreateCategoryRequest;
use App\DTO\Category\UpdateCategoryRequest;
use App\Entity\Category;
use App\Repository\CategoryRepository;
use App\Service\CategoryService;
use App\Service\SlugService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class CategoryServiceTest extends TestCase
{
    private CategoryRepository $categoryRepository;
    private EntityManagerInterface $em;
    private SlugService $slugService;
    private CategoryService $service;

    protected function setUp(): void
    {
        $this->categoryRepository = $this->createMock(CategoryRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->slugService = $this->createMock(SlugService::class);
        $this->slugService->method('generateCategorySlug')->willReturn('a-slug');

        $this->service = new CategoryService($this->categoryRepository, $this->em, $this->slugService);
    }

    private function withId(Category $category, int $id): Category
    {
        $ref = new \ReflectionProperty(Category::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($category, $id);

        return $category;
    }

    public function testCreateThrowsWhenParentNotFound(): void
    {
        $this->categoryRepository->method('find')->willReturn(null);

        $dto = new CreateCategoryRequest();
        $dto->name = 'Electronics';
        $dto->parentId = 99;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Parent category not found');

        $this->service->create($dto);
    }

    public function testCreateRootCategorySucceeds(): void
    {
        $this->em->expects($this->once())->method('persist')->with($this->isInstanceOf(Category::class));
        $this->em->expects($this->once())->method('flush');

        $dto = new CreateCategoryRequest();
        $dto->name = 'Electronics';

        $category = $this->service->create($dto);

        $this->assertSame('Electronics', $category->getName());
        $this->assertSame('a-slug', $category->getSlug());
        $this->assertNull($category->getParent());
    }

    public function testCreateSubCategoryAttachesParent(): void
    {
        $parent = $this->withId(new Category(), 1);
        $this->categoryRepository->method('find')->willReturn($parent);

        $dto = new CreateCategoryRequest();
        $dto->name = 'Phones';
        $dto->parentId = 1;

        $category = $this->service->create($dto);

        $this->assertSame($parent, $category->getParent());
    }

    public function testUpdateRejectsSelfAsParent(): void
    {
        $category = $this->withId(new Category(), 5);
        $this->categoryRepository->method('find')->willReturn($category);

        $dto = new UpdateCategoryRequest();
        $dto->parentId = 5;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('A category cannot be its own parent');

        $this->service->update($category, $dto, ['parentId' => 5]);
    }

    public function testUpdateThrowsWhenNewParentNotFound(): void
    {
        $category = $this->withId(new Category(), 5);
        $this->categoryRepository->method('find')->willReturn(null);

        $dto = new UpdateCategoryRequest();
        $dto->parentId = 99;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Parent category not found');

        $this->service->update($category, $dto, ['parentId' => 99]);
    }

    public function testUpdateClearsParentWhenExplicitlySetToNull(): void
    {
        $parent = $this->withId(new Category(), 1);
        $category = $this->withId(new Category(), 5);
        $category->setParent($parent);

        $dto = new UpdateCategoryRequest();
        $dto->parentId = null;

        $result = $this->service->update($category, $dto, ['parentId' => null]);

        $this->assertNull($result->getParent());
    }

    public function testUpdateLeavesParentUnchangedWhenKeyAbsentFromRawData(): void
    {
        $parent = $this->withId(new Category(), 1);
        $category = $this->withId(new Category(), 5);
        $category->setParent($parent);

        $dto = new UpdateCategoryRequest();

        $result = $this->service->update($category, $dto, []);

        $this->assertSame($parent, $result->getParent());
    }

    public function testDeleteRemovesCategory(): void
    {
        $category = new Category();

        $this->em->expects($this->once())->method('remove')->with($category);
        $this->em->expects($this->once())->method('flush');

        $this->service->delete($category);
    }
}
