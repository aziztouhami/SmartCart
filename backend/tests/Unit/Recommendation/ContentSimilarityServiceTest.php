<?php

namespace App\Tests\Unit\Recommendation;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductType;
use App\ML\LogisticRegressionTrainer;
use App\Service\Recommendation\ContentSimilarityService;
use PHPUnit\Framework\TestCase;

class ContentSimilarityServiceTest extends TestCase
{
    private LogisticRegressionTrainer $trainer;
    private ContentSimilarityService $service;

    protected function setUp(): void
    {
        $this->trainer = $this->createMock(LogisticRegressionTrainer::class);
        $this->service = new ContentSimilarityService($this->trainer);
    }

    private function setId(object $entity, int $id): void
    {
        $ref = new \ReflectionProperty($entity::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($entity, $id);
    }

    private function makeCategory(int $id, ?Category $parent = null): Category
    {
        $category = new Category();
        $this->setId($category, $id);
        if ($parent) {
            $category->setParent($parent);
        }

        return $category;
    }

    private function makeBrand(int $id): Brand
    {
        $brand = new Brand();
        $this->setId($brand, $id);

        return $brand;
    }

    private function makeProductType(int $id): ProductType
    {
        $type = new ProductType();
        $this->setId($type, $id);

        return $type;
    }

    private function makeProduct(
        int $id,
        ?Category $category = null,
        ?Brand $brand = null,
        ?ProductType $type = null,
        array $attributes = [],
    ): Product {
        $product = new Product();
        $this->setId($product, $id);
        if ($category) {
            $product->setCategory($category);
        }
        if ($brand) {
            $product->setBrand($brand);
        }
        if ($type) {
            $product->setProductType($type);
        }
        $product->setAttributes($attributes);

        return $product;
    }

    // --- score(): default (untrained) weights = category 3.0, parentCategory 1.0, brand 1.5, type 2.0, featureMatch 4.0 ---

    public function testScoreIsZeroForProductsWithDifferentUnrelatedCategories(): void
    {
        $a = $this->makeProduct(1, category: $this->makeCategory(9001));
        $b = $this->makeProduct(2, category: $this->makeCategory(9002));

        $this->assertSame(0.0, $this->service->score($a, $b));
    }

    public function testScoreTreatsTwoCategorylessProductsAsSameCategory(): void
    {
        // extractFeatures() compares getCategory()?->getId() with ===; when
        // both sides are null this is `null === null`, which is true. Two
        // products with no category at all therefore score as if they share
        // a leaf category — a real quirk of the current implementation, not
        // an assumption.
        $a = $this->makeProduct(1);
        $b = $this->makeProduct(2);

        $this->assertSame(3.0, $this->service->score($a, $b));
    }

    public function testScoreAddsCategoryWeightForSameLeafCategory(): void
    {
        $category = $this->makeCategory(100);
        $a = $this->makeProduct(1, category: $category);
        $b = $this->makeProduct(2, category: $category);

        $this->assertSame(3.0, $this->service->score($a, $b));
    }

    public function testScoreAddsParentCategoryWeightForSameParentDifferentLeaf(): void
    {
        $parent = $this->makeCategory(1);
        $leafA = $this->makeCategory(10, $parent);
        $leafB = $this->makeCategory(20, $parent);
        $a = $this->makeProduct(1, category: $leafA);
        $b = $this->makeProduct(2, category: $leafB);

        $this->assertSame(1.0, $this->service->score($a, $b));
    }

    public function testScoreGivesNoParentBonusWhenCategoriesAreUnrelated(): void
    {
        $parentA = $this->makeCategory(1);
        $parentB = $this->makeCategory(2);
        $leafA = $this->makeCategory(10, $parentA);
        $leafB = $this->makeCategory(20, $parentB);
        $a = $this->makeProduct(1, category: $leafA);
        $b = $this->makeProduct(2, category: $leafB);

        $this->assertSame(0.0, $this->service->score($a, $b));
    }

    public function testScoreAddsBrandWeightForSameBrand(): void
    {
        $brand = $this->makeBrand(5);
        // Distinct, unrelated categories neutralize the category signal so
        // only the brand contribution is being measured.
        $a = $this->makeProduct(1, category: $this->makeCategory(9001), brand: $brand);
        $b = $this->makeProduct(2, category: $this->makeCategory(9002), brand: $brand);

        $this->assertSame(1.5, $this->service->score($a, $b));
    }

    public function testScoreAddsTypeWeightForSameTypeWithNoAttributeOverlap(): void
    {
        $type = $this->makeProductType(7);
        $a = $this->makeProduct(1, category: $this->makeCategory(9001), type: $type);
        $b = $this->makeProduct(2, category: $this->makeCategory(9002), type: $type);

        $this->assertSame(2.0, $this->service->score($a, $b));
    }

    public function testScoreAddsPartialFeatureMatchWhenSameType(): void
    {
        $type = $this->makeProductType(7);
        $a = $this->makeProduct(1, category: $this->makeCategory(9001), type: $type, attributes: ['color' => 'black', 'storage' => '64gb']);
        $b = $this->makeProduct(2, category: $this->makeCategory(9002), type: $type, attributes: ['color' => 'black', 'storage' => '128gb']);

        // type(2.0) + featureMatch(4.0 * 0.5, since 1 of 2 comparable attrs match)
        $this->assertSame(4.0, $this->service->score($a, $b));
    }

    public function testScoreAddsFullFeatureMatchWhenAllAttributesMatch(): void
    {
        $type = $this->makeProductType(7);
        $a = $this->makeProduct(1, category: $this->makeCategory(9001), type: $type, attributes: ['color' => 'black']);
        $b = $this->makeProduct(2, category: $this->makeCategory(9002), type: $type, attributes: ['color' => 'black']);

        // type(2.0) + featureMatch(4.0 * 1.0)
        $this->assertSame(6.0, $this->service->score($a, $b));
    }

    public function testFeatureMatchIsIgnoredWhenTypesDiffer(): void
    {
        $typeA = $this->makeProductType(7);
        $typeB = $this->makeProductType(8);
        $a = $this->makeProduct(1, category: $this->makeCategory(9001), type: $typeA, attributes: ['color' => 'black']);
        $b = $this->makeProduct(2, category: $this->makeCategory(9002), type: $typeB, attributes: ['color' => 'black']);

        $this->assertSame(0.0, $this->service->score($a, $b));
    }

    public function testScoreCombinesAllSignalsAdditively(): void
    {
        $category = $this->makeCategory(100);
        $brand = $this->makeBrand(5);
        $type = $this->makeProductType(7);
        $a = $this->makeProduct(1, category: $category, brand: $brand, type: $type, attributes: ['color' => 'black']);
        $b = $this->makeProduct(2, category: $category, brand: $brand, type: $type, attributes: ['color' => 'black']);

        // category(3.0) + brand(1.5) + type(2.0) + featureMatch(4.0 * 1.0) = 10.5
        $this->assertSame(10.5, $this->service->score($a, $b));
    }

    public function testScoreIsSymmetric(): void
    {
        $category = $this->makeCategory(100);
        $brand = $this->makeBrand(5);
        $a = $this->makeProduct(1, category: $category, brand: $brand);
        $b = $this->makeProduct(2, category: $category);

        $this->assertSame($this->service->score($a, $b), $this->service->score($b, $a));
    }

    public function testScoreTreatsNullCategoryAsNeverMatching(): void
    {
        $category = $this->makeCategory(100);
        $a = $this->makeProduct(1, category: $category);
        $b = $this->makeProduct(2); // no category

        $this->assertSame(0.0, $this->service->score($a, $b));
    }

    // --- featureMatchRatio() ---

    public function testFeatureMatchRatioIsZeroWhenNoComparableAttributes(): void
    {
        $ratio = $this->service->featureMatchRatio(['color' => 'black'], ['storage' => '64gb']);

        $this->assertSame(0.0, $ratio);
    }

    public function testFeatureMatchRatioIsZeroWhenFirstSetIsEmpty(): void
    {
        $ratio = $this->service->featureMatchRatio([], ['color' => 'black']);

        $this->assertSame(0.0, $ratio);
    }

    public function testFeatureMatchRatioComputesFractionOfMatchingComparableAttributes(): void
    {
        $ratio = $this->service->featureMatchRatio(
            ['color' => 'black', 'storage' => '64gb', 'ram' => '8gb'],
            ['color' => 'black', 'storage' => '128gb'] // 'ram' not comparable (absent on B)
        );

        // considered: color, storage (both present on B) => 2 ; matches: color only => 1
        $this->assertSame(0.5, $ratio);
    }

    public function testFeatureMatchRatioIsOneWhenAllComparableAttributesMatch(): void
    {
        $ratio = $this->service->featureMatchRatio(
            ['color' => 'black', 'storage' => '64gb'],
            ['color' => 'black', 'storage' => '64gb']
        );

        $this->assertSame(1.0, $ratio);
    }

    // --- bucketByShared() ---

    public function testBucketBySharedGroupsProductsByCategoryBrandTypeAndParentCategory(): void
    {
        $parent = $this->makeCategory(1);
        $leaf1 = $this->makeCategory(10, $parent);
        $leaf2 = $this->makeCategory(20, $parent);
        $brand1 = $this->makeBrand(100);
        $brand2 = $this->makeBrand(200);
        $type1 = $this->makeProductType(1000);
        $type2 = $this->makeProductType(2000);

        $p1 = $this->makeProduct(1, category: $leaf1, brand: $brand1, type: $type1);
        $p2 = $this->makeProduct(2, category: $leaf1, brand: $brand2, type: $type2);
        $p3 = $this->makeProduct(3, category: $leaf2, brand: $brand1, type: $type1);
        $p4 = $this->makeProduct(4); // no category/brand/type at all

        $buckets = $this->service->bucketByShared([$p1, $p2, $p3, $p4]);

        $idsOf = fn (array $bucket) => array_map(fn (Product $p) => $p->getId(), $bucket);

        $this->assertSame([1, 2], $idsOf($buckets['category'][10]));
        $this->assertSame([3], $idsOf($buckets['category'][20]));
        $this->assertArrayNotHasKey(4, array_flip($idsOf($buckets['category'][10] ?? [])));

        // All three categorized products share the same parent (1).
        $this->assertSame([1, 2, 3], $idsOf($buckets['parentCategory'][1]));

        $this->assertSame([1, 3], $idsOf($buckets['brand'][100]));
        $this->assertSame([2], $idsOf($buckets['brand'][200]));

        $this->assertSame([1, 3], $idsOf($buckets['type'][1000]));
        $this->assertSame([2], $idsOf($buckets['type'][2000]));

        // Product 4 has no category/brand/type, so it appears in no bucket.
        foreach (['category', 'parentCategory', 'brand', 'type'] as $key) {
            foreach ($buckets[$key] as $bucket) {
                $this->assertNotContains(4, $idsOf($bucket));
            }
        }
    }

    public function testBucketBySharedReturnsEmptyBucketsForEmptyProductList(): void
    {
        $buckets = $this->service->bucketByShared([]);

        $this->assertSame([], $buckets['category']);
        $this->assertSame([], $buckets['parentCategory']);
        $this->assertSame([], $buckets['brand']);
        $this->assertSame([], $buckets['type']);
    }

    // --- train() ---

    public function testTrainFallsBackToDefaultWeightsWhenTooFewPositivePairs(): void
    {
        // Only 3 distinct co-occurrence pairs — below MIN_POSITIVE_PAIRS_TO_TRAIN (8).
        $products = [];
        $groups = [];
        for ($i = 1; $i <= 3; ++$i) {
            $idA = $i * 10;
            $idB = $i * 10 + 1;
            $products[] = $this->makeProduct($idA);
            $products[] = $this->makeProduct($idB);
            $groups[] = [$idA => 1, $idB => 1];
        }

        $this->trainer->expects($this->never())->method('train');

        $this->service->train($groups, $products);

        $this->assertFalse($this->service->isTrained());
        $this->assertSame(0.0, $this->service->getConfidence());
        $this->assertSame(3.0, $this->service->getWeights()['category']); // still DEFAULT_WEIGHTS
    }

    public function testTrainFullyAdoptsLearnedWeightsAtFullConfidence(): void
    {
        // 120 distinct co-occurrence pairs => confidence caps at 1.0
        // (FULL_CONFIDENCE_PAIRS), so learned weights fully replace defaults.
        $products = [];
        for ($id = 1; $id <= 121; ++$id) {
            $products[] = $this->makeProduct($id);
        }
        $groups = [];
        for ($id = 1; $id <= 120; ++$id) {
            $groups[] = [$id => 1, $id + 1 => 1];
        }

        // [bias, category, parentCategory, brand, type, featureMatch] — a
        // negative weight (parentCategory) must be clamped to zero.
        $this->trainer->method('train')->willReturn([0.2, 6.0, -1.0, 3.0, 5.0, 8.0]);

        $this->service->train($groups, $products);

        $this->assertTrue($this->service->isTrained());
        $this->assertEqualsWithDelta(1.0, $this->service->getConfidence(), 0.0001);
        $this->assertSame(
            ['category' => 6.0, 'parentCategory' => 0.0, 'brand' => 3.0, 'type' => 5.0, 'featureMatch' => 8.0],
            $this->service->getWeights()
        );
    }

    public function testTrainAtExactMinimumThresholdIsTrainedButHasZeroConfidence(): void
    {
        // Exactly MIN_POSITIVE_PAIRS_TO_TRAIN (8) pairs — training runs, but
        // confidence is 0 at that boundary, so blended weights still equal
        // the defaults even though isTrained() is true.
        $products = [];
        for ($id = 1; $id <= 16; $id += 2) {
            $products[] = $this->makeProduct($id);
            $products[] = $this->makeProduct($id + 1);
        }
        $groups = [];
        for ($id = 1; $id <= 16; $id += 2) {
            $groups[] = [$id => 1, $id + 1 => 1];
        }
        $this->assertCount(8, $groups);

        $this->trainer->method('train')->willReturn([0.0, 6.0, 6.0, 6.0, 6.0, 6.0]);

        $this->service->train($groups, $products);

        $this->assertTrue($this->service->isTrained());
        $this->assertSame(0.0, $this->service->getConfidence());
        $this->assertSame(3.0, $this->service->getWeights()['category']); // unchanged from default
    }

    public function testTrainLeavesWeightsUnchangedWhenTrainerReturnsEmpty(): void
    {
        $products = [];
        for ($id = 1; $id <= 16; $id += 2) {
            $products[] = $this->makeProduct($id);
            $products[] = $this->makeProduct($id + 1);
        }
        $groups = [];
        for ($id = 1; $id <= 16; $id += 2) {
            $groups[] = [$id => 1, $id + 1 => 1];
        }

        $this->trainer->method('train')->willReturn([]);

        $this->service->train($groups, $products);

        $this->assertFalse($this->service->isTrained());
        $this->assertSame(self::defaultWeights(), $this->service->getWeights());
    }

    public function testTrainIgnoresGroupsWithOnlyOneProduct(): void
    {
        // A single-product "group" produces no pair at all.
        $products = [$this->makeProduct(1)];
        $groups = [[1 => 1]];

        $this->trainer->expects($this->never())->method('train');

        $this->service->train($groups, $products);

        $this->assertFalse($this->service->isTrained());
    }

    /** @return array{category: float, parentCategory: float, brand: float, type: float, featureMatch: float} */
    private static function defaultWeights(): array
    {
        return ['category' => 3.0, 'parentCategory' => 1.0, 'brand' => 1.5, 'type' => 2.0, 'featureMatch' => 4.0];
    }
}
