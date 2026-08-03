<?php

namespace App\Service;

use App\DTO\Promotion\CreatePromotionRequest;
use App\Entity\Promotion;
use App\Repository\BrandRepository;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class PromotionService
{
    public function __construct(
        private ProductRepository $productRepository,
        private BrandRepository $brandRepository,
        private UserRepository $userRepository,
        private EntityManagerInterface $em,
        private MailService $mailService,
    ) {
    }

    public function create(CreatePromotionRequest $dto): Promotion
    {
        if (!in_array($dto->discountType, [Promotion::DISCOUNT_PERCENTAGE, Promotion::DISCOUNT_FIXED], true)) {
            throw new \RuntimeException('Invalid discount type', 400);
        }

        if (Promotion::TYPE_PRODUCT !== $dto->type && Promotion::DISCOUNT_FIXED === $dto->discountType) {
            throw new \RuntimeException('A fixed price can only be set for a single-product promotion. Brand and store-wide promotions must use a percentage.', 400);
        }

        if (Promotion::DISCOUNT_PERCENTAGE === $dto->discountType && null === $dto->percentage) {
            throw new \RuntimeException('Percentage is required', 400);
        }
        if (Promotion::DISCOUNT_FIXED === $dto->discountType && null === $dto->fixedPrice) {
            throw new \RuntimeException('Fixed price is required', 400);
        }

        $promotion = new Promotion();
        $promotion->setType($dto->type);
        $promotion->setDiscountType($dto->discountType);
        $promotion->setPercentage(Promotion::DISCOUNT_PERCENTAGE === $dto->discountType ? (string) $dto->percentage : null);

        if (Promotion::TYPE_PRODUCT === $dto->type) {
            if (!$dto->productId) {
                throw new \RuntimeException('productId is required for a product promotion', 400);
            }
            $product = $this->productRepository->find($dto->productId);
            if (!$product) {
                throw new \RuntimeException('Product not found', 404);
            }
            if (Promotion::DISCOUNT_FIXED === $dto->discountType && $dto->fixedPrice >= (float) $product->getPrice()) {
                throw new \RuntimeException('Fixed promotional price must be lower than the current price', 400);
            }
            $promotion->setProduct($product);
            $promotion->setFixedPrice(Promotion::DISCOUNT_FIXED === $dto->discountType ? (string) $dto->fixedPrice : null);
        } elseif (Promotion::TYPE_BRAND === $dto->type) {
            if (!$dto->brandId) {
                throw new \RuntimeException('brandId is required for a brand promotion', 400);
            }
            $brand = $this->brandRepository->find($dto->brandId);
            if (!$brand) {
                throw new \RuntimeException('Brand not found', 404);
            }
            $promotion->setBrand($brand);
        }
        // TYPE_ALL needs neither product nor brand.

        try {
            $startDate = new \DateTimeImmutable($dto->startDate);
        } catch (\Exception) {
            throw new \RuntimeException('Invalid start date', 400);
        }
        $promotion->setStartDate($startDate);

        if (null !== $dto->endDate && '' !== $dto->endDate) {
            try {
                $endDate = new \DateTimeImmutable($dto->endDate);
            } catch (\Exception) {
                throw new \RuntimeException('Invalid end date', 400);
            }
            if ($endDate < $startDate) {
                throw new \RuntimeException('End date must be after the start date', 400);
            }
            $promotion->setEndDate($endDate);
        }

        $this->em->persist($promotion);
        $this->em->flush();

        $this->notifyOptedInUsers($promotion);

        return $promotion;
    }

    private function notifyOptedInUsers(Promotion $promotion): void
    {
        foreach ($this->userRepository->findMarketingOptIn() as $user) {
            // One bad email must not block the rest of the batch or the promotion creation.
            $this->mailService->sendSafely(fn () => $this->mailService->sendPromotionEmail($user, $promotion));
        }
    }

    public function endNow(Promotion $promotion): Promotion
    {
        $promotion->setEndDate(new \DateTimeImmutable());
        $this->em->flush();

        return $promotion;
    }

    public function delete(Promotion $promotion): void
    {
        $this->em->remove($promotion);
        $this->em->flush();
    }
}
