<?php

namespace App\Service\Chatbot;

use App\Entity\ChatMessageLog;
use App\Entity\Product;
use App\Entity\Promotion;
use App\Repository\BrandRepository;
use App\Repository\CategoryRepository;
use App\Repository\ChatMessageLogRepository;
use App\Repository\ProductRepository;
use App\Repository\PromotionRepository;
use App\Repository\ReviewRepository;
use App\Service\Ai\GroqClientService;
use App\Service\Ai\Prompt\ShopAssistantPrompt;

/**
 * Orchestrates one chat turn: logs the message (also the rate-limit
 * counter), finds grounding products, builds the full prompt — catalogue
 * overview + full product detail (price, stock, description, features,
 * active promotion, rating) — calls the LLM (Llama 3.3 70B via Groq), logs
 * and returns the reply.
 */
class ChatbotService
{
    private const MAX_PRODUCTS_IN_PROMPT = 6;
    private const DESCRIPTION_MAX_LENGTH = 220;
    private const RATE_LIMIT_WINDOW_SECONDS = 60;
    private const RATE_LIMIT_MAX_MESSAGES = 12;
    private const FALLBACK_REPLY = "Désolé, je n'arrive pas à répondre pour le moment. Merci de réessayer dans un instant.";

    /** Filler words that would otherwise dilute the keyword search into matching nothing or everything. */
    private const STOPWORDS = [
        'le', 'la', 'les', 'un', 'une', 'des', 'de', 'du', 'et', 'ou', 'est', 'es', 'pour', 'avec',
        'dans', 'sur', 'ce', 'cette', 'ces', 'vous', 'avez', 'as', 'a', 'je', 'tu', 'il', 'elle',
        'nous', 'votre', 'vos', 'mon', 'ma', 'mes', 'que', 'qui', 'quoi', 'comment', 'bonjour', 'salut',
        'the', 'a', 'an', 'is', 'are', 'do', 'you', 'have', 'this', 'that', 'for', 'with', 'and', 'or',
    ];

    public function __construct(
        private GroqClientService $aiClient,
        private TranslationService $translationService,
        private ShopAssistantPrompt $shopAssistantPrompt,
        private ProductRepository $productRepository,
        private CategoryRepository $categoryRepository,
        private BrandRepository $brandRepository,
        private PromotionRepository $promotionRepository,
        private ReviewRepository $reviewRepository,
        private ChatMessageLogRepository $chatMessageLogRepository,
        private string $siteName,
    ) {}

    public function isRateLimited(string $sessionId): bool
    {
        $since = new \DateTimeImmutable('-' . self::RATE_LIMIT_WINDOW_SECONDS . ' seconds');
        return $this->chatMessageLogRepository->countUserMessagesSince($sessionId, $since) >= self::RATE_LIMIT_MAX_MESSAGES;
    }

    /**
     * @param array<int, array{role: string, content: string}> $history
     */
    public function reply(string $sessionId, string $message, array $history): string
    {
        $this->log($sessionId, 'user', $message);

        $products = $this->findRelevantProducts($message, $history);
        $prompt = $this->buildPrompt($message, $products, $history);

        $reply = $this->aiClient->generate($prompt) ?? self::FALLBACK_REPLY;

        $this->log($sessionId, 'assistant', $reply);

        return $reply;
    }

    private function log(string $sessionId, string $role, string $message): void
    {
        $entry = (new ChatMessageLog())
            ->setSessionId($sessionId)
            ->setRole($role)
            ->setMessage($message);
        $this->chatMessageLogRepository->save($entry, true);
    }

    /**
     * @param array<int, array{role: string, content: string}> $history
     * @return Product[]
     */
    private function findRelevantProducts(string $message, array $history = []): array
    {
        // Combine the current message with the last 3 conversation turns so that
        // follow-up questions ("c'est quoi leur prix", "et en rouge ?") can still
        // find the products discussed earlier — this works for ALL product types
        // without any hardcoded list.
        $textToSearch = $message;
        foreach (array_slice($history, -3) as $turn) {
            $textToSearch .= ' ' . ($turn['content'] ?? '');
        }

        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($textToSearch)) ?: [];
        $keywords = array_values(array_unique(array_filter(
            $words,
            fn ($w) => mb_strlen($w) > 2 && !in_array($w, self::STOPWORDS, true)
        )));

        if (empty($keywords)) {
            return [];
        }

        // If the user wrote in French (or any non-English language), translate the
        // current message to English and extract additional keywords from the translation.
        // This lets "avez-vous des téléphones ?" find products named "iPhone 15" or
        // in a category called "Smartphones" without any hardcoded vocabulary list.
        // TranslationService returns the original text unchanged when the DeepL key
        // is not configured or the API call fails, so the chatbot always keeps working.
        $translatedMessage = $this->translationService->toEnglish($message);
        if ($translatedMessage !== $message) {
            $translatedWords = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($translatedMessage)) ?: [];
            $translatedKeywords = array_filter(
                $translatedWords,
                fn ($w) => mb_strlen($w) > 2 && !in_array($w, self::STOPWORDS, true)
            );
            $keywords = array_values(array_unique(array_merge($keywords, $translatedKeywords)));
        }

        // Pass 1: exact substring search across product name, description, category, brand.
        $products = $this->productRepository->findByAnyKeyword($keywords, self::MAX_PRODUCTS_IN_PROMPT);

        // Pass 2: if nothing matched, compare user keywords against the actual category
        // names in the DB using bidirectional containment and shared-suffix matching.
        // This catches semantic near-matches (e.g. "telephones" ↔ "Smartphones" share
        // the suffix "phones") without requiring any hardcoded synonym dictionary.
        if (empty($products)) {
            $products = $this->findProductsByContextualMatch($keywords);
        }

        return $products;
    }

    /**
     * @param string[] $keywords
     * @return Product[]
     */
    private function findProductsByContextualMatch(array $keywords): array
    {
        $matchedIds = [];

        foreach ($this->categoryRepository->findAll() as $category) {
            $catLower = mb_strtolower($category->getName());
            foreach ($keywords as $kw) {
                if (str_contains($catLower, $kw)              // "smartphones" contains "smartphone"
                    || str_contains($kw, $catLower)           // "tablette" contains "tablet"
                    || $this->shareCommonSuffix($kw, $catLower, 5) // "telephones"/"smartphones" → "hones"
                ) {
                    $matchedIds[] = $category->getId();
                    break;
                }
            }
        }

        if (empty($matchedIds)) {
            return [];
        }

        return $this->productRepository->findByCategoryIds(
            array_unique($matchedIds),
            self::MAX_PRODUCTS_IN_PROMPT
        );
    }

    /**
     * Returns true when $a and $b share a common trailing substring of at least
     * $minLength characters. Used to detect morphological near-matches between
     * user vocabulary and DB category names without any hardcoded synonym list.
     *
     * Example: shareCommonSuffix("telephones", "smartphones", 5) → true ("hones")
     */
    private function shareCommonSuffix(string $a, string $b, int $minLength): bool
    {
        $max = min(mb_strlen($a), mb_strlen($b));
        for ($len = $max; $len >= $minLength; $len--) {
            if (mb_substr($a, -$len) === mb_substr($b, -$len)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param Product[] $products
     * @param array<int, array{role: string, content: string}> $history
     */
    private function buildPrompt(string $message, array $products, array $history): string
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
                ? $root->getName() . ' (' . implode(', ', $childNames) . ')'
                : $root->getName();
        }

        $brandNames = array_map(fn ($b) => $b->getName(), $this->brandRepository->findAll());

        return implode("\n", [
            '[APERÇU DU CATALOGUE]',
            'Catégories: ' . (empty($categoryLines) ? 'N/A' : implode(' | ', $categoryLines)),
            'Marques: ' . (empty($brandNames) ? 'N/A' : implode(', ', $brandNames)),
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
        $parts[] = 'Nom: ' . $p->getName();
        $parts[] = 'Prix: ' . $p->getPrice() . ' TND';
        $parts[] = 'Stock: ' . ($p->getStock() > 0 ? $p->getStock() . ' unités disponibles' : 'rupture de stock');
        $parts[] = 'Catégorie: ' . ($p->getCategory()?->getName() ?? 'N/A');

        if ($p->getBrand()) {
            $parts[] = 'Marque: ' . $p->getBrand()->getName();
        }

        if ($p->getProductType()) {
            $parts[] = 'Type: ' . $p->getProductType()->getName();
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
        if ($avgRating !== null) {
            $reviewCount = $this->reviewRepository->countByProduct($p);
            $parts[] = sprintf('Avis clients: %.1f/100 (%d avis)', $avgRating, $reviewCount);
        }

        $features = $this->describeFeatures($p);
        if ($features !== '') {
            $parts[] = 'Caractéristiques: ' . $features;
        }

        if ($p->getDescription()) {
            $description = mb_substr(trim($p->getDescription()), 0, self::DESCRIPTION_MAX_LENGTH);
            $parts[] = 'Description: ' . $description;
        }

        return '- ' . implode(' | ', $parts);
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
            $described[] = $label . ': ' . $stringValue . ($unit ? ' ' . $unit : '');
        }

        return implode(', ', $described);
    }
}
