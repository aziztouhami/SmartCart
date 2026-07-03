<?php

namespace App\Recommendation\Entity;

use App\Entity\Product;
use App\Recommendation\Repository\ProductRelationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Precomputed "people who viewed/bought this also viewed/bought that" edge,
 * one row per directed (product, relatedProduct) pair. Rebuilt from scratch
 * by the offline batch job (RecommendationBuilderService) — serving a
 * recommendation is then just an indexed lookup by product_id, no
 * computation on the request path.
 */
#[ORM\Entity(repositoryClass: ProductRelationRepository::class)]
#[ORM\Table(name: 'product_relation')]
#[ORM\UniqueConstraint(name: 'uniq_product_related', columns: ['product_id', 'related_product_id', 'type'])]
#[ORM\Index(columns: ['product_id', 'type', 'score'])]
class ProductRelation
{
    public const TYPE_SIMILAR = 'similar';
    public const TYPE_COMPLEMENTARY = 'complementary';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Product $product = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(name: 'related_product_id', nullable: false, onDelete: 'CASCADE')]
    private ?Product $relatedProduct = null;

    #[ORM\Column(type: 'float')]
    private float $score = 0.0;

    /**
     * 'similar' — same category/brand/type alternative (content-based).
     * 'complementary' — frequently bought/carted together, different category (behavioral).
     */
    #[ORM\Column(length: 20)]
    private string $type = self::TYPE_SIMILAR;

    #[ORM\Column]
    private ?\DateTimeImmutable $computedAt = null;

    public function __construct()
    {
        $this->computedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getRelatedProduct(): ?Product
    {
        return $this->relatedProduct;
    }

    public function setRelatedProduct(?Product $relatedProduct): self
    {
        $this->relatedProduct = $relatedProduct;
        return $this;
    }

    public function getScore(): float
    {
        return $this->score;
    }

    public function setScore(float $score): self
    {
        $this->score = $score;
        return $this;
    }

    public function getComputedAt(): ?\DateTimeImmutable
    {
        return $this->computedAt;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }
}
