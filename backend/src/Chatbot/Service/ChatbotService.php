<?php

namespace App\Chatbot\Service;

use App\Entity\ChatMessageLog;
use App\Entity\Product;
use App\Entity\Promotion;
use App\Repository\BrandRepository;
use App\Repository\CategoryRepository;
use App\Repository\ChatMessageLogRepository;
use App\Repository\ProductRepository;
use App\Repository\PromotionRepository;
use App\Repository\ReviewRepository;

/**
 * Orchestrates one chat turn: logs the message (also the rate-limit
 * counter), finds grounding products, builds the full prompt — catalogue
 * overview + full product detail (price, stock, description, features,
 * active promotion, rating) — calls Gemini, logs and returns the reply.
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
        private GeminiClientService $geminiClient,
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

        $products = $this->findRelevantProducts($message);
        $prompt = $this->buildPrompt($message, $products, $history);

        $reply = $this->geminiClient->generate($prompt) ?? self::FALLBACK_REPLY;

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

    /** @return Product[] */
    private function findRelevantProducts(string $message): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($message)) ?: [];
        $keywords = array_values(array_filter(
            $words,
            fn ($w) => mb_strlen($w) > 2 && !in_array($w, self::STOPWORDS, true)
        ));

        if (empty($keywords)) {
            return [];
        }

        return $this->productRepository->findByAnyKeyword($keywords, self::MAX_PRODUCTS_IN_PROMPT);
    }

    /**
     * @param Product[] $products
     * @param array<int, array{role: string, content: string}> $history
     */
    private function buildPrompt(string $message, array $products, array $history): string
    {
        $lines = [
            "Tu es l'assistant boutique de {$this->siteName}.",
            "Réponds UNIQUEMENT à partir des données fournies ci-dessous (aperçu du catalogue + fiches produit).",
            "Si une information ne figure pas dans ces données, dis-le clairement — n'invente jamais un prix, un stock, une caractéristique ou une promotion.",
            "Réponds TOUJOURS dans la même langue que le dernier message de l'utilisateur (français s'il écrit en français, anglais s'il écrit en anglais, etc.) — jamais dans une autre langue, même si les instructions ci-dessus sont en français.",
            "Réponds de façon brève et utile (quelques phrases maximum).",
            '',
            $this->buildCatalogOverview(),
            '',
            '[FICHES PRODUIT PERTINENTES]',
        ];

        if (empty($products)) {
            $lines[] = '(Aucun produit du catalogue ne correspond précisément à cette demande — base-toi sur l\'aperçu du catalogue ci-dessus si la question est générale.)';
        } else {
            $promoMap = $this->promotionRepository->findActiveForProducts($products);
            foreach ($products as $p) {
                $lines[] = $this->describeProduct($p, $promoMap[$p->getId()] ?? null);
            }
        }
        $lines[] = '';

        if (!empty($history)) {
            $lines[] = '[HISTORIQUE DE CONVERSATION]';
            foreach (array_slice($history, -6) as $turn) {
                $role = ($turn['role'] ?? '') === 'assistant' ? 'Assistant' : 'Utilisateur';
                $lines[] = "{$role}: " . ($turn['content'] ?? '');
            }
            $lines[] = '';
        }

        $lines[] = '[NOUVEAU MESSAGE]';
        $lines[] = "Utilisateur: {$message}";
        $lines[] = '';
        $lines[] = "(Rappel : ta réponse doit être dans la même langue que le message ci-dessus, pas forcément en français.)";

        return implode("\n", $lines);
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
     * => 5000) — not something Gemini (or a user) should have to decode, so
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
