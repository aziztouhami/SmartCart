<?php

namespace App\Entity;

use App\Repository\CategorySeasonalScoreRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Learned seasonality index per (category, calendar month), computed
 * offline by App\Command\AnalyzeSeasonalTrendsCommand from historical order
 * volume. 1.0 = average month for that category, >1 = above-average ("in
 * season"), <1 = below-average.
 *
 * Not read by anything yet — this is the data-driven alternative to the
 * manually-tagged Category::$seasonalMonths boost (SeasonalBoostService).
 * Kept as a separate precomputed table so a future SeasonalBoostService
 * variant can read from it without recomputing on the request path, the
 * same offline-batch-then-indexed-read pattern as the rest of this module.
 */
#[ORM\Entity(repositoryClass: CategorySeasonalScoreRepository::class)]
#[ORM\Table(name: 'category_seasonal_score')]
#[ORM\UniqueConstraint(name: 'uniq_category_month', columns: ['category_id', 'month'])]
class CategorySeasonalScore
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Category $category = null;

    #[ORM\Column]
    private int $month = 1;

    #[ORM\Column(type: 'float')]
    private float $score = 1.0;

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

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getMonth(): int
    {
        return $this->month;
    }

    public function setMonth(int $month): self
    {
        $this->month = $month;

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
}
