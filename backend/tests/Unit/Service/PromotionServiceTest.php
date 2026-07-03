<?php

namespace App\Tests\Unit\Service;

use App\DTO\Promotion\CreatePromotionRequest;
use App\Entity\Brand;
use App\Entity\Product;
use App\Entity\Promotion;
use App\Entity\User;
use App\Repository\BrandRepository;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use App\Service\MailService;
use App\Service\PromotionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class PromotionServiceTest extends TestCase
{
    private ProductRepository $productRepository;
    private BrandRepository $brandRepository;
    private UserRepository $userRepository;
    private EntityManagerInterface $em;
    private MailService $mailService;
    private PromotionService $service;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepository::class);
        $this->brandRepository = $this->createMock(BrandRepository::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->mailService = $this->createMock(MailService::class);

        $this->service = new PromotionService(
            $this->productRepository,
            $this->brandRepository,
            $this->userRepository,
            $this->em,
            $this->mailService,
        );
    }

    private function baseDto(): CreatePromotionRequest
    {
        $dto = new CreatePromotionRequest();
        $dto->type = Promotion::TYPE_PRODUCT;
        $dto->discountType = Promotion::DISCOUNT_PERCENTAGE;
        $dto->percentage = 10.0;
        $dto->startDate = '2026-01-01';
        $dto->productId = 1;

        return $dto;
    }

    public function testCreateThrowsOnInvalidDiscountType(): void
    {
        $dto = $this->baseDto();
        $dto->discountType = 'bogus';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid discount type');

        $this->service->create($dto);
    }

    public function testCreateThrowsWhenFixedPriceUsedForBrandPromotion(): void
    {
        $dto = $this->baseDto();
        $dto->type = Promotion::TYPE_BRAND;
        $dto->discountType = Promotion::DISCOUNT_FIXED;
        $dto->fixedPrice = 5.0;
        $dto->brandId = 1;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('A fixed price can only be set for a single-product promotion. Brand and store-wide promotions must use a percentage.');

        $this->service->create($dto);
    }

    public function testCreateThrowsWhenPercentageMissing(): void
    {
        $dto = $this->baseDto();
        $dto->percentage = null;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Percentage is required');

        $this->service->create($dto);
    }

    public function testCreateThrowsWhenFixedPriceMissing(): void
    {
        $dto = $this->baseDto();
        $dto->discountType = Promotion::DISCOUNT_FIXED;
        $dto->percentage = null;
        $dto->fixedPrice = null;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Fixed price is required');

        $this->service->create($dto);
    }

    public function testCreateThrowsWhenProductIdMissingForProductPromotion(): void
    {
        $dto = $this->baseDto();
        $dto->productId = null;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('productId is required for a product promotion');

        $this->service->create($dto);
    }

    public function testCreateThrowsWhenProductNotFound(): void
    {
        $this->productRepository->method('find')->willReturn(null);

        $dto = $this->baseDto();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Product not found');

        $this->service->create($dto);
    }

    public function testCreateThrowsWhenFixedPriceNotLowerThanCurrentPrice(): void
    {
        $product = new Product();
        $product->setPrice('20.00');
        $this->productRepository->method('find')->willReturn($product);

        $dto = $this->baseDto();
        $dto->discountType = Promotion::DISCOUNT_FIXED;
        $dto->percentage = null;
        $dto->fixedPrice = 25.0;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Fixed promotional price must be lower than the current price');

        $this->service->create($dto);
    }

    public function testCreateThrowsWhenBrandIdMissingForBrandPromotion(): void
    {
        $dto = $this->baseDto();
        $dto->type = Promotion::TYPE_BRAND;
        $dto->productId = null;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('brandId is required for a brand promotion');

        $this->service->create($dto);
    }

    public function testCreateThrowsWhenBrandNotFound(): void
    {
        $this->brandRepository->method('find')->willReturn(null);

        $dto = $this->baseDto();
        $dto->type = Promotion::TYPE_BRAND;
        $dto->productId = null;
        $dto->brandId = 1;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Brand not found');

        $this->service->create($dto);
    }

    public function testCreateThrowsWhenStartDateInvalid(): void
    {
        $product = new Product();
        $product->setPrice('20.00');
        $this->productRepository->method('find')->willReturn($product);

        $dto = $this->baseDto();
        $dto->startDate = 'not-a-date';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid start date');

        $this->service->create($dto);
    }

    public function testCreateThrowsWhenEndDateBeforeStartDate(): void
    {
        $product = new Product();
        $product->setPrice('20.00');
        $this->productRepository->method('find')->willReturn($product);

        $dto = $this->baseDto();
        $dto->startDate = '2026-01-10';
        $dto->endDate = '2026-01-01';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('End date must be after the start date');

        $this->service->create($dto);
    }

    public function testCreateSucceedsForProductPercentagePromotion(): void
    {
        $product = new Product();
        $product->setPrice('20.00');
        $this->productRepository->method('find')->willReturn($product);

        $dto = $this->baseDto();

        $promotion = $this->service->create($dto);

        $this->assertSame(Promotion::TYPE_PRODUCT, $promotion->getType());
        $this->assertSame('10', $promotion->getPercentage());
        $this->assertSame($product, $promotion->getProduct());
    }

    public function testCreateSucceedsForAllStorePromotionWithoutProductOrBrand(): void
    {
        $dto = $this->baseDto();
        $dto->type = Promotion::TYPE_ALL;
        $dto->productId = null;

        $promotion = $this->service->create($dto);

        $this->assertSame(Promotion::TYPE_ALL, $promotion->getType());
        $this->assertNull($promotion->getProduct());
        $this->assertNull($promotion->getBrand());
    }

    public function testCreateNotifiesOptedInUsersAndToleratesMailFailure(): void
    {
        $product = new Product();
        $product->setPrice('20.00');
        $this->productRepository->method('find')->willReturn($product);

        $user = new User();
        $this->userRepository->method('findMarketingOptIn')->willReturn([$user]);
        $this->mailService->expects($this->once())->method('sendPromotionEmail')
            ->willThrowException(new \RuntimeException('SMTP down'));

        $dto = $this->baseDto();

        $promotion = $this->service->create($dto);

        $this->assertInstanceOf(Promotion::class, $promotion);
    }

    public function testEndNowSetsEndDate(): void
    {
        $promotion = new Promotion();

        $result = $this->service->endNow($promotion);

        $this->assertNotNull($result->getEndDate());
    }

    public function testDeleteRemovesPromotion(): void
    {
        $promotion = new Promotion();

        $this->em->expects($this->once())->method('remove')->with($promotion);
        $this->em->expects($this->once())->method('flush');

        $this->service->delete($promotion);
    }
}
