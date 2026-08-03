<?php

namespace App\Command;

use App\Repository\CategoryRepository;
use App\Repository\CategorySeasonalScoreRepository;
use App\Repository\OrderRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Data-driven alternative to the manually-tagged seasonal boost
 * (Category::$seasonalMonths, applied by SeasonalBoostService). Mines order
 * history to answer "does this category actually sell more in month M than
 * its own yearly average" — a seasonality index per (category, month),
 * stored in category_seasonal_score.
 *
 * Standalone and NOT wired into the live recommendation pipeline: it is not
 * called from RebuildRecommendationsCommand, and nothing reads
 * category_seasonal_score yet. Run it manually whenever you want to see
 * what the data suggests:
 *
 *     php bin/console app:analyze-seasonal-trends
 *
 * To integrate it later, the simplest path is to give SeasonalBoostService
 * a second data source — inject CategorySeasonalScoreRepository, and in
 * isInSeason() (or a new isInSeason()-returning-a-strength variant) check
 * findAllAsMap()[$categoryId][$currentMonth] ?? 1.0 against a threshold
 * (e.g. > 1.2 = "in season"), blended with or falling back to the manual
 * tags the same way ContentSimilarityService blends learned weights with
 * hand-picked defaults until there's enough data to trust.
 *
 * Caveat: with less than a full year (ideally several years) of order
 * history, "this month is busier" and "the catalog only had stock in this
 * month" are indistinguishable. The MIN_TOTAL_QUANTITY threshold below is a
 * coarse guard against the noisiest cases, not a substitute for enough data.
 */
#[AsCommand(name: 'app:analyze-seasonal-trends', description: 'Compute a learned seasonality index per category/month from order history (standalone — not wired into serving)')]
class AnalyzeSeasonalTrendsCommand extends Command
{
    // Below this many total units sold across the year, a category's
    // month-by-month split is mostly noise — skip it rather than store a
    // confident-looking number backed by a handful of orders.
    private const MIN_TOTAL_QUANTITY = 20;

    // How far a month's index has to stray from "average" (1.0) to be
    // worth highlighting as in/out of season in the summary table.
    private const NOTABLE_THRESHOLD = 1.2;

    public function __construct(
        private OrderRepository $orderRepository,
        private CategoryRepository $categoryRepository,
        private CategorySeasonalScoreRepository $categorySeasonalScoreRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $byCategoryId = [];
        foreach ($this->categoryRepository->findAll() as $category) {
            $byCategoryId[$category->getId()] = $category;
        }

        $quantities = $this->orderRepository->sumQuantityByCategoryAndMonth();

        $byCategory = [];
        foreach ($quantities as $row) {
            $byCategory[$row['categoryId']][$row['month']] = $row['quantity'];
        }

        $rows = [];
        $summaryLines = [];
        $skipped = 0;

        foreach ($byCategory as $categoryId => $monthCounts) {
            $total = array_sum($monthCounts);
            if ($total < self::MIN_TOTAL_QUANTITY) {
                ++$skipped;
                continue;
            }

            // "Average month" baseline assumes a flat 12-month calendar —
            // months with zero sales still count toward the average, so a
            // category that only ever sells in one month gets a sharp peak
            // there instead of an artificially smoothed one.
            $average = $total / 12;

            $monthScores = [];
            for ($month = 1; $month <= 12; ++$month) {
                $monthScores[$month] = round(($monthCounts[$month] ?? 0) / $average, 3);
            }

            foreach ($monthScores as $month => $score) {
                $rows[] = ['categoryId' => $categoryId, 'month' => $month, 'score' => $score];
            }

            $peakMonth = array_search(max($monthScores), $monthScores, true);
            $peakScore = $monthScores[$peakMonth];
            if ($peakScore >= self::NOTABLE_THRESHOLD) {
                $name = $byCategoryId[$categoryId]?->getName() ?? "#{$categoryId}";
                $summaryLines[] = [$name, $peakMonth, $peakScore, $total];
            }
        }

        $this->categorySeasonalScoreRepository->replaceAll($rows);

        $io->success(sprintf(
            '%d category/month score(s) stored for %d categor(y/ies); %d categor(y/ies) skipped (fewer than %d units sold all-time).',
            count($rows),
            count($byCategory) - $skipped,
            $skipped,
            self::MIN_TOTAL_QUANTITY
        ));

        if (!empty($summaryLines)) {
            usort($summaryLines, fn ($a, $b) => $b[2] <=> $a[2]);
            $io->table(
                ['Category', 'Peak month', 'Index (1.0 = average)', 'Total units/year'],
                array_map(fn ($l) => [$l[0], $l[1], $l[2], $l[3]], $summaryLines)
            );
        } else {
            $io->note('No category cleared the notable-seasonality threshold — either too little order history, or demand is genuinely flat year-round so far.');
        }

        $io->note('This data is stored but not yet read by any recommendation path — see this command\'s class docblock for how to wire it into SeasonalBoostService when ready.');

        return Command::SUCCESS;
    }
}
