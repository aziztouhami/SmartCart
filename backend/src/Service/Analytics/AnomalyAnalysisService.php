<?php

namespace App\Service\Analytics;

use App\Prompts\Analytics\AnomalyAnalysisPrompt;
use App\Repository\BrandRepository;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Repository\ProductTypeRepository;
use App\Service\Ai\OllamaClientService;
use App\Service\Feature\BrandFeatureBuilder;
use App\Service\Feature\CategoryFeatureBuilder;
use App\Service\Feature\InteractionAggregationService;
use App\Service\Feature\ProductFeatureBuilder;
use App\Service\Feature\ProductTypeFeatureBuilder;

/**
 * Orchestrates the "Analyze" button in the admin panel: gathers one entity's
 * existing behavioral KPIs (reusing the Service\Feature\* builders — no new
 * tracking infrastructure), adds a weekly sell-through time series derived
 * from existing purchase interactions, sends it all to the local Ollama
 * model via AnomalyAnalysisPrompt, and returns a sanitized result. Nothing
 * is persisted — every call recomputes from live data.
 */
class AnomalyAnalysisService
{
    private const TIME_SERIES_WEEKS = 8;
    private const VALID_SEVERITIES = ['low', 'medium', 'high'];

    public function __construct(
        private ProductRepository $productRepository,
        private CategoryRepository $categoryRepository,
        private BrandRepository $brandRepository,
        private ProductTypeRepository $productTypeRepository,
        private ProductFeatureBuilder $productFeatureBuilder,
        private CategoryFeatureBuilder $categoryFeatureBuilder,
        private BrandFeatureBuilder $brandFeatureBuilder,
        private ProductTypeFeatureBuilder $productTypeFeatureBuilder,
        private InteractionAggregationService $aggregation,
        private AnomalyAnalysisPrompt $prompt,
        private OllamaClientService $ollamaClient,
    ) {
    }

    public function analyzeProduct(int $id): array
    {
        $product = $this->productRepository->find($id);
        if (!$product) {
            throw new \RuntimeException('Product not found', 404);
        }

        $features = $this->findRow($this->productFeatureBuilder->build(), 'productId', $id);
        $timeSeries = $this->aggregation->purchaseTimeSeries('i.product', $id, self::TIME_SERIES_WEEKS);

        return $this->run('product', $product->getName(), $features, $timeSeries);
    }

    public function analyzeCategory(int $id): array
    {
        $category = $this->categoryRepository->find($id);
        if (!$category) {
            throw new \RuntimeException('Category not found', 404);
        }

        $features = $this->findRow($this->categoryFeatureBuilder->build(), 'categoryId', $id);
        $timeSeries = $this->aggregation->purchaseTimeSeries('p.category', $id, self::TIME_SERIES_WEEKS);

        return $this->run('category', $category->getName(), $features, $timeSeries);
    }

    public function analyzeBrand(int $id): array
    {
        $brand = $this->brandRepository->find($id);
        if (!$brand) {
            throw new \RuntimeException('Brand not found', 404);
        }

        $features = $this->findRow($this->brandFeatureBuilder->build(), 'brandId', $id);
        $timeSeries = $this->aggregation->purchaseTimeSeries('p.brand', $id, self::TIME_SERIES_WEEKS);

        return $this->run('brand', $brand->getName(), $features, $timeSeries);
    }

    public function analyzeProductType(int $id): array
    {
        $type = $this->productTypeRepository->find($id);
        if (!$type) {
            throw new \RuntimeException('Product type not found', 404);
        }

        $features = $this->findRow($this->productTypeFeatureBuilder->build(), 'productTypeId', $id);
        $timeSeries = $this->aggregation->purchaseTimeSeries('p.productType', $id, self::TIME_SERIES_WEEKS);

        return $this->run('product type', $type->getName(), $features, $timeSeries);
    }

    /**
     * @param array<string, mixed>                                 $features
     * @param array<int, array{weekStart: string, unitsSold: int}> $timeSeries
     *
     * @return array{healthScore: int, summary: string, anomalies: array<int, array{metric: string, severity: string, finding: string, recommendation: string}>}
     */
    private function run(string $entityType, string $entityName, array $features, array $timeSeries): array
    {
        $prompt = $this->prompt->build($entityType, $entityName, $features, $timeSeries);
        $result = $this->ollamaClient->generateJson($prompt);

        if (null === $result) {
            throw new \RuntimeException('AI analytics unavailable — check OLLAMA_MODEL is set (backend/.env) and the Ollama container is running.', 503);
        }

        return $this->sanitize($result);
    }

    /**
     * Never trust raw model output — same defensive-validation convention as
     * ProductTypeService::suggestAttributes() for the Groq-based feature.
     *
     * @param array<string, mixed> $raw
     *
     * @return array{healthScore: int, summary: string, anomalies: array<int, array{metric: string, severity: string, finding: string, recommendation: string}>}
     */
    private function sanitize(array $raw): array
    {
        $healthScore = $raw['healthScore'] ?? null;
        $healthScore = is_numeric($healthScore) ? (int) $healthScore : 50;
        $healthScore = max(0, min(100, $healthScore));

        $summary = is_string($raw['summary'] ?? null) ? $raw['summary'] : '';

        $anomalies = [];
        foreach ((array) ($raw['anomalies'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $severity = $item['severity'] ?? 'low';
            $anomalies[] = [
                'metric' => is_string($item['metric'] ?? null) ? $item['metric'] : '',
                'severity' => in_array($severity, self::VALID_SEVERITIES, true) ? $severity : 'low',
                'finding' => is_string($item['finding'] ?? null) ? $item['finding'] : '',
                'recommendation' => is_string($item['recommendation'] ?? null) ? $item['recommendation'] : '',
            ];
        }

        return [
            'healthScore' => $healthScore,
            'summary' => $summary,
            'anomalies' => $anomalies,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function findRow(array $rows, string $idKey, int $id): array
    {
        foreach ($rows as $row) {
            if (($row[$idKey] ?? null) === $id) {
                return $row;
            }
        }

        return [];
    }
}
