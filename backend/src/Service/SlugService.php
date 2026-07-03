<?php

namespace App\Service;

use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Repository\ProductTypeRepository;

class SlugService
{
    public function __construct(
        private ProductRepository $productRepository,
        private CategoryRepository $categoryRepository,
        private ProductTypeRepository $productTypeRepository,
    ) {}

    public function generateProductSlug(string $name, ?int $excludeId = null): string
    {
        $base = $this->slugify($name);
        $slug = $base;
        $i = 1;

        while (true) {
            $existing = $this->productRepository->findBySlug($slug);
            if (!$existing || $existing->getId() === $excludeId) {
                return $slug;
            }
            $slug = $base . '-' . $i++;
        }
    }

    public function generateCategorySlug(string $name, ?int $excludeId = null): string
    {
        $base = $this->slugify($name);
        $slug = $base;
        $i = 1;

        while (true) {
            $existing = $this->categoryRepository->findBySlug($slug);
            if (!$existing || $existing->getId() === $excludeId) {
                return $slug;
            }
            $slug = $base . '-' . $i++;
        }
    }

    public function generateProductTypeSlug(string $name, ?int $excludeId = null): string
    {
        $base = $this->slugify($name);
        $slug = $base;
        $i = 1;

        while (true) {
            $existing = $this->productTypeRepository->findBySlug($slug);
            if (!$existing || $existing->getId() === $excludeId) {
                return $slug;
            }
            $slug = $base . '-' . $i++;
        }
    }

    /**
     * Slugify free text into a URL/key-safe token (e.g. an attribute name
     * into the key it's stored under in Product::$attributes).
     */
    public function slugify(string $text): string
    {
        $text = mb_strtolower(trim($text));
        // Replace spaces and special chars with hyphens
        $text = preg_replace('/[^\p{L}\p{N}]+/u', '-', $text);
        $text = preg_replace('/-+/', '-', $text);
        return trim($text, '-');
    }
}
