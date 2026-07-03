<?php

namespace App\Entity;

use App\Repository\PromotionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PromotionRepository::class)]
class Promotion
{
    public const TYPE_PRODUCT = 'product';
    public const TYPE_BRAND   = 'brand';
    public const TYPE_ALL     = 'all';

    public const DISCOUNT_PERCENTAGE = 'percentage';
    public const DISCOUNT_FIXED      = 'fixed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private ?string $type = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Product $product = null;

    #[ORM\ManyToOne(targetEntity: Brand::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Brand $brand = null;

    #[ORM\Column(length: 20)]
    private ?string $discountType = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    private ?string $percentage = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $fixedPrice = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $endDate = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): self
    {
        $this->product = $product;
        return $this;
    }

    public function getBrand(): ?Brand
    {
        return $this->brand;
    }

    public function setBrand(?Brand $brand): self
    {
        $this->brand = $brand;
        return $this;
    }

    public function getDiscountType(): ?string
    {
        return $this->discountType;
    }

    public function setDiscountType(string $discountType): self
    {
        $this->discountType = $discountType;
        return $this;
    }

    public function getPercentage(): ?string
    {
        return $this->percentage;
    }

    public function setPercentage(?string $percentage): self
    {
        $this->percentage = $percentage;
        return $this;
    }

    public function getFixedPrice(): ?string
    {
        return $this->fixedPrice;
    }

    public function setFixedPrice(?string $fixedPrice): self
    {
        $this->fixedPrice = $fixedPrice;
        return $this;
    }

    public function getStartDate(): ?\DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeImmutable $startDate): self
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeImmutable $endDate): self
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function isActive(\DateTimeImmutable $now): bool
    {
        if ($this->startDate > $now) {
            return false;
        }
        if ($this->endDate !== null && $this->endDate < $now) {
            return false;
        }
        return true;
    }

    /**
     * Compute the discounted price for a given base price.
     */
    public function computePrice(float $basePrice): float
    {
        if ($this->discountType === self::DISCOUNT_FIXED) {
            return (float) $this->fixedPrice;
        }

        $pct = (float) $this->percentage;
        return round($basePrice * (1 - $pct / 100), 2);
    }

    /**
     * Public-facing shape attached to a product in API responses.
     */
    public function toPublicArray(float $basePrice): array
    {
        $newPrice = $this->computePrice($basePrice);

        return [
            'id'           => $this->id,
            'discountType' => $this->discountType,
            'percentage'   => $this->percentage !== null
                ? (float) $this->percentage
                : round((1 - $newPrice / $basePrice) * 100, 2),
            'oldPrice'     => $basePrice,
            'newPrice'     => $newPrice,
            'endDate'      => $this->endDate?->format(\DateTimeInterface::ATOM),
        ];
    }
}
