<?php

namespace App\Service;

use App\DTO\Category\CreateCategoryRequest;
use App\DTO\Category\UpdateCategoryRequest;
use App\Entity\Category;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;

class CategoryService
{
    public function __construct(
        private CategoryRepository $categoryRepository,
        private EntityManagerInterface $em,
        private SlugService $slugService,
    ) {
    }

    public function create(CreateCategoryRequest $dto): Category
    {
        $parent = null;
        if (null !== $dto->parentId) {
            $parent = $this->categoryRepository->find($dto->parentId);
            if (!$parent) {
                throw new \RuntimeException('Parent category not found', 404);
            }
        }

        $category = new Category();
        $category->setName($dto->name);
        $category->setSlug($this->slugService->generateCategorySlug($dto->name));
        $category->setImage($dto->image);
        $category->setParent($parent);
        $category->setSeasonalMonths($dto->seasonalMonths);

        $this->em->persist($category);
        $this->em->flush();

        return $category;
    }

    /**
     * @param array<string, mixed> $rawData decoded request body — used to detect explicit
     *                                      null for parentId/image (key present but null) vs. the key being absent entirely
     */
    public function update(Category $category, UpdateCategoryRequest $dto, array $rawData): Category
    {
        if (null !== $dto->name) {
            $category->setName($dto->name);
            $category->setSlug($this->slugService->generateCategorySlug($dto->name, $category->getId()));
        }
        if (array_key_exists('image', $rawData)) {
            $category->setImage($dto->image);
        }
        if (array_key_exists('seasonalMonths', $rawData)) {
            $category->setSeasonalMonths($dto->seasonalMonths);
        }
        if (array_key_exists('parentId', $rawData)) {
            if (null === $dto->parentId) {
                $category->setParent(null);
            } else {
                $parent = $this->categoryRepository->find($dto->parentId);
                if (!$parent) {
                    throw new \RuntimeException('Parent category not found', 404);
                }
                if ($parent->getId() === $category->getId()) {
                    throw new \RuntimeException('A category cannot be its own parent', 400);
                }
                $category->setParent($parent);
            }
        }

        $category->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        return $category;
    }

    public function delete(Category $category): void
    {
        $this->em->remove($category);
        $this->em->flush();
    }
}
