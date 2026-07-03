<?php

namespace App\Service;

use App\DTO\Product\CreateProductRequest;
use App\DTO\Product\UpdateProductRequest;
use App\Entity\Product;
use App\Repository\BrandRepository;
use App\Repository\CategoryRepository;
use App\Repository\ProductTypeRepository;
use Doctrine\ORM\EntityManagerInterface;

class ProductService
{
    public function __construct(
        private CategoryRepository $categoryRepository,
        private BrandRepository $brandRepository,
        private ProductTypeRepository $productTypeRepository,
        private ProductTypeService $productTypeService,
        private EntityManagerInterface $em,
        private SlugService $slugService,
    ) {}

    public function create(CreateProductRequest $dto): Product
    {
        $category = $this->categoryRepository->find($dto->categoryId);
        if (!$category) {
            throw new \RuntimeException('Category not found', 404);
        }
        if (!$category->getChildren()->isEmpty()) {
            throw new \RuntimeException('Products can only be assigned to sub-categories, not parent categories.', 400);
        }

        $product = new Product();
        $product->setName($dto->name);
        $product->setDescription($dto->description);
        $product->setPrice((string) $dto->price);
        $product->setStock($dto->stock);
        $product->setCategory($category);
        $product->setImages($dto->images);
        $product->setSlug($this->slugService->generateProductSlug($dto->name));

        if (property_exists($dto, 'brandId') && $dto->brandId !== null) {
            $brand = $this->brandRepository->find($dto->brandId);
            if (!$brand) {
                throw new \RuntimeException('Brand not found', 404);
            }
            $product->setBrand($brand);
        }

        if ($dto->productTypeId !== null) {
            $type = $this->productTypeRepository->find($dto->productTypeId);
            if (!$type) {
                throw new \RuntimeException('Product type not found', 404);
            }
            $product->setProductType($type);
            $product->setAttributes($this->productTypeService->resolveAttributeValues($type, $dto->attributes));
        }

        $this->em->persist($product);
        $this->em->flush();

        return $product;
    }

    public function update(Product $product, UpdateProductRequest $dto): Product
    {
        if ($dto->name !== null) {
            $product->setName($dto->name);
            $product->setSlug($this->slugService->generateProductSlug($dto->name, $product->getId()));
        }
        if ($dto->price !== null) {
            $product->setPrice((string) $dto->price);
        }
        if ($dto->stock !== null) {
            $product->setStock($dto->stock);
        }
        if ($dto->description !== null) {
            $product->setDescription($dto->description);
        }
        if ($dto->images !== null) {
            $product->setImages($dto->images);
        }
        if ($dto->categoryId !== null) {
            $category = $this->categoryRepository->find($dto->categoryId);
            if (!$category) {
                throw new \RuntimeException('Category not found', 404);
            }
            if (!$category->getChildren()->isEmpty()) {
                throw new \RuntimeException('Products can only be assigned to sub-categories, not parent categories.', 400);
            }
            $product->setCategory($category);
        }

        if (property_exists($dto, 'brandId') && $dto->brandId !== null) {
            $brand = $this->brandRepository->find($dto->brandId);
            if (!$brand) {
                throw new \RuntimeException('Brand not found', 404);
            }
            $product->setBrand($brand);
        } elseif (property_exists($dto, 'brandId') && $dto->brandId === null && method_exists($product, 'setBrand')) {
            $product->setBrand(null);
        }

        if ($dto->productTypeId !== null) {
            $type = $this->productTypeRepository->find($dto->productTypeId);
            if (!$type) {
                throw new \RuntimeException('Product type not found', 404);
            }
            $product->setProductType($type);
        }

        if ($dto->attributes !== null) {
            $type = $product->getProductType();
            if (!$type) {
                throw new \RuntimeException('Assign a product type before setting its features', 400);
            }
            $product->setAttributes($this->productTypeService->resolveAttributeValues($type, $dto->attributes));
        }

        $product->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        return $product;
    }

    public function updateStock(Product $product, array $data): Product
    {
        if (array_key_exists('quantity', $data)) {
            $qty = (int) $data['quantity'];
            if ($qty < 0) {
                throw new \RuntimeException('Stock cannot be negative', 400);
            }
            $product->setStock($qty);
        } elseif (array_key_exists('adjustment', $data)) {
            $newStock = $product->getStock() + (int) $data['adjustment'];
            if ($newStock < 0) {
                throw new \RuntimeException('Adjustment would result in negative stock', 400);
            }
            $product->setStock($newStock);
        } else {
            throw new \RuntimeException('Provide either "quantity" or "adjustment"', 400);
        }

        $product->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        return $product;
    }

    public function delete(Product $product): void
    {
        $this->em->remove($product);
        $this->em->flush();
    }
}
