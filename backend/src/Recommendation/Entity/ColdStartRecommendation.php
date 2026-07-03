<?php

namespace App\Recommendation\Entity;

use App\Entity\Product;
use App\Recommendation\Repository\ColdStartRecommendationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * The precomputed "what do we show someone with absolutely no history yet"
 * list — one global ranked list, not per-user/session. Blends what
 * brand-new visitors tend to look at first with what's trending right now.
 * Built by ColdStartRecommendationService, read by RecommendationController
 * whenever there's no session/interaction history to personalize from.
 */
#[ORM\Entity(repositoryClass: ColdStartRecommendationRepository::class)]
#[ORM\Table(name: 'cold_start_recommendation')]
class ColdStartRecommendation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Product $product = null;

    #[ORM\Column(type: 'float')]
    private float $score = 0.0;

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

    public function getScore(): float
    {
        return $this->score;
    }

    public function setScore(float $score): self
    {
        $this->score = $score;
        return $this;
    }
}
