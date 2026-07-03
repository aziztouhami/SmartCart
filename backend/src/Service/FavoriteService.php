<?php

namespace App\Service;

use App\Entity\Favorite;
use App\Entity\User;
use App\Repository\FavoriteRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;

class FavoriteService
{
    public function __construct(
        private FavoriteRepository $favoriteRepository,
        private ProductRepository $productRepository,
        private EntityManagerInterface $em,
    ) {}

    public function add(User $user, int $productId): Favorite
    {
        $product = $this->productRepository->find($productId);
        if (!$product) {
            throw new \RuntimeException('Product not found', 404);
        }

        if ($this->favoriteRepository->findByUserAndProduct($user, $product)) {
            throw new \RuntimeException('Product is already in your favorites', 409);
        }

        $favorite = new Favorite();
        $favorite->setUser($user);
        $favorite->setProduct($product);

        $this->em->persist($favorite);
        $this->em->flush();

        return $favorite;
    }

    public function remove(User $user, int $productId): void
    {
        $product = $this->productRepository->find($productId);
        if (!$product) {
            throw new \RuntimeException('Product not found', 404);
        }

        $favorite = $this->favoriteRepository->findByUserAndProduct($user, $product);
        if (!$favorite) {
            throw new \RuntimeException('Product not in your favorites', 404);
        }

        $this->em->remove($favorite);
        $this->em->flush();
    }
}
