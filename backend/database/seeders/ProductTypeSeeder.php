<?php

namespace Database\Seeders;

use App\Entity\ProductType;
use App\Entity\ProductTypeAttribute;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Seeds the product types (Smartphone, Laptop, ...) and the per-type feature
 * definitions ProductSeeder fills in on the matching products, so the
 * type/feature system added for the recommender is visible in seeded data.
 */
class ProductTypeSeeder extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $types = [
            'smartphone' => [
                'name' => 'Smartphone',
                'attributes' => [
                    ['name' => 'Color', 'dataType' => 'text', 'required' => true],
                    ['name' => 'Storage', 'dataType' => 'select', 'options' => ['64GB', '128GB', '256GB', '512GB'], 'required' => true],
                    ['name' => 'RAM', 'dataType' => 'select', 'options' => ['4GB', '6GB', '8GB', '12GB']],
                    ['name' => 'Battery Capacity', 'dataType' => 'number', 'unit' => 'mAh'],
                    ['name' => 'Screen Size', 'dataType' => 'number', 'unit' => 'inch'],
                    ['name' => '5G', 'dataType' => 'boolean'],
                ],
            ],
            'laptop' => [
                'name' => 'Laptop',
                'attributes' => [
                    ['name' => 'Color', 'dataType' => 'text'],
                    ['name' => 'Processor', 'dataType' => 'text', 'required' => true],
                    ['name' => 'RAM', 'dataType' => 'select', 'options' => ['8GB', '16GB', '32GB']],
                    ['name' => 'Storage', 'dataType' => 'select', 'options' => ['256GB SSD', '512GB SSD', '1TB SSD']],
                    ['name' => 'Screen Size', 'dataType' => 'number', 'unit' => 'inch'],
                    ['name' => 'Weight', 'dataType' => 'number', 'unit' => 'kg'],
                ],
            ],
            'headphones' => [
                'name' => 'Headphones',
                'attributes' => [
                    ['name' => 'Color', 'dataType' => 'text'],
                    ['name' => 'Wireless', 'dataType' => 'boolean'],
                    ['name' => 'Noise Cancelling', 'dataType' => 'boolean'],
                    ['name' => 'Battery Life', 'dataType' => 'number', 'unit' => 'hours'],
                ],
            ],
            'smart-watch' => [
                'name' => 'Smart Watch',
                'attributes' => [
                    ['name' => 'Color', 'dataType' => 'text'],
                    ['name' => 'Case Size', 'dataType' => 'select', 'options' => ['40mm', '41mm', '44mm', '45mm']],
                    ['name' => 'Connectivity', 'dataType' => 'select', 'options' => ['GPS', 'GPS + Cellular']],
                    ['name' => 'Water Resistant', 'dataType' => 'boolean'],
                ],
            ],
            'shoes' => [
                'name' => 'Shoes',
                'attributes' => [
                    ['name' => 'Color', 'dataType' => 'text', 'required' => true],
                    ['name' => 'Size', 'dataType' => 'select', 'options' => ['39', '40', '41', '42', '43', '44', '45', '46']],
                    ['name' => 'Material', 'dataType' => 'text'],
                ],
            ],
            'watch' => [
                'name' => 'Watch',
                'attributes' => [
                    ['name' => 'Color', 'dataType' => 'text'],
                    ['name' => 'Movement', 'dataType' => 'select', 'options' => ['Quartz', 'Automatic']],
                    ['name' => 'Water Resistance', 'dataType' => 'text'],
                    ['name' => 'Material', 'dataType' => 'text'],
                ],
            ],
            'clothing' => [
                'name' => 'Clothing',
                'attributes' => [
                    ['name' => 'Color', 'dataType' => 'text', 'required' => true],
                    ['name' => 'Size', 'dataType' => 'select', 'options' => ['XS', 'S', 'M', 'L', 'XL', 'XXL'], 'required' => true],
                    ['name' => 'Material', 'dataType' => 'text'],
                    ['name' => 'Fit', 'dataType' => 'select', 'options' => ['Slim', 'Regular', 'Loose']],
                ],
            ],
            'furniture' => [
                'name' => 'Furniture',
                'attributes' => [
                    ['name' => 'Color', 'dataType' => 'text'],
                    ['name' => 'Material', 'dataType' => 'text'],
                    ['name' => 'Dimensions', 'dataType' => 'text'],
                    ['name' => 'Assembly Required', 'dataType' => 'boolean'],
                ],
            ],
            'home-decor' => [
                'name' => 'Home Decor',
                'attributes' => [
                    ['name' => 'Color', 'dataType' => 'text'],
                    ['name' => 'Material', 'dataType' => 'text'],
                    ['name' => 'Shape', 'dataType' => 'select', 'options' => ['Round', 'Square', 'Rectangular', 'Oval']],
                ],
            ],
            'kitchen-appliance' => [
                'name' => 'Kitchen Appliance',
                'attributes' => [
                    ['name' => 'Color', 'dataType' => 'text'],
                    ['name' => 'Power', 'dataType' => 'number', 'unit' => 'W'],
                    ['name' => 'Capacity', 'dataType' => 'text'],
                    ['name' => 'Warranty', 'dataType' => 'text'],
                ],
            ],
            'skincare' => [
                'name' => 'Skincare',
                'attributes' => [
                    ['name' => 'Skin Type', 'dataType' => 'select', 'options' => ['Dry', 'Oily', 'Combination', 'All Skin Types']],
                    ['name' => 'Volume', 'dataType' => 'text'],
                    ['name' => 'Key Ingredient', 'dataType' => 'text'],
                ],
            ],
            'makeup' => [
                'name' => 'Makeup',
                'attributes' => [
                    ['name' => 'Shade', 'dataType' => 'text'],
                    ['name' => 'Finish', 'dataType' => 'select', 'options' => ['Matte', 'Dewy', 'Natural']],
                    ['name' => 'Volume', 'dataType' => 'text'],
                ],
            ],
            'perfume' => [
                'name' => 'Perfume',
                'attributes' => [
                    ['name' => 'Fragrance Family', 'dataType' => 'select', 'options' => ['Woody', 'Floral', 'Fresh', 'Oriental', 'Citrus']],
                    ['name' => 'Volume', 'dataType' => 'text'],
                    ['name' => 'Concentration', 'dataType' => 'select', 'options' => ['EDT', 'EDP', 'Parfum']],
                ],
            ],
            'fitness-equipment' => [
                'name' => 'Fitness Equipment',
                'attributes' => [
                    ['name' => 'Weight', 'dataType' => 'number', 'unit' => 'kg'],
                    ['name' => 'Material', 'dataType' => 'text'],
                    ['name' => 'Adjustable', 'dataType' => 'boolean'],
                ],
            ],
            'sports-ball' => [
                'name' => 'Sports Ball',
                'attributes' => [
                    ['name' => 'Size', 'dataType' => 'select', 'options' => ['3', '4', '5']],
                    ['name' => 'Material', 'dataType' => 'text'],
                    ['name' => 'Official Size', 'dataType' => 'boolean'],
                ],
            ],
            'bicycle' => [
                'name' => 'Bicycle',
                'attributes' => [
                    ['name' => 'Frame Size', 'dataType' => 'select', 'options' => ['S', 'M', 'L', 'XL']],
                    ['name' => 'Wheel Size', 'dataType' => 'text'],
                    ['name' => 'Gear Count', 'dataType' => 'number'],
                    ['name' => 'Brake Type', 'dataType' => 'select', 'options' => ['Disc', 'Rim']],
                ],
            ],
            'book' => [
                'name' => 'Book',
                'attributes' => [
                    ['name' => 'Author', 'dataType' => 'text', 'required' => true],
                    ['name' => 'Pages', 'dataType' => 'number'],
                    ['name' => 'Language', 'dataType' => 'text'],
                    ['name' => 'Format', 'dataType' => 'select', 'options' => ['Paperback', 'Hardcover']],
                ],
            ],
            'video-game' => [
                'name' => 'Video Game',
                'attributes' => [
                    ['name' => 'Platform', 'dataType' => 'select', 'options' => ['PS5', 'Xbox Series X', 'PC', 'Switch']],
                    ['name' => 'Genre', 'dataType' => 'text'],
                    ['name' => 'Multiplayer', 'dataType' => 'boolean'],
                ],
            ],
            'action-figure' => [
                'name' => 'Action Figure',
                'attributes' => [
                    ['name' => 'Character', 'dataType' => 'text'],
                    ['name' => 'Height', 'dataType' => 'text'],
                    ['name' => 'Material', 'dataType' => 'text'],
                ],
            ],
            'car-accessory' => [
                'name' => 'Car Accessory',
                'attributes' => [
                    ['name' => 'Compatibility', 'dataType' => 'text'],
                    ['name' => 'Material', 'dataType' => 'text'],
                    ['name' => 'Color', 'dataType' => 'text'],
                ],
            ],
            'snack' => [
                'name' => 'Snack',
                'attributes' => [
                    ['name' => 'Flavor', 'dataType' => 'text'],
                    ['name' => 'Weight', 'dataType' => 'text'],
                    ['name' => 'Dietary', 'dataType' => 'select', 'options' => ['Regular', 'Vegan', 'Gluten-Free']],
                ],
            ],
            'beverage' => [
                'name' => 'Beverage',
                'attributes' => [
                    ['name' => 'Format', 'dataType' => 'select', 'options' => ['Ground', 'Whole Beans', 'Tea Bags', 'Loose Leaf']],
                    ['name' => 'Weight', 'dataType' => 'text'],
                    ['name' => 'Caffeine', 'dataType' => 'boolean'],
                ],
            ],
            'pet-food' => [
                'name' => 'Pet Food',
                'attributes' => [
                    ['name' => 'Pet Type', 'dataType' => 'select', 'options' => ['Dog', 'Cat']],
                    ['name' => 'Weight', 'dataType' => 'text'],
                    ['name' => 'Life Stage', 'dataType' => 'select', 'options' => ['Puppy/Kitten', 'Adult', 'Senior']],
                ],
            ],
        ];

        foreach ($types as $key => $definition) {
            $type = new ProductType();
            $type->setName($definition['name']);
            $type->setSlug($this->slugify($definition['name']));
            $manager->persist($type);

            foreach ($definition['attributes'] as $attrDef) {
                $attribute = new ProductTypeAttribute();
                $attribute->setName($attrDef['name']);
                $attribute->setSlug($this->slugify($attrDef['name']));
                $attribute->setDataType($attrDef['dataType']);
                $attribute->setUnit($attrDef['unit'] ?? null);
                $attribute->setOptions($attrDef['options'] ?? null);
                $attribute->setRequired($attrDef['required'] ?? false);
                $type->addAttribute($attribute);
                $manager->persist($attribute);
            }

            $this->addReference('product-type-'.$key, $type);
        }

        $manager->flush();
    }

    private function slugify(string $text): string
    {
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);

        return trim($text, '-');
    }
}
