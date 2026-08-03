<?php

namespace App\DTO\Promotion;

use App\Entity\Promotion;

class PromotionListItem
{
    public int $id;
    public string $type;
    public ?array $product;
    public ?array $brand;
    public string $discountType;
    public ?float $percentage;
    public ?float $fixedPrice;
    public string $startDate;
    public ?string $endDate;
    public string $status;
    public string $createdAt;

    public static function fromEntity(Promotion $promotion): self
    {
        $dto = new self();
        $dto->id = $promotion->getId();
        $dto->type = $promotion->getType();

        $product = $promotion->getProduct();
        $dto->product = $product ? ['id' => $product->getId(), 'name' => $product->getName(), 'price' => (float) $product->getPrice()] : null;

        $brand = $promotion->getBrand();
        $dto->brand = $brand ? ['id' => $brand->getId(), 'name' => $brand->getName()] : null;

        $dto->discountType = $promotion->getDiscountType();
        $dto->percentage = null !== $promotion->getPercentage() ? (float) $promotion->getPercentage() : null;
        $dto->fixedPrice = null !== $promotion->getFixedPrice() ? (float) $promotion->getFixedPrice() : null;
        $dto->startDate = $promotion->getStartDate()->format(\DateTimeInterface::ATOM);
        $dto->endDate = $promotion->getEndDate()?->format(\DateTimeInterface::ATOM);

        $now = new \DateTimeImmutable();
        if (!$promotion->isActive($now)) {
            $dto->status = null !== $promotion->getEndDate() && $promotion->getEndDate() < $now ? 'ended' : 'scheduled';
        } else {
            $dto->status = 'active';
        }

        $dto->createdAt = $promotion->getCreatedAt()->format(\DateTimeInterface::ATOM);

        return $dto;
    }
}
