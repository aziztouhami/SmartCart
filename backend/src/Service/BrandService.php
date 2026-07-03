<?php

namespace App\Service;

use App\DTO\Brand\CreateBrandRequest;
use App\DTO\Brand\UpdateBrandRequest;
use App\Entity\Brand;
use Doctrine\ORM\EntityManagerInterface;

class BrandService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function create(CreateBrandRequest $dto): Brand
    {
        $brand = new Brand();
        $brand->setName($dto->name);
        $brand->setImage($dto->image);
        $brand->setDescription($dto->description);
        $brand->setJoinedAt(
            $dto->joinedAt ? new \DateTimeImmutable($dto->joinedAt) : new \DateTimeImmutable()
        );

        $this->em->persist($brand);
        $this->em->flush();

        return $brand;
    }

    /**
     * @param array<string, mixed> $rawData decoded request body — used to detect explicit
     *        null for image (key present but null, meaning "remove it") vs. the key being absent
     */
    public function update(Brand $brand, UpdateBrandRequest $dto, array $rawData = []): Brand
    {
        if ($dto->name !== null) {
            $brand->setName($dto->name);
        }
        if (array_key_exists('image', $rawData)) {
            $brand->setImage($dto->image);
        }
        if ($dto->description !== null) {
            $brand->setDescription($dto->description);
        }
        if ($dto->joinedAt !== null) {
            $brand->setJoinedAt(new \DateTimeImmutable($dto->joinedAt));
        }

        $this->em->flush();

        return $brand;
    }

    public function delete(Brand $brand): void
    {
        $this->em->remove($brand);
        $this->em->flush();
    }
}
