<?php

namespace App\Tests\Unit\Service\Chatbot;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductType;
use App\Entity\ProductTypeAttribute;
use App\Entity\Promotion;
use App\Prompts\Chatbot\ShopAssistantPrompt;
use App\Repository\BrandRepository;
use App\Repository\CategoryRepository;
use App\Repository\PromotionRepository;
use App\Repository\ReviewRepository;
use App\Service\Chatbot\ChatPromptDataBuilder;
use PHPUnit\Framework\TestCase;

class ChatPromptDataBuilderTest extends TestCase
{
    private CategoryRepository $categoryRepository;
    private BrandRepository $brandRepository;
    private PromotionRepository $promotionRepository;
    private ReviewRepository $reviewRepository;
    private ShopAssistantPrompt $shopAssistantPrompt;
    private ChatPromptDataBuilder $builder;

    protected function setUp(): void
    {
        $this->categoryRepository = $this->createMock(CategoryRepository::class);
        $this->brandRepository = $this->createMock(BrandRepository::class);
        $this->promotionRepository = $this->createMock(PromotionRepository::class);
        $this->reviewRepository = $this->createMock(ReviewRepository::class);
        $this->shopAssistantPrompt = $this->createMock(ShopAssistantPrompt::class);

        $this->categoryRepository->method('findRoots')->willReturn([]);
        $this->brandRepository->method('findAll')->willReturn([]);
        $this->promotionRepository->method('findActiveForProducts')->willReturn([]);
        $this->reviewRepository->method('getAverageRating')->willReturn(null);

        $this->builder = new ChatPromptDataBuilder(
            $this->categoryRepository,
            $this->brandRepository,
            $this->promotionRepository,
            $this->reviewRepository,
            $this->shopAssistantPrompt,
            'SmartCart',
        );
    }

    private function makeProduct(int $id): Product
    {
        $product = new Product();
        $ref = new \ReflectionProperty(Product::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($product, $id);

        return $product;
    }

    private function makeCategory(int $id, string $name, ?Category $parent = null): Category
    {
        $category = new Category();
        $ref = new \ReflectionProperty(Category::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($category, $id);
        $category->setName($name);
        if ($parent) {
            $parent->addChild($category);
        }

        return $category;
    }

    private function makeBrand(int $id, string $name): Brand
    {
        $brand = new Brand();
        $ref = new \ReflectionProperty(Brand::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($brand, $id);
        $brand->setName($name);

        return $brand;
    }

    private function makePromotion(int $percentage): Promotion
    {
        return (new Promotion())
            ->setType(Promotion::TYPE_PRODUCT)
            ->setDiscountType(Promotion::DISCOUNT_PERCENTAGE)
            ->setPercentage((string) $percentage)
            ->setStartDate(new \DateTimeImmutable('-1 day'));
    }

    public function testBuildDelegatesToShopAssistantPromptWithCatalogOverview(): void
    {
        $root = $this->makeCategory(1, 'Électronique');
        $this->makeCategory(2, 'Smartphones', $root);
        $this->makeCategory(3, 'Ordinateurs', $root);
        $this->categoryRepository = $this->createMock(CategoryRepository::class);
        $this->categoryRepository->method('findRoots')->willReturn([$root]);

        $brand = $this->makeBrand(1, 'Apple');
        $this->brandRepository = $this->createMock(BrandRepository::class);
        $this->brandRepository->method('findAll')->willReturn([$brand]);

        $builder = new ChatPromptDataBuilder(
            $this->categoryRepository,
            $this->brandRepository,
            $this->promotionRepository,
            $this->reviewRepository,
            $this->shopAssistantPrompt,
            'SmartCart',
        );

        $this->shopAssistantPrompt->expects($this->once())
            ->method('build')
            ->with(
                'SmartCart',
                $this->callback(function (string $overview) {
                    return str_contains($overview, '[APERÇU DU CATALOGUE]')
                        && str_contains($overview, 'Électronique (Smartphones, Ordinateurs)')
                        && str_contains($overview, 'Apple');
                }),
                [],
                [],
                'hello'
            )
            ->willReturn('FINAL PROMPT');

        $result = $builder->build('hello', [], []);

        $this->assertSame('FINAL PROMPT', $result);
    }

    public function testCatalogOverviewUsesNaWhenNoCategoriesOrBrands(): void
    {
        $this->shopAssistantPrompt->expects($this->once())
            ->method('build')
            ->with(
                $this->anything(),
                $this->callback(fn (string $overview) => str_contains($overview, 'Catégories: N/A') && str_contains($overview, 'Marques: N/A')),
                $this->anything(),
                $this->anything(),
                $this->anything()
            )
            ->willReturn('FINAL PROMPT');

        $this->builder->build('hello', [], []);
    }

    public function testBuildPassesEmptyProductLinesWhenNoProductsMatched(): void
    {
        $this->shopAssistantPrompt->expects($this->once())
            ->method('build')
            ->with($this->anything(), $this->anything(), [], $this->anything(), $this->anything())
            ->willReturn('FINAL PROMPT');

        $this->builder->build('hello', [], []);
    }

    public function testBuildNeverQueriesPromotionsWhenNoProductsMatched(): void
    {
        $this->promotionRepository->expects($this->never())->method('findActiveForProducts');

        $this->builder->build('hello', [], []);
    }

    public function testDescribeProductIncludesCoreFields(): void
    {
        $product = $this->makeProduct(1);
        $product->setName('iPhone 15');
        $product->setPrice('2999.00');
        $product->setStock(5);
        $category = $this->makeCategory(1, 'Smartphones');
        $product->setCategory($category);

        $line = null;
        $this->shopAssistantPrompt->method('build')
            ->willReturnCallback(function (...$args) use (&$line) {
                $line = $args[2][0];

                return 'FINAL';
            });

        $this->builder->build('hello', [$product], []);

        $this->assertStringContainsString('Nom: iPhone 15', $line);
        $this->assertStringContainsString('Prix: 2999.00 TND', $line);
        $this->assertStringContainsString('Stock: 5 unités disponibles', $line);
        $this->assertStringContainsString('Catégorie: Smartphones', $line);
    }

    public function testDescribeProductShowsOutOfStockWhenStockIsZero(): void
    {
        $product = $this->makeProduct(1);
        $product->setName('Sold Out Item');
        $product->setPrice('10.00');
        $product->setStock(0);
        $product->setCategory($this->makeCategory(1, 'Misc'));

        $line = null;
        $this->shopAssistantPrompt->method('build')
            ->willReturnCallback(function (...$args) use (&$line) {
                $line = $args[2][0];

                return 'FINAL';
            });

        $this->builder->build('hello', [$product], []);

        $this->assertStringContainsString('rupture de stock', $line);
    }

    public function testDescribeProductOmitsBrandAndTypeWhenAbsent(): void
    {
        $product = $this->makeProduct(1);
        $product->setName('No Brand Item');
        $product->setPrice('10.00');
        $product->setStock(1);
        $product->setCategory($this->makeCategory(1, 'Misc'));

        $line = null;
        $this->shopAssistantPrompt->method('build')
            ->willReturnCallback(function (...$args) use (&$line) {
                $line = $args[2][0];

                return 'FINAL';
            });

        $this->builder->build('hello', [$product], []);

        $this->assertStringNotContainsString('Marque:', $line);
        $this->assertStringNotContainsString('Type:', $line);
    }

    public function testDescribeProductIncludesBrandAndCategoryFallbackToNaWhenMissing(): void
    {
        $product = $this->makeProduct(1);
        $product->setName('No Category Item');
        $product->setPrice('10.00');
        $product->setStock(1);
        $product->setBrand($this->makeBrand(1, 'Samsung'));

        $line = null;
        $this->shopAssistantPrompt->method('build')
            ->willReturnCallback(function (...$args) use (&$line) {
                $line = $args[2][0];

                return 'FINAL';
            });

        $this->builder->build('hello', [$product], []);

        $this->assertStringContainsString('Catégorie: N/A', $line);
        $this->assertStringContainsString('Marque: Samsung', $line);
    }

    public function testDescribeProductIncludesActivePromotion(): void
    {
        $product = $this->makeProduct(1);
        $product->setName('Discounted Item');
        $product->setPrice('100.00');
        $product->setStock(1);
        $product->setCategory($this->makeCategory(1, 'Misc'));

        $promotion = $this->makePromotion(20);
        $this->promotionRepository = $this->createMock(PromotionRepository::class);
        $this->promotionRepository->method('findActiveForProducts')->willReturn([1 => $promotion]);

        $builder = new ChatPromptDataBuilder(
            $this->categoryRepository,
            $this->brandRepository,
            $this->promotionRepository,
            $this->reviewRepository,
            $this->shopAssistantPrompt,
            'SmartCart',
        );

        $line = null;
        $this->shopAssistantPrompt->method('build')
            ->willReturnCallback(function (...$args) use (&$line) {
                $line = $args[2][0];

                return 'FINAL';
            });

        $builder->build('hello', [$product], []);

        $this->assertStringContainsString('Promotion en cours: -20', $line);
        $this->assertStringContainsString('80', $line); // discounted price
    }

    public function testDescribeProductIncludesRatingWhenReviewsExist(): void
    {
        $product = $this->makeProduct(1);
        $product->setName('Reviewed Item');
        $product->setPrice('10.00');
        $product->setStock(1);
        $product->setCategory($this->makeCategory(1, 'Misc'));

        $this->reviewRepository = $this->createMock(ReviewRepository::class);
        $this->reviewRepository->method('getAverageRating')->willReturn(85.5);
        $this->reviewRepository->method('countByProduct')->willReturn(12);

        $builder = new ChatPromptDataBuilder(
            $this->categoryRepository,
            $this->brandRepository,
            $this->promotionRepository,
            $this->reviewRepository,
            $this->shopAssistantPrompt,
            'SmartCart',
        );

        $line = null;
        $this->shopAssistantPrompt->method('build')
            ->willReturnCallback(function (...$args) use (&$line) {
                $line = $args[2][0];

                return 'FINAL';
            });

        $builder->build('hello', [$product], []);

        $this->assertStringContainsString('Avis clients: 85.5/100 (12 avis)', $line);
    }

    public function testDescribeProductOmitsRatingWhenNoReviews(): void
    {
        $product = $this->makeProduct(1);
        $product->setName('Unreviewed Item');
        $product->setPrice('10.00');
        $product->setStock(1);
        $product->setCategory($this->makeCategory(1, 'Misc'));

        $line = null;
        $this->shopAssistantPrompt->method('build')
            ->willReturnCallback(function (...$args) use (&$line) {
                $line = $args[2][0];

                return 'FINAL';
            });

        $this->builder->build('hello', [$product], []);

        $this->assertStringNotContainsString('Avis clients', $line);
    }

    public function testDescribeProductMapsAttributeSlugsToHumanNamesWithUnits(): void
    {
        $productType = new ProductType();
        $typeRef = new \ReflectionProperty(ProductType::class, 'id');
        $typeRef->setAccessible(true);
        $typeRef->setValue($productType, 1);
        $productType->setName('Smartphone');
        $productType->setSlug('smartphone');

        $attrDef = new ProductTypeAttribute();
        $attrRef = new \ReflectionProperty(ProductTypeAttribute::class, 'id');
        $attrRef->setAccessible(true);
        $attrRef->setValue($attrDef, 1);
        $attrDef->setName('Capacité de la batterie');
        $attrDef->setSlug('battery-capacity');
        $attrDef->setDataType('number');
        $attrDef->setUnit('mAh');
        $productType->addAttribute($attrDef);

        $product = $this->makeProduct(1);
        $product->setName('Battery Phone');
        $product->setPrice('10.00');
        $product->setStock(1);
        $product->setCategory($this->makeCategory(1, 'Misc'));
        $product->setProductType($productType);
        $product->setAttributes(['battery-capacity' => 5000]);

        $line = null;
        $this->shopAssistantPrompt->method('build')
            ->willReturnCallback(function (...$args) use (&$line) {
                $line = $args[2][0];

                return 'FINAL';
            });

        $this->builder->build('hello', [$product], []);

        $this->assertStringContainsString('Caractéristiques: Capacité de la batterie: 5000 mAh', $line);
        $this->assertStringContainsString('Type: Smartphone', $line);
    }

    public function testDescribeProductRendersBooleanAttributesAsOuiNon(): void
    {
        $productType = new ProductType();
        $typeRef = new \ReflectionProperty(ProductType::class, 'id');
        $typeRef->setAccessible(true);
        $typeRef->setValue($productType, 1);
        $productType->setName('Accessoire');
        $productType->setSlug('accessoire');

        $attrDef = new ProductTypeAttribute();
        $attrRef = new \ReflectionProperty(ProductTypeAttribute::class, 'id');
        $attrRef->setAccessible(true);
        $attrRef->setValue($attrDef, 1);
        $attrDef->setName('Étanche');
        $attrDef->setSlug('waterproof');
        $attrDef->setDataType('boolean');
        $productType->addAttribute($attrDef);

        $product = $this->makeProduct(1);
        $product->setName('Waterproof Item');
        $product->setPrice('10.00');
        $product->setStock(1);
        $product->setCategory($this->makeCategory(1, 'Misc'));
        $product->setProductType($productType);
        $product->setAttributes(['waterproof' => true]);

        $line = null;
        $this->shopAssistantPrompt->method('build')
            ->willReturnCallback(function (...$args) use (&$line) {
                $line = $args[2][0];

                return 'FINAL';
            });

        $this->builder->build('hello', [$product], []);

        $this->assertStringContainsString('Étanche: oui', $line);
    }

    public function testDescribeProductTruncatesLongDescription(): void
    {
        $product = $this->makeProduct(1);
        $product->setName('Long Description Item');
        $product->setPrice('10.00');
        $product->setStock(1);
        $product->setCategory($this->makeCategory(1, 'Misc'));
        $product->setDescription(str_repeat('a', 300));

        $line = null;
        $this->shopAssistantPrompt->method('build')
            ->willReturnCallback(function (...$args) use (&$line) {
                $line = $args[2][0];

                return 'FINAL';
            });

        $this->builder->build('hello', [$product], []);

        // Extract the description portion of the line and check its length is capped at 220.
        $this->assertMatchesRegularExpression('/Description: (a{220})(?!a)/', $line);
    }

    public function testBuildPassesHistoryAndMessageThrough(): void
    {
        $history = [['role' => 'user', 'content' => 'earlier']];

        $this->shopAssistantPrompt->expects($this->once())
            ->method('build')
            ->with('SmartCart', $this->anything(), [], $history, 'my question')
            ->willReturn('FINAL');

        $result = $this->builder->build('my question', [], $history);

        $this->assertSame('FINAL', $result);
    }
}
