<?php

namespace App\Service\Recommendation;

use App\Entity\Product;

/**
 * "Is this product in season right now" — currently answered from manual
 * tagging (Category::$seasonalMonths, set by admins via the category API).
 * Kept as its own service so the source of truth can later be swapped for
 * (or blended with) the learned alternative computed by
 * App\Command\AnalyzeSeasonalTrendsCommand, without touching any of the
 * call sites that ask "is it in season".
 */
class SeasonalBoostService
{
    public function isInSeason(Product $product, ?\DateTimeImmutable $now = null): bool
    {
        $months = $product->getCategory()?->getSeasonalMonths();
        if (empty($months)) {
            return false;
        }

        $currentMonth = (int) ($now ?? new \DateTimeImmutable())->format('n');

        return in_array($currentMonth, $months, true);
    }
}
