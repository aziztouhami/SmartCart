<?php

namespace App\Recommendation\Command;

use App\Recommendation\Service\ColdStartRecommendationService;
use App\Recommendation\Service\RecommendationBuilderService;
use App\Recommendation\Service\UserRecommendationBuilderService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Recomputes both recommendation tables: product_relation (guest sessions +
 * item co-occurrence/content, for anonymous visitors) and
 * user_recommendation (the logged-in CF + content hybrid). Meant to run
 * daily via an external scheduler (host cron / Windows Task Scheduler) —
 * recommendations stay served from whatever the last successful run
 * produced until the next one completes.
 */
#[AsCommand(name: 'app:rebuild-recommendations', description: 'Recompute guest session and logged-in hybrid product recommendations')]
class RebuildRecommendationsCommand extends Command
{
    public function __construct(
        private RecommendationBuilderService $itemRelationBuilder,
        private UserRecommendationBuilderService $userRecommendationBuilder,
        private ColdStartRecommendationService $coldStartRecommendationBuilder,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $coldStartStats = $this->coldStartRecommendationBuilder->rebuild();
        $io->success(sprintf('Cold-start fallback: %d row(s) stored.', $coldStartStats['rows']));

        $itemStats = $this->itemRelationBuilder->rebuild();
        $io->success(sprintf(
            'Item relations: %d activity group(s) across %d product(s) — %d relation rows stored.',
            $itemStats['groups'],
            $itemStats['products'],
            $itemStats['pairs']
        ));
        if ($itemStats['contentWeightsLearned']) {
            $io->writeln(sprintf(
                '  Content weights (%.0f%% learned, %.0f%% default prior): %s',
                $itemStats['contentWeightsConfidence'] * 100,
                (1 - $itemStats['contentWeightsConfidence']) * 100,
                json_encode($itemStats['contentWeights'])
            ));
        } else {
            $io->writeln('  Not enough co-occurrence data yet — using default content weights.');
        }

        $userStats = $this->userRecommendationBuilder->rebuild();
        $io->success(sprintf(
            'User recommendations: %d user(s) (%d hybrid, %d fallback) — %d rows stored.',
            $userStats['users'],
            $userStats['hybrid'],
            $userStats['fallback'],
            $userStats['rows']
        ));

        return Command::SUCCESS;
    }
}
