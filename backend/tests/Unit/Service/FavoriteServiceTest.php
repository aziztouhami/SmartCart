<?php

namespace App\Tests\Unit\Service;

use App\Entity\Favorite;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\FavoriteRepository;
use App\Repository\ProductRepository;
use App\Service\FavoriteService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class FavoriteServiceTest extends TestCase
{
    private FavoriteRepository $favoriteRepository;
    private ProductRepository $productRepository;
    private EntityManagerInterface $em;
    private FavoriteService $service;

    protected function setUp(): void
    {
        $this->favoriteRepository = $this->createMock(FavoriteRepository::class);
        $this->productRepository = $this->createMock(ProductRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);

        $this->service = new FavoriteService($this->favoriteRepository, $this->productRepository, $this->em);
    }

    public function testAddThrowsWhenProductNotFound(): void
    {
        $this->productRepository->method('find')->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Product not found');
        $this->expectExceptionCode(404);

        $this->service->add(new User(), 999);
    }

    public function testAddThrowsWhenAlreadyFavorited(): void
    {
        $product = new Product();
        $this->productRepository->method('find')->willReturn($product);
        $this->favoriteRepository->method('findByUserAndProduct')->willReturn(new Favorite());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Product is already in your favorites');
        $this->expectExceptionCode(409);

        $this->service->add(new User(), 1);
    }

    public function testAddPersistsNewFavorite(): void
    {
        $user = new User();
        $product = new Product();
        $this->productRepository->method('find')->willReturn($product);
        $this->favoriteRepository->method('findByUserAndProduct')->willReturn(null);

        $this->em->expects($this->once())->method('persist')->with($this->isInstanceOf(Favorite::class));
        $this->em->expects($this->once())->method('flush');

        $favorite = $this->service->add($user, 1);

        $this->assertSame($user, $favorite->getUser());
        $this->assertSame($product, $favorite->getProduct());
    }

    public function testRemoveThrowsWhenProductNotFound(): void
    {
        $this->productRepository->method('find')->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Product not found');
        $this->expectExceptionCode(404);

        $this->service->remove(new User(), 999);
    }

    public function testRemoveThrowsWhenNotInFavorites(): void
    {
        $product = new Product();
        $this->productRepository->method('find')->willReturn($product);
        $this->favoriteRepository->method('findByUserAndProduct')->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Product not in your favorites');
        $this->expectExceptionCode(404);

        $this->service->remove(new User(), 1);
    }

    public function testRemoveDeletesExistingFavorite(): void
    {
        $product = new Product();
        $favorite = new Favorite();
        $this->productRepository->method('find')->willReturn($product);
        $this->favoriteRepository->method('findByUserAndProduct')->willReturn($favorite);

        $this->em->expects($this->once())->method('remove')->with($favorite);
        $this->em->expects($this->once())->method('flush');

        $this->service->remove(new User(), 1);
    }
}
