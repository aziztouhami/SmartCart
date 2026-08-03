<?php

namespace App\Tests\Unit\Service\Analytics;

use App\Entity\Product;
use App\Prompts\Analytics\AnomalyAnalysisPrompt;
use App\Repository\BrandRepository;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Repository\ProductTypeRepository;
use App\Service\Ai\OllamaClientService;
use App\Service\Analytics\AnomalyAnalysisService;
use App\Service\Feature\BrandFeatureBuilder;
use App\Service\Feature\CategoryFeatureBuilder;
use App\Service\Feature\InteractionAggregationService;
use App\Service\Feature\ProductFeatureBuilder;
use App\Service\Feature\ProductTypeFeatureBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AnomalyAnalysisServiceTest extends TestCase
{
    private ProductRepository&MockObject $productRepository;
    private CategoryRepository&MockObject $categoryRepository;
    private BrandRepository&MockObject $brandRepository;
    private ProductTypeRepository&MockObject $productTypeRepository;
    private ProductFeatureBuilder&MockObject $productFeatureBuilder;
    private CategoryFeatureBuilder&MockObject $categoryFeatureBuilder;
    private BrandFeatureBuilder&MockObject $brandFeatureBuilder;
    private ProductTypeFeatureBuilder&MockObject $productTypeFeatureBuilder;
    private InteractionAggregationService&MockObject $aggregation;
    private AnomalyAnalysisPrompt&MockObject $prompt;
    private OllamaClientService&MockObject $ollamaClient;
    private AnomalyAnalysisService $service;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepository::class);
        $this->categoryRepository = $this->createMock(CategoryRepository::class);
        $this->brandRepository = $this->createMock(BrandRepository::class);
        $this->productTypeRepository = $this->createMock(ProductTypeRepository::class);
        $this->productFeatureBuilder = $this->createMock(ProductFeatureBuilder::class);
        $this->categoryFeatureBuilder = $this->createMock(CategoryFeatureBuilder::class);
        $this->brandFeatureBuilder = $this->createMock(BrandFeatureBuilder::class);
        $this->productTypeFeatureBuilder = $this->createMock(ProductTypeFeatureBuilder::class);
        $this->aggregation = $this->createMock(InteractionAggregationService::class);
        $this->prompt = $this->createMock(AnomalyAnalysisPrompt::class);
        $this->ollamaClient = $this->createMock(OllamaClientService::class);

        $this->service = new AnomalyAnalysisService(
            $this->productRepository,
            $this->categoryRepository,
            $this->brandRepository,
            $this->productTypeRepository,
            $this->productFeatureBuilder,
            $this->categoryFeatureBuilder,
            $this->brandFeatureBuilder,
            $this->productTypeFeatureBuilder,
            $this->aggregation,
            $this->prompt,
            $this->ollamaClient,
        );
    }

    private function stubHappyPath(array $ollamaResult): void
    {
        $product = (new Product())->setName('Test Product');
        $this->productRepository->method('find')->with(42)->willReturn($product);
        $this->productFeatureBuilder->method('build')->willReturn([
            ['productId' => 42, 'views' => 10, 'purchases' => 2],
        ]);
        $this->aggregation->method('purchaseTimeSeries')->willReturn([
            ['weekStart' => '2026-01-05', 'unitsSold' => 3],
        ]);
        $this->prompt->method('build')->willReturn('the prompt');
        $this->ollamaClient->method('generateJson')->willReturn($ollamaResult);
    }

    public function testAnalyzeProductThrows404WhenNotFound(): void
    {
        $this->productRepository->method('find')->with(999)->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(404);

        $this->service->analyzeProduct(999);
    }

    public function testAnalyzeProductThrows503WhenAiUnavailable(): void
    {
        $product = (new Product())->setName('Test Product');
        $this->productRepository->method('find')->willReturn($product);
        $this->productFeatureBuilder->method('build')->willReturn([]);
        $this->aggregation->method('purchaseTimeSeries')->willReturn([]);
        $this->ollamaClient->method('generateJson')->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(503);

        $this->service->analyzeProduct(42);
    }

    public function testSanitizeClampsOutOfRangeHealthScore(): void
    {
        $this->stubHappyPath(['healthScore' => 150, 'summary' => 'ok', 'anomalies' => []]);

        $result = $this->service->analyzeProduct(42);

        $this->assertSame(100, $result['healthScore']);
    }

    public function testSanitizeClampsNegativeHealthScore(): void
    {
        $this->stubHappyPath(['healthScore' => -20, 'summary' => 'ok', 'anomalies' => []]);

        $result = $this->service->analyzeProduct(42);

        $this->assertSame(0, $result['healthScore']);
    }

    public function testSanitizeDefaultsHealthScoreWhenMissing(): void
    {
        $this->stubHappyPath(['summary' => 'ok', 'anomalies' => []]);

        $result = $this->service->analyzeProduct(42);

        $this->assertSame(50, $result['healthScore']);
    }

    public function testSanitizeDefaultsSummaryWhenNotAString(): void
    {
        $this->stubHappyPath(['healthScore' => 70, 'summary' => ['not', 'a', 'string'], 'anomalies' => []]);

        $result = $this->service->analyzeProduct(42);

        $this->assertSame('', $result['summary']);
    }

    public function testSanitizeDowngradesInvalidSeverityToLow(): void
    {
        $this->stubHappyPath([
            'healthScore' => 70,
            'summary' => 'ok',
            'anomalies' => [
                ['metric' => 'views', 'severity' => 'catastrophic', 'finding' => 'x', 'recommendation' => 'y'],
            ],
        ]);

        $result = $this->service->analyzeProduct(42);

        $this->assertSame('low', $result['anomalies'][0]['severity']);
    }

    public function testSanitizeKeepsValidSeverity(): void
    {
        $this->stubHappyPath([
            'healthScore' => 70,
            'summary' => 'ok',
            'anomalies' => [
                ['metric' => 'views', 'severity' => 'high', 'finding' => 'x', 'recommendation' => 'y'],
            ],
        ]);

        $result = $this->service->analyzeProduct(42);

        $this->assertSame('high', $result['anomalies'][0]['severity']);
    }

    public function testSanitizeDropsNonArrayAnomalyItems(): void
    {
        $this->stubHappyPath([
            'healthScore' => 70,
            'summary' => 'ok',
            'anomalies' => ['not an array', ['metric' => 'views', 'severity' => 'low', 'finding' => 'x', 'recommendation' => 'y']],
        ]);

        $result = $this->service->analyzeProduct(42);

        $this->assertCount(1, $result['anomalies']);
    }

    public function testSanitizeHandlesNonArrayAnomaliesField(): void
    {
        $this->stubHappyPath(['healthScore' => 70, 'summary' => 'ok', 'anomalies' => 'not an array']);

        $result = $this->service->analyzeProduct(42);

        $this->assertSame([], $result['anomalies']);
    }
}
