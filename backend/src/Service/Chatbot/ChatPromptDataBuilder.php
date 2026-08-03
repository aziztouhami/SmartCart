<?php

namespace App\Service\Chatbot;

use App\Entity\Product;
use App\Entity\Promotion;
use App\Prompts\Chatbot\ShopAssistantPrompt;
use App\Repository\BrandRepository;
use App\Repository\CategoryRepository;
use App\Repository\PromotionRepository;
use App\Repository\ReviewRepository;

/**
 * Assembles the full chatbot prompt: catalogue overview (category tree +
 * brand list, always included) plus a full fact-sheet — price, stock,
 * description, features, active promotion, rating — for each grounding
 * product found by ChatProductFinder.
 */
class ChatPromptDataBuilder
{
    private const DESCRIPTION_MAX_LENGTH = 220;

    public function __construct(
        private CategoryRepository $categoryRepository,
        private BrandRepository $brandRepository,
        private PromotionRepository $promotionRepository,
        private ReviewRepository $reviewRepository,
        private ShopAssistantPrompt $shopAssistantPrompt,
        private string $siteName,
    ) {
    }

    /**
     * @param Product[]                                        $products
     * @param array<int, array{role: string, content: string}> $history
     */
    public function build(string $message, array $products, array $history): string
    {
        $productLines = [];
        if (!empty($products)) {
            $promoMap = $this->promotionRepository->findActiveForProducts($products);
            foreach ($products as $p) {
                $productLines[] = $this->describeProduct($p, $promoMap[$p->getId()] ?? null);
            }
        }

        return $this->shopAssistantPrompt->build(
            $this->siteName,
            $this->buildCatalogOverview(),
            $productLines,
            $history,
            $message,
        );
    }

    /**
     * Cheap, always-included context (category tree + brand list) so the
     * bot can answer general "what do you sell" questions even when no
     * specific product matched the message's keywords.
     */
    private function buildCatalogOverview(): string
    {
        $categoryLines = [];
        foreach ($this->categoryRepository->findRoots() as $root) {
            $childNames = array_map(fn ($c) => $c->getName(), $root->getChildren()->toArray());
            $categoryLines[] = $childNames
                ? $root->getName().' ('.implode(', ', $childNames).')'
                : $root->getName();
        }

        $brandNames = array_map(fn ($b) => $b->getName(), $this->brandRepository->findAll());

        return implode("\n", [
            '[APERÇU DU CATALOGUE]',
            'Catégories: '.(empty($categoryLines) ? 'N/A' : implode(' | ', $categoryLines)),
            'Marques: '.(empty($brandNames) ? 'N/A' : implode(', ', $brandNames)),
        ]);
    }

    /**
     * One full product fact-sheet line: price, stock, description,
     * type-specific features (with their proper name/unit, not the raw
     * slug), active promotion and customer rating — everything the bot
     * might be asked about for this product.
     */
    private function describeProduct(Product $p, ?Promotion $promotion): string
    {
        $parts = [];
        $parts[] = 'Nom: '.$p->getName();
        $parts[] = 'Prix: '.$p->getPrice().' TND';
        $parts[] = 'Stock: '.($p->getStock() > 0 ? $p->getStock().' unités disponibles' : 'rupture de stock');
        $parts[] = 'Catégorie: '.($p->getCategory()?->getName() ?? 'N/A');

        if ($p->getBrand()) {
            $parts[] = 'Marque: '.$p->getBrand()->getName();
        }

        if ($p->getProductType()) {
            $parts[] = 'Type: '.$p->getProductType()->getName();
        }

        if ($promotion) {
            $promo = $promotion->toPublicArray((float) $p->getPrice());
            $parts[] = sprintf(
                'Promotion en cours: -%s%% (prix promo: %s TND au lieu de %s TND)',
                $promo['percentage'],
                $promo['newPrice'],
                $promo['oldPrice']
            );
        }

        $avgRating = $this->reviewRepository->getAverageRating($p);
        if (null !== $avgRating) {
            $reviewCount = $this->reviewRepository->countByProduct($p);
            $parts[] = sprintf('Avis clients: %.1f/100 (%d avis)', $avgRating, $reviewCount);
        }

        $features = $this->describeFeatures($p);
        if ('' !== $features) {
            $parts[] = 'Caractéristiques: '.$features;
        }

        if ($p->getDescription()) {
            $description = mb_substr(trim($p->getDescription()), 0, self::DESCRIPTION_MAX_LENGTH);
            $parts[] = 'Description: '.$description;
        }

        return '- '.implode(' | ', $parts);
    }

    /**
     * The product's attributes JSON is keyed by slug (e.g. "battery-capacity"
     * => 5000) — not something the model (or a user) should have to decode, so
     * this maps each slug back to its ProductTypeAttribute's human name and
     * unit (e.g. "Capacité de la batterie: 5000 mAh").
     */
    private function describeFeatures(Product $p): string
    {
        $values = $p->getAttributes();
        if (empty($values) || !$p->getProductType()) {
            return '';
        }

        $defsBySlug = [];
        foreach ($p->getProductType()->getAttributes() as $def) {
            $defsBySlug[$def->getSlug()] = $def;
        }

        $described = [];
        foreach ($values as $slug => $value) {
            $def = $defsBySlug[$slug] ?? null;
            $label = $def ? $def->getName() : $slug;
            $unit = $def?->getUnit();
            $stringValue = is_bool($value) ? ($value ? 'oui' : 'non') : (string) $value;
            $described[] = $label.': '.$stringValue.($unit ? ' '.$unit : '');
        }

        return implode(', ', $described);
    }
}
