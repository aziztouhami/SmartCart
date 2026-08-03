<?php

namespace Database\Seeders;

use App\Entity\Category;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CategorySeeder extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Parent categories
        $electronics = $this->persist($manager, 'Electronics', 'electronics');
        $fashion = $this->persist($manager, 'Fashion', 'fashion');
        $homeGarden = $this->persist($manager, 'Home & Garden', 'home-garden');
        $beauty = $this->persist($manager, 'Beauty & Health', 'beauty-health');
        $sports = $this->persist($manager, 'Sports & Outdoors', 'sports-outdoors');
        $books = $this->persist($manager, 'Books', 'books');
        $gaming = $this->persist($manager, 'Gaming & Toys', 'gaming-toys');
        $automotive = $this->persist($manager, 'Automotive', 'automotive');
        $food = $this->persist($manager, 'Food & Beverages', 'food-beverages');
        $pets = $this->persist($manager, 'Pet Supplies', 'pet-supplies');

        // Electronics subcategories
        $this->persist($manager, 'Smartphones', 'smartphones', $electronics);
        $this->persist($manager, 'Laptops', 'laptops', $electronics);
        $this->persist($manager, 'Tablets', 'tablets', $electronics);
        $this->persist($manager, 'Headphones', 'headphones', $electronics);
        $this->persist($manager, 'Smart Watches', 'smart-watches', $electronics);

        // Fashion subcategories
        $this->persist($manager, "Men's Clothing", 'mens-clothing', $fashion);
        $this->persist($manager, "Women's Clothing", 'womens-clothing', $fashion);
        $this->persist($manager, 'Shoes', 'shoes', $fashion);
        $this->persist($manager, 'Watches', 'watches', $fashion);

        // Home & Garden subcategories
        $this->persist($manager, 'Furniture', 'furniture', $homeGarden);
        $this->persist($manager, 'Home Decor', 'home-decor', $homeGarden);
        $this->persist($manager, 'Kitchen Appliances', 'kitchen-appliances', $homeGarden);

        // Beauty & Health subcategories
        $this->persist($manager, 'Skincare', 'skincare', $beauty);
        $this->persist($manager, 'Makeup', 'makeup', $beauty);
        $this->persist($manager, 'Perfumes', 'perfumes', $beauty);

        // Sports & Outdoors subcategories
        $this->persist($manager, 'Fitness Equipment', 'fitness-equipment', $sports);
        $this->persist($manager, 'Football', 'football', $sports);
        $this->persist($manager, 'Cycling', 'cycling', $sports);

        // Books subcategories
        $this->persist($manager, 'Fiction', 'fiction', $books);
        $this->persist($manager, 'Educational', 'educational', $books);

        // Gaming & Toys subcategories
        $this->persist($manager, 'Video Games', 'video-games', $gaming);
        $this->persist($manager, 'Action Figures', 'action-figures', $gaming);

        // Automotive subcategories
        $this->persist($manager, 'Car Accessories', 'car-accessories', $automotive);
        $this->persist($manager, 'Car Electronics', 'car-electronics', $automotive);

        // Food & Beverages subcategories
        $this->persist($manager, 'Snacks', 'snacks', $food);
        $this->persist($manager, 'Soft Drinks', 'soft-drinks', $food);
        $this->persist($manager, 'Coffee', 'coffee', $food);
        $this->persist($manager, 'Tea', 'tea', $food);

        // Pet Supplies subcategories
        $this->persist($manager, 'Dog Supplies', 'dog-supplies', $pets);
        $this->persist($manager, 'Cat Supplies', 'cat-supplies', $pets);

        $manager->flush();
    }

    private function persist(ObjectManager $manager, string $name, string $slug, ?Category $parent = null): Category
    {
        $category = new Category();
        $category->setName($name);
        $category->setSlug($slug);
        $category->setParent($parent);

        $manager->persist($category);
        $this->addReference('category-'.$slug, $category);

        return $category;
    }
}
