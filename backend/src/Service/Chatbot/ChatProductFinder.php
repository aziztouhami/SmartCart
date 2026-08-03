<?php

namespace App\Service\Chatbot;

use App\Entity\Product;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;

/**
 * Finds the products that should ground a chatbot reply: keyword search
 * across name/description/category/brand (with an English-translated pass
 * for non-English messages), falling back to a fuzzy category-name match
 * when nothing matched directly.
 */
class ChatProductFinder
{
    private const MAX_PRODUCTS_IN_PROMPT = 6;

    /** Filler words that would otherwise dilute the keyword search into matching nothing or everything. */
    private const STOPWORDS = [
        'le', 'la', 'les', 'un', 'une', 'des', 'de', 'du', 'et', 'ou', 'est', 'es', 'pour', 'avec',
        'dans', 'sur', 'ce', 'cette', 'ces', 'vous', 'avez', 'as', 'a', 'je', 'tu', 'il', 'elle',
        'nous', 'votre', 'vos', 'mon', 'ma', 'mes', 'que', 'qui', 'quoi', 'comment', 'bonjour', 'salut',
        'the', 'a', 'an', 'is', 'are', 'do', 'you', 'have', 'this', 'that', 'for', 'with', 'and', 'or',
    ];

    public function __construct(
        private ProductRepository $productRepository,
        private CategoryRepository $categoryRepository,
        private TranslationService $translationService,
    ) {
    }

    /**
     * @param array<int, array{role: string, content: string}> $history
     *
     * @return Product[]
     */
    public function find(string $message, array $history = []): array
    {
        // Combine the current message with the last 3 conversation turns so that
        // follow-up questions ("c'est quoi leur prix", "et en rouge ?") can still
        // find the products discussed earlier — this works for ALL product types
        // without any hardcoded list.
        $textToSearch = $message;
        foreach (array_slice($history, -3) as $turn) {
            $textToSearch .= ' '.($turn['content'] ?? '');
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
     *
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
        for ($len = $max; $len >= $minLength; --$len) {
            if (mb_substr($a, -$len) === mb_substr($b, -$len)) {
                return true;
            }
        }

        return false;
    }
}
