<?php

namespace Database\Seeders;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ProductSeeder extends Fixture implements DependentFixtureInterface
{
    private const IMAGES_BASE_URL = 'http://localhost:8000/uploads/products';

    private const BRAND_IMAGES_BASE_URL = 'http://localhost:8000/uploads/brands';

    private const BRAND_IMAGE_FILES = [
        'apple' => 'apple.png',
        'samsung' => 'samsung.png',
        'xiaomi' => 'xiaomi.png',
        'lenovo' => 'lenovo.png',
        'hp' => 'hp.png',
        'asus' => 'asus.jpg',
        'sony' => 'sony.png',
        'levis' => 'levi-s.png',
        'nike' => 'nike.jpg',
        'mango' => 'mango.png',
        'adidas' => 'adidas.png',
        'casio' => 'casio.jpg',
        'seiko' => 'seiko.png',
        'ikea' => 'ikea.png',
        'philips' => 'philips.png',
        'moulinex' => 'moulinex.png',
        'cerave' => 'cerave.png',
        'maybelline' => 'maybelline.jpg',
        'dior' => 'dior.png',
        'rockrider' => 'rockrider.webp',
        'ea-sports' => 'ea-sports.png',
        'marvel' => 'marvel.png',
        'michelin' => 'michelin.jpg',
        'pioneer' => 'pioneer.png',
        'pringles' => 'pringles.png',
        'lavazza' => 'lavazza.png',
        'lipton' => 'lipton.png',
        'pedigree' => 'pedigree.png',
        'whiskas' => 'whiskas.png',
    ];

    // Number of image files available per product slug, named "{slug}-1.{ext}", "{slug}-2.{ext}", ...
    // in public/uploads/products/. A slug absent from this map has no seeded image.
    private const IMAGE_EXTENSIONS = [
        '5-sports-automatic' => ['png'],
        '501-original-jeans' => ['jpg', 'jpg'],
        'adjustable-dumbbell-set-20kg' => ['jpg'],
        'adult-cat-food-1-9kg' => ['avif', 'avif', 'avif'],
        'adult-dog-food-3kg' => ['png'],
        'air-force-1' => ['jpg'],
        'air-fryer-essential-xl' => ['webp'],
        'airpods-pro-2nd-gen' => ['avif'],
        'al-rihla-football' => ['avif', 'avif', 'avif', 'avif'],
        'blendforce-blender' => ['webp'],
        'car-floor-mats-set' => ['webp', 'jpg'],
        'clean-code' => ['webp'],
        'decorative-wall-mirror' => ['png'],
        'edifice-efv-100d' => ['avif'],
        'fc-26-ps5' => ['webp'],
        'fit-me-foundation' => ['avif'],
        'galaxy-s24-128gb' => ['avif'],
        'galaxy-watch-7' => ['avif'],
        'grand-court-2-0' => ['avif', 'jpg'],
        'ideapad-slim-3' => ['webp', 'png', 'png'],
        'iphone-15-128gb' => ['jpg'],
        'linnmon-desk' => ['avif'],
        'malm-bed-frame' => ['avif'],
        'moisturizing-cream-340g' => ['jpg'],
        'original-crisps-165g' => ['webp'],
        'pavilion-15' => ['avif', 'avif', 'avif'],
        'qualita-rossa-ground-coffee-250g' => ['png', 'webp'],
        'redmi-note-13-pro' => ['jpg'],
        'sauvage-eau-de-toilette-100ml' => ['jpg', 'webp'],
        'spider-man-figure-30cm' => ['jpg', 'webp'],
        'sportswear-club-t-shirt' => ['webp'],
        'st100-mountain-bike' => ['jpeg', 'jpeg'],
        'the-alchemist' => ['webp', 'jpg'],
        'vivobook-15' => ['webp', 'webp', 'webp', 'webp', 'webp', 'webp'],
        'watch-series-9' => ['jpg'],
        'wh-1000xm5' => ['webp'],
        'yellow-label-tea-100-bags' => ['webp'],
    ];

    private const BRAND_DESCRIPTIONS = [
        'apple' => 'American technology company known for the iPhone, Mac, and a design-first approach to consumer electronics.',
        'samsung' => 'South Korean electronics giant making smartphones, TVs, and home appliances for markets worldwide.',
        'xiaomi' => 'Chinese consumer electronics brand offering high-spec smartphones and gadgets at competitive prices.',
        'lenovo' => 'Global computer manufacturer recognized for reliable laptops built for work and everyday use.',
        'hp' => 'Long-standing American computer and printer maker offering laptops for home, business, and gaming.',
        'asus' => 'Taiwanese electronics company known for performance-driven laptops and computer hardware.',
        'sony' => 'Japanese electronics and entertainment company renowned for premium audio and imaging technology.',
        'levis' => 'Iconic American denim brand and inventor of the blue jean, in production since 1853.',
        'nike' => 'Global sportswear leader supplying footwear, apparel, and equipment for athletes everywhere.',
        'mango' => 'Spanish fashion brand offering contemporary, Mediterranean-inspired clothing for women and men.',
        'adidas' => 'German sportswear company delivering performance footwear and apparel across sports and street style.',
        'casio' => 'Japanese electronics maker famous for durable, affordable watches and calculators.',
        'seiko' => 'Japanese watchmaker with a century-long legacy of precision and innovative timekeeping.',
        'ikea' => 'Swedish furniture retailer known for affordable, functional, flat-pack home furnishings.',
        'philips' => 'Dutch electronics company producing dependable home appliances and personal care devices.',
        'moulinex' => 'French kitchen appliance brand trusted for blenders, mixers, and everyday cooking tools.',
        'cerave' => 'Dermatologist-developed skincare brand formulated with ceramides to restore the skin barrier.',
        'maybelline' => 'American cosmetics brand offering accessible, trend-driven makeup for every look.',
        'dior' => 'French luxury house celebrated for haute couture fashion and iconic fragrances.',
        'rockrider' => "Decathlon's in-house mountain bike brand, built for trail riders of every level.",
        'ea-sports' => 'Video game label specializing in realistic, officially licensed sports simulations.',
        'marvel' => 'American entertainment brand behind iconic superheroes and collectible action figures.',
        'michelin' => 'French tyre manufacturer also producing durable automotive accessories and travel guides.',
        'pioneer' => 'Japanese electronics brand specializing in car audio and multimedia systems.',
        'pringles' => 'American snack brand known for its distinctively stackable, canned potato crisps.',
        'lavazza' => 'Italian coffee roaster crafting rich espresso and ground coffee blends since 1895.',
        'lipton' => 'World-renowned tea brand offering classic black teas and refreshing blends.',
        'pedigree' => 'Trusted pet food brand formulating complete, balanced nutrition for dogs.',
        'whiskas' => 'Popular pet food brand providing balanced, tasty nutrition for cats of every age.',
    ];

    public function load(ObjectManager $manager): void
    {
        // ── Brands ────────────────────────────────────────────────────────────
        $brandNames = [
            'apple' => 'Apple',
            'samsung' => 'Samsung',
            'xiaomi' => 'Xiaomi',
            'lenovo' => 'Lenovo',
            'hp' => 'HP',
            'asus' => 'ASUS',
            'sony' => 'Sony',
            'levis' => "Levi's",
            'nike' => 'Nike',
            'mango' => 'Mango',
            'adidas' => 'Adidas',
            'casio' => 'Casio',
            'seiko' => 'Seiko',
            'ikea' => 'IKEA',
            'philips' => 'Philips',
            'moulinex' => 'Moulinex',
            'cerave' => 'CeraVe',
            'maybelline' => 'Maybelline',
            'dior' => 'Dior',
            'rockrider' => 'Rockrider',
            'ea-sports' => 'EA Sports',
            'marvel' => 'Marvel',
            'michelin' => 'Michelin',
            'pioneer' => 'Pioneer',
            'pringles' => 'Pringles',
            'lavazza' => 'Lavazza',
            'lipton' => 'Lipton',
            'pedigree' => 'Pedigree',
            'whiskas' => 'Whiskas',
        ];

        $brands = [];
        foreach ($brandNames as $key => $name) {
            $brand = new Brand();
            $brand->setName($name);
            $brand->setImage(self::BRAND_IMAGES_BASE_URL.'/'.self::BRAND_IMAGE_FILES[$key]);
            $brand->setDescription(self::BRAND_DESCRIPTIONS[$key] ?? null);
            $brand->setJoinedAt(new \DateTimeImmutable());
            $manager->persist($brand);
            $brands[$key] = $brand;
        }

        // ── Products ──────────────────────────────────────────────────────────
        // [name, category-slug, price, stock, description, brand-key|null, product-type-key|null, attributes]
        $products = [
            // Smartphones
            ['iPhone 15 128GB',                  'smartphones',        '3399.00', 15,  '6.1-inch Super Retina XDR display, A16 Bionic chip, dual-camera system, 128GB storage.',   'apple',
                'smartphone', ['color' => 'Black', 'storage' => '128GB', 'ram' => '6GB', 'battery-capacity' => 3349, 'screen-size' => 6.1, '5g' => true]],
            ['Galaxy S24 128GB',                 'smartphones',        '2899.00', 20,  '6.2-inch AMOLED display, Exynos processor, triple-camera setup, 128GB storage.',            'samsung',
                'smartphone', ['color' => 'Onyx Black', 'storage' => '128GB', 'ram' => '8GB', 'battery-capacity' => 4000, 'screen-size' => 6.2, '5g' => true]],
            ['Redmi Note 13 Pro',                'smartphones',        '1199.00', 30,  '200MP camera, AMOLED display, 8GB RAM, 256GB storage.',                                     'xiaomi',
                'smartphone', ['color' => 'Midnight Black', 'storage' => '256GB', 'ram' => '8GB', 'battery-capacity' => 5100, 'screen-size' => 6.67, '5g' => false]],
            // Laptops
            ['IdeaPad Slim 3',                   'laptops',            '1799.00', 12,  '15.6-inch Full HD display, Intel Core i5, 8GB RAM, 512GB SSD.',                             'lenovo',
                'laptop', ['color' => 'Abyss Blue', 'processor' => 'Intel Core i5', 'ram' => '8GB', 'storage' => '512GB SSD', 'screen-size' => 15.6, 'weight' => 1.6]],
            ['Pavilion 15',                      'laptops',            '2599.00', 8,   'Intel Core i7 processor, 16GB RAM, 512GB SSD, Full HD display.',                            'hp',
                'laptop', ['color' => 'Natural Silver', 'processor' => 'Intel Core i7', 'ram' => '16GB', 'storage' => '512GB SSD', 'screen-size' => 15.6, 'weight' => 1.75]],
            ['Vivobook 15',                      'laptops',            '1899.00', 14,  'Ryzen 5 processor, 8GB RAM, 512GB SSD, Windows 11.',                                        'asus',
                'laptop', ['color' => 'Quiet Blue', 'processor' => 'AMD Ryzen 5', 'ram' => '8GB', 'storage' => '512GB SSD', 'screen-size' => 15.6, 'weight' => 1.7]],
            // Headphones
            ['AirPods Pro 2nd Gen',              'headphones',         '999.00',  25,  'Wireless earbuds with active noise cancellation and spatial audio.',                        'apple',
                'headphones', ['color' => 'White', 'wireless' => true, 'noise-cancelling' => true, 'battery-life' => 6]],
            ['WH-1000XM5',                       'headphones',         '1399.00', 10,  'Premium wireless noise-canceling over-ear headphones.',                                     'sony',
                'headphones', ['color' => 'Black', 'wireless' => true, 'noise-cancelling' => true, 'battery-life' => 30]],
            // Smart Watches
            ['Watch Series 9',                   'smart-watches',      '1799.00', 10,  'GPS smartwatch with health tracking and fitness monitoring.',                                'apple',
                'smart-watch', ['color' => 'Midnight', 'case-size' => '45mm', 'connectivity' => 'GPS', 'water-resistant' => true]],
            ['Galaxy Watch 7',                   'smart-watches',      '999.00',  18,  'AMOLED display, health sensors, GPS and fitness tracking.',                                 'samsung',
                'smart-watch', ['color' => 'Graphite', 'case-size' => '44mm', 'connectivity' => 'GPS', 'water-resistant' => true]],
            // Men's Clothing
            ['501 Original Jeans',               'mens-clothing',      '249.00',  40,  'Classic straight-fit denim jeans made from durable cotton.',                                'levis',
                'clothing', ['color' => 'Indigo Blue', 'size' => 'L', 'material' => '100% Cotton Denim', 'fit' => 'Regular']],
            ['Sportswear Club T-Shirt',          'mens-clothing',      '89.00',   60,  'Soft cotton T-shirt suitable for casual daily wear.',                                       'nike',
                'clothing', ['color' => 'Black', 'size' => 'M', 'material' => '100% Cotton', 'fit' => 'Regular']],
            // Shoes
            ['Air Force 1',                      'shoes',              '399.00',  25,  'Iconic low-top sneakers with leather upper and cushioned sole.',                            'nike',
                'shoes', ['color' => 'White', 'size' => '42', 'material' => 'Leather']],
            ['Grand Court 2.0',                  'shoes',              '249.00',  30,  'Tennis-inspired sneakers for everyday comfort.',                                            'adidas',
                'shoes', ['color' => 'White/Black', 'size' => '43', 'material' => 'Synthetic leather']],
            // Watches
            ['Edifice EFV-100D',                 'watches',            '329.00',  20,  'Stainless steel analog watch with water resistance.',                                       'casio',
                'watch', ['color' => 'Silver', 'movement' => 'Quartz', 'water-resistance' => '100m', 'material' => 'Stainless steel']],
            ['5 Sports Automatic',               'watches',            '999.00',  8,   'Automatic mechanical watch with durable design.',                                           'seiko',
                'watch', ['color' => 'Black', 'movement' => 'Automatic', 'water-resistance' => '100m', 'material' => 'Stainless steel']],
            // Furniture
            ['MALM Bed Frame',                   'furniture',          '899.00',  10,  'Minimalist queen-size bed frame with sturdy construction.',                                 'ikea',
                'furniture', ['color' => 'White', 'material' => 'Particleboard', 'dimensions' => '160x200cm (Queen)', 'assembly-required' => true]],
            ['LINNMON Desk',                     'furniture',          '399.00',  15,  'Simple office desk suitable for home and workspaces.',                                      'ikea',
                'furniture', ['color' => 'White', 'material' => 'Particleboard', 'dimensions' => '100x60cm', 'assembly-required' => true]],
            // Home Decor
            ['Decorative Wall Mirror',           'home-decor',         '149.00',  20,  'Modern round mirror for living rooms and bedrooms.',                                        null,
                'home-decor', ['color' => 'Gold', 'material' => 'Metal frame, glass', 'shape' => 'Round']],
            // Kitchen Appliances
            ['Air Fryer Essential XL',           'kitchen-appliances', '699.00',  18,  'Healthy cooking with rapid air technology and large capacity.',                             'philips',
                'kitchen-appliance', ['color' => 'Black', 'power' => 1500, 'capacity' => '6.2L', 'warranty' => '2 years']],
            ['Blendforce Blender',               'kitchen-appliances', '179.00',  25,  'Powerful blender for smoothies, soups, and sauces.',                                       'moulinex',
                'kitchen-appliance', ['color' => 'Black', 'power' => 600, 'capacity' => '1.5L', 'warranty' => '1 year']],
            // Skincare
            ['Moisturizing Cream 340g',          'skincare',           '69.00',   50,  'Hydrating cream with ceramides for dry skin.',                                              'cerave',
                'skincare', ['skin-type' => 'Dry', 'volume' => '340g', 'key-ingredient' => 'Ceramides']],
            // Makeup
            ['Fit Me Foundation',                'makeup',             '39.00',   60,  'Lightweight liquid foundation with natural finish.',                                        'maybelline',
                'makeup', ['shade' => 'Natural Beige', 'finish' => 'Natural', 'volume' => '30ml']],
            // Perfumes
            ['Sauvage Eau de Toilette 100ml',    'perfumes',           '499.00',  15,  'Fresh and woody fragrance for men.',                                                        'dior',
                'perfume', ['fragrance-family' => 'Woody', 'volume' => '100ml', 'concentration' => 'EDT']],
            // Fitness Equipment
            ['Adjustable Dumbbell Set 20kg',     'fitness-equipment',  '299.00',  12,  'Home workout dumbbells with adjustable weights.',                                           null,
                'fitness-equipment', ['weight' => 20, 'material' => 'Cast iron with rubber coating', 'adjustable' => true]],
            // Football
            ['Al Rihla Football',                'football',           '129.00',  25,  'Match-quality football inspired by international tournaments.',                             'adidas',
                'sports-ball', ['size' => '5', 'material' => 'Polyurethane', 'official-size' => true]],
            // Cycling
            ['ST100 Mountain Bike',              'cycling',            '1199.00', 8,   'Entry-level mountain bike with durable frame.',                                             'rockrider',
                'bicycle', ['frame-size' => 'M', 'wheel-size' => '29 inch', 'gear-count' => 21, 'brake-type' => 'Disc']],
            // Fiction
            ['The Alchemist',                    'fiction',            '39.00',   40,  'Best-selling novel by Paulo Coelho about personal destiny.',                                null,
                'book', ['author' => 'Paulo Coelho', 'pages' => 208, 'language' => 'English', 'format' => 'Paperback']],
            // Educational
            ['Clean Code',                       'educational',        '79.00',   25,  'Software development best practices by Robert C. Martin.',                                 null,
                'book', ['author' => 'Robert C. Martin', 'pages' => 464, 'language' => 'English', 'format' => 'Paperback']],
            // Video Games
            ['FC 26 PS5',                        'video-games',        '249.00',  20,  'Latest football simulation game for PlayStation 5.',                                       'ea-sports',
                'video-game', ['platform' => 'PS5', 'genre' => 'Sports', 'multiplayer' => true]],
            // Action Figures
            ['Spider-Man Figure 30cm',           'action-figures',     '89.00',   35,  'Detailed collectible action figure.',                                                       'marvel',
                'action-figure', ['character' => 'Spider-Man', 'height' => '30cm', 'material' => 'PVC']],
            // Car Accessories
            ['Car Floor Mats Set',               'car-accessories',    '99.00',   20,  'Durable all-weather floor mats for most vehicles.',                                        'michelin',
                'car-accessory', ['compatibility' => 'Universal fit', 'material' => 'Rubber', 'color' => 'Black']],
            // Snacks
            ['Original Crisps 165g',             'snacks',             '12.50',   100, 'Crispy potato chips in original flavor.',                                                  'pringles',
                'snack', ['flavor' => 'Original', 'weight' => '165g', 'dietary' => 'Regular']],
            // Coffee
            ['Qualita Rossa Ground Coffee 250g', 'coffee',             '18.90',   80,  'Ground coffee blend with rich aroma and balanced flavor.',                                'lavazza',
                'beverage', ['format' => 'Ground', 'weight' => '250g', 'caffeine' => true]],
            // Tea
            ['Yellow Label Tea 100 Bags',        'tea',                '14.50',   70,  'Classic black tea blend.',                                                                 'lipton',
                'beverage', ['format' => 'Tea Bags', 'weight' => '100 bags', 'caffeine' => true]],
            // Dog Supplies
            ['Adult Dog Food 3kg',               'dog-supplies',       '45.00',   40,  'Complete nutrition for adult dogs.',                                                       'pedigree',
                'pet-food', ['pet-type' => 'Dog', 'weight' => '3kg', 'life-stage' => 'Adult']],
            // Cat Supplies
            ['Adult Cat Food 1.9kg',             'cat-supplies',       '28.00',   50,  'Balanced nutrition for adult cats.',                                                       'whiskas',
                'pet-food', ['pet-type' => 'Cat', 'weight' => '1.9kg', 'life-stage' => 'Adult']],
        ];

        foreach ($products as [$name, $categorySlug, $price, $stock, $description, $brandKey, $typeKey, $attributes]) {
            $slug = $this->slugify($name);

            $product = new Product();
            $product->setName($name);
            $product->setSlug($slug);
            $product->setPrice($price);
            $product->setStock($stock);
            $product->setDescription($description);
            $product->setCategory($this->getReference('category-'.$categorySlug, Category::class));

            $images = [];
            foreach (self::IMAGE_EXTENSIONS[$slug] ?? [] as $i => $ext) {
                $images[] = self::IMAGES_BASE_URL.'/'.$slug.'-'.($i + 1).'.'.$ext;
            }
            $product->setImages($images);
            if (null !== $brandKey) {
                $product->setBrand($brands[$brandKey]);
            }
            if (null !== $typeKey) {
                $product->setProductType($this->getReference('product-type-'.$typeKey, ProductType::class));
                $product->setAttributes($attributes);
            }

            $manager->persist($product);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [CategorySeeder::class, ProductTypeSeeder::class];
    }

    private function slugify(string $text): string
    {
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);

        return trim($text, '-');
    }
}
