<?php

namespace App\Tests\Unit\Service\Chatbot;

use App\Entity\Category;
use App\Entity\Product;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Service\Chatbot\ChatProductFinder;
use App\Service\Chatbot\TranslationService;
use PHPUnit\Framework\TestCase;

class ChatProductFinderTest extends TestCase
{
    private ProductRepository $productRepository;
    private CategoryRepository $categoryRepository;
    private TranslationService $translationService;
    private ChatProductFinder $finder;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepository::class);
        $this->categoryRepository = $this->createMock(CategoryRepository::class);
        $this->translationService = $this->createMock(TranslationService::class);

        // By default, TranslationService is a no-op that returns the input unchanged
        // (mirrors its graceful-degrade behavior when the API is unavailable).
        $this->translationService->method('toEnglish')->willReturnArgument(0);

        $this->finder = new ChatProductFinder(
            $this->productRepository,
            $this->categoryRepository,
            $this->translationService,
        );
    }

    private function makeCategory(int $id, string $name): Category
    {
        $category = new Category();
        $ref = new \ReflectionProperty(Category::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($category, $id);
        $category->setName($name);

        return $category;
    }

    private function makeProduct(int $id): Product
    {
        $product = new Product();
        $ref = new \ReflectionProperty(Product::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($product, $id);

        return $product;
    }

    public function testReturnsEmptyArrayWhenMessageHasNoKeywords(): void
    {
        // "le" and "de" are stopwords, "a" is <=2 chars — nothing left to search for.
        $this->productRepository->expects($this->never())->method('findByAnyKeyword');
        $this->categoryRepository->expects($this->never())->method('findAll');

        $result = $this->finder->find('le de a', []);

        $this->assertSame([], $result);
    }

    public function testReturnsEmptyArrayForBlankMessage(): void
    {
        $result = $this->finder->find('   ', []);

        $this->assertSame([], $result);
    }

    public function testFindsProductsByKeywordSearch(): void
    {
        $products = [$this->makeProduct(1), $this->makeProduct(2)];

        $this->productRepository->expects($this->once())
            ->method('findByAnyKeyword')
            ->with($this->callback(fn ($kw) => in_array('iphone', $kw, true)), 6)
            ->willReturn($products);

        $result = $this->finder->find('iphone', []);

        $this->assertSame($products, $result);
    }

    public function testFiltersOutStopwordsAndShortWords(): void
    {
        $this->categoryRepository->method('findAll')->willReturn([]);
        $this->productRepository->expects($this->once())
            ->method('findByAnyKeyword')
            ->with($this->callback(function (array $kw) {
                // "avez" and "vous" are stopwords, "un" is a stopword, "des" is a stopword.
                return !in_array('avez', $kw, true)
                    && !in_array('vous', $kw, true)
                    && in_array('telephones', $kw, true);
            }), 6)
            ->willReturn([]);

        $this->finder->find('avez vous des telephones', []);
    }

    public function testCombinesLastThreeHistoryTurnsIntoSearchText(): void
    {
        $history = [
            ['role' => 'user', 'content' => 'turn one keyword'],
            ['role' => 'assistant', 'content' => 'turn two keyword'],
            ['role' => 'user', 'content' => 'turn three keyword'],
            ['role' => 'assistant', 'content' => 'turn four keyword'],
        ];

        $this->categoryRepository->method('findAll')->willReturn([]);
        $this->productRepository->expects($this->once())
            ->method('findByAnyKeyword')
            ->with($this->callback(function (array $kw) {
                // Only the last 3 turns should be included — "one" from turn 1 must be absent.
                return !in_array('one', $kw, true)
                    && in_array('two', $kw, true)
                    && in_array('three', $kw, true)
                    && in_array('four', $kw, true);
            }), 6)
            ->willReturn([]);

        $this->finder->find('follow-up question', $history);
    }

    public function testMergesTranslatedKeywordsWhenTranslationDiffersFromOriginal(): void
    {
        $this->translationService = $this->createMock(TranslationService::class);
        $this->translationService->method('toEnglish')->willReturn('phones');
        $this->finder = new ChatProductFinder(
            $this->productRepository,
            $this->categoryRepository,
            $this->translationService,
        );

        $this->categoryRepository->method('findAll')->willReturn([]);
        $this->productRepository->expects($this->once())
            ->method('findByAnyKeyword')
            ->with($this->callback(fn ($kw) => in_array('telephones', $kw, true) && in_array('phones', $kw, true)), 6)
            ->willReturn([]);

        $this->finder->find('telephones', []);
    }

    public function testDoesNotDuplicateKeywordsWhenTranslationEqualsOriginal(): void
    {
        // TranslationService returns the same text unchanged (its degrade-gracefully behavior).
        $this->translationService->method('toEnglish')->willReturnArgument(0);
        $this->categoryRepository->method('findAll')->willReturn([]);

        $this->productRepository->expects($this->once())
            ->method('findByAnyKeyword')
            ->with($this->callback(fn ($kw) => 1 === count(array_keys($kw, 'phones', true))), 6)
            ->willReturn([]);

        $this->finder->find('phones', []);
    }

    public function testFallsBackToContextualCategoryMatchWhenKeywordSearchFindsNothing(): void
    {
        $this->productRepository->method('findByAnyKeyword')->willReturn([]);

        $category = $this->makeCategory(10, 'Smartphones');
        $this->categoryRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$category]);

        $products = [$this->makeProduct(5)];
        $this->productRepository->expects($this->once())
            ->method('findByCategoryIds')
            ->with([10], 6)
            ->willReturn($products);

        $result = $this->finder->find('telephones', []);

        $this->assertSame($products, $result);
    }

    public function testContextualMatchViaSharedSuffix(): void
    {
        $this->productRepository->method('findByAnyKeyword')->willReturn([]);

        // "telephones" and "smartphones" share the suffix "hones" (well beyond 5 chars: "phones").
        $category = $this->makeCategory(10, 'Smartphones');
        $this->categoryRepository->method('findAll')->willReturn([$category]);

        $products = [$this->makeProduct(5)];
        $this->productRepository->expects($this->once())
            ->method('findByCategoryIds')
            ->with([10], 6)
            ->willReturn($products);

        $result = $this->finder->find('telephones', []);

        $this->assertSame($products, $result);
    }

    public function testContextualMatchReturnsEmptyWhenNoCategoryMatches(): void
    {
        $this->productRepository->method('findByAnyKeyword')->willReturn([]);

        $category = $this->makeCategory(10, 'Vetements');
        $this->categoryRepository->method('findAll')->willReturn([$category]);

        $this->productRepository->expects($this->never())->method('findByCategoryIds');

        $result = $this->finder->find('ordinateurs portables', []);

        $this->assertSame([], $result);
    }

    public function testDoesNotFallBackToContextualMatchWhenKeywordSearchAlreadyFoundProducts(): void
    {
        $products = [$this->makeProduct(1)];
        $this->productRepository->method('findByAnyKeyword')->willReturn($products);

        $this->categoryRepository->expects($this->never())->method('findAll');
        $this->productRepository->expects($this->never())->method('findByCategoryIds');

        $result = $this->finder->find('iphone', []);

        $this->assertSame($products, $result);
    }
}
