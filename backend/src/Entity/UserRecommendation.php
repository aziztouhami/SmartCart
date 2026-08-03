<?php

namespace App\Entity;

use App\Repository\UserRecommendationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * One row of a logged-in user's precomputed hybrid recommendation list —
 * the blended (CF + content + business rules) output of
 * UserRecommendationBuilderService. Serving a recommendation is then a
 * single indexed lookup by user_id, never the blend itself.
 */
#[ORM\Entity(repositoryClass: UserRecommendationRepository::class)]
#[ORM\Table(name: 'user_recommendation')]
#[ORM\UniqueConstraint(name: 'uniq_user_recommendation_product', columns: ['user_id', 'product_id'])]
#[ORM\Index(columns: ['user_id', 'score'])]
class UserRecommendation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Product $product = null;

    #[ORM\Column(type: 'float')]
    private float $score = 0.0;

    /** 'hybrid' | 'preferences' | 'trending' — which path produced this row, mostly for debugging/analytics. */
    #[ORM\Column(length: 20)]
    private string $source = 'hybrid';

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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

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

    public function getScore(): float
    {
        return $this->score;
    }

    public function setScore(float $score): self
    {
        $this->score = $score;

        return $this;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): self
    {
        $this->source = $source;

        return $this;
    }

    public function getComputedAt(): ?\DateTimeImmutable
    {
        return $this->computedAt;
    }
}
