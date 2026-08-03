<?php

namespace App\Command;

use App\Service\FeatureService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Dumps product/category/brand/user feature vectors to CSV so they can be
 * loaded into an offline recommendation model (e.g. via pandas) without
 * having to call the admin JSON endpoints.
 */
#[AsCommand(name: 'app:export-features', description: 'Export product/category/brand/user feature vectors as CSV')]
class ExportFeaturesCommand extends Command
{
    public function __construct(private FeatureService $featureService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('outputDir', InputArgument::OPTIONAL, 'Directory to write the CSV files into', 'var/features');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dir = rtrim($input->getArgument('outputDir'), '/\\');

        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            $io->error("Could not create output directory: {$dir}");

            return Command::FAILURE;
        }

        $this->writeCsv("{$dir}/products.csv", $this->featureService->getProductFeatures());
        $this->writeCsv("{$dir}/categories.csv", $this->featureService->getCategoryFeatures());
        $this->writeCsv("{$dir}/brands.csv", $this->featureService->getBrandFeatures());
        $this->writeCsv("{$dir}/users.csv", $this->featureService->getUserFeatures());

        $io->success("Feature CSVs written to {$dir}/ (products.csv, categories.csv, brands.csv, users.csv)");

        return Command::SUCCESS;
    }

    private function writeCsv(string $path, array $rows): void
    {
        $handle = fopen($path, 'w');

        if (!empty($rows)) {
            fputcsv($handle, array_keys($rows[0]));
            foreach ($rows as $row) {
                fputcsv($handle, array_map(
                    fn ($value) => is_bool($value) ? (int) $value : $value,
                    $row
                ));
            }
        }

        fclose($handle);
    }
}
