<?php

namespace App\Tests\Unit\Recommendation;

use App\Entity\Product;
use App\Service\Recommendation\ContentRecommendationService;
use App\Service\Recommendation\ContentSimilarityService;
use PHPUnit\Framework\TestCase;

class ContentRecommendationServiceTest extends TestCase
{
    private ContentSimilarityService $contentSimilarity;
    private ContentRecommendationService $service;

    protected function setUp(): void
    {
        $this->contentSimilarity = $this->createMock(ContentSimilarityService::class);
        $this->service = new ContentRecommendationService($this->contentSimilarity);
    }

    private function makeProduct(int $id): Product
    {
        $product = new Product();
        $ref = new \ReflectionProperty(Product::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($product, $id);

        return $product;
    }

    public function testScoresCandidatesBySimilarityTimesTasteScore(): void
    {
        $seed = $this->makeProduct(1);
        $candidate = $this->makeProduct(2);
        $productsById = [1 => $seed, 2 => $candidate];

        $this->contentSimilarity->method('score')->with($seed, $candidate)->willReturn(0.8);

        $result = $this->service->predictForUser([1 => 5.0], $productsById);

        $this->assertSame(4.0, $result[2]); // 0.8 * 5.0
    }

    public function testSkipsCandidatesWithZeroOrNegativeSimilarity(): void
    {
        $seed = $this->makeProduct(1);
        $candidateZero = $this->makeProduct(2);
        $candidateNegative = $this->makeProduct(3);
        $productsById = [1 => $seed, 2 => $candidateZero, 3 => $candidateNegative];

        $this->contentSimilarity->method('score')->willReturnMap([
            [$seed, $candidateZero, 0.0],
            [$seed, $candidateNegative, -0.5],
        ]);

        $result = $this->service->predictForUser([1 => 5.0], $productsById);

        $this->assertSame([], $result);
    }

    public function testSkipsSeedsWithNonPositiveTasteScore(): void
    {
        $seed = $this->makeProduct(1);
        $candidate = $this->makeProduct(2);
        $productsById = [1 => $seed, 2 => $candidate];

        $this->contentSimilarity->expects($this->never())->method('score');

        $result = $this->service->predictForUser([1 => 0.0], $productsById);

        $this->assertSame([], $result);
    }

    public function testSkipsSeedsMissingFromProductsById(): void
    {
        $candidate = $this->makeProduct(2);
        $productsById = [2 => $candidate];

        $this->contentSimilarity->expects($this->never())->method('score');

        $result = $this->service->predictForUser([99 => 5.0], $productsById);

        $this->assertSame([], $result);
    }

    public function testDoesNotRecommendCandidatesAlreadyRatedByTheUser(): void
    {
        $seed = $this->makeProduct(1);
        $alreadyRated = $this->makeProduct(2);
        $productsById = [1 => $seed, 2 => $alreadyRated];

        // Only the seed's own similarity call happens, if any — 2 must never
        // be scored as a candidate since it's already rated.
        $this->contentSimilarity->expects($this->never())->method('score');

        $result = $this->service->predictForUser([1 => 5.0, 2 => 3.0], $productsById);

        $this->assertSame([], $result);
    }

    public function testAccumulatesContributionsFromMultipleSeeds(): void
    {
        $seed1 = $this->makeProduct(1);
        $seed2 = $this->makeProduct(6);
        $candidate = $this->makeProduct(7);
        $productsById = [1 => $seed1, 6 => $seed2, 7 => $candidate];

        $this->contentSimilarity->method('score')->willReturnMap([
            [$seed1, $candidate, 1.0],
            [$seed2, $candidate, 0.5],
        ]);

        $result = $this->service->predictForUser([1 => 2.0, 6 => 3.0], $productsById);

        // seed1: 1.0 * 2.0 = 2.0 ; seed2: 0.5 * 3.0 = 1.5 ; total 3.5
        $this->assertSame(3.5, $result[7]);
    }

    public function testReturnsEmptyArrayWhenNoUserRatings(): void
    {
        $productsById = [1 => $this->makeProduct(1)];

        $result = $this->service->predictForUser([], $productsById);

        $this->assertSame([], $result);
    }
}
