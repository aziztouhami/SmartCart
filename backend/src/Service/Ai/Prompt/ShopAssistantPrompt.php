<?php

namespace App\Service\Ai\Prompt;

/**
 * Builds the chatbot's full prompt for one chat turn: fixed grounding/tone
 * instructions + the catalogue overview, matched product fact-sheets, and
 * recent conversation history. Callers gather the data (repositories,
 * entities) — this class only owns the wording and structure sent to the
 * model. See ChatbotService::buildPrompt().
 */
class ShopAssistantPrompt
{
    private const MAX_HISTORY_TURNS = 6;

    /**
     * @param string[] $productLines Pre-formatted "- Nom: ... | Prix: ..." lines,
     *                                one per matched product (empty when none matched).
     * @param array<int, array{role: string, content: string}> $history
     */
    public function build(
        string $siteName,
        string $catalogOverview,
        array $productLines,
        array $history,
        string $message,
    ): string {
        $lines = [
            "Tu es l'assistant boutique de {$siteName}.",
            'Réponds UNIQUEMENT à partir des données fournies ci-dessous (aperçu du catalogue + fiches produit).',
            "Si une information ne figure pas dans ces données, dis-le clairement — n'invente jamais un prix, un stock, une caractéristique ou une promotion.",
            'Réponds TOUJOURS dans la même langue que le dernier message de l\'utilisateur (français s\'il écrit en français, anglais s\'il écrit en anglais, etc.) — jamais dans une autre langue, même si les instructions ci-dessus sont en français.',
            'Réponds de façon brève et utile (quelques phrases maximum).',
            '',
            $catalogOverview,
            '',
            '[FICHES PRODUIT PERTINENTES]',
        ];

        if (empty($productLines)) {
            $lines[] = '(Aucun produit du catalogue ne correspond précisément à cette demande — base-toi sur l\'aperçu du catalogue ci-dessus si la question est générale.)';
        } else {
            array_push($lines, ...$productLines);
        }
        $lines[] = '';

        if (!empty($history)) {
            $lines[] = '[HISTORIQUE DE CONVERSATION]';
            foreach (array_slice($history, -self::MAX_HISTORY_TURNS) as $turn) {
                $role = ($turn['role'] ?? '') === 'assistant' ? 'Assistant' : 'Utilisateur';
                $lines[] = "{$role}: " . ($turn['content'] ?? '');
            }
            $lines[] = '';
        }

        $lines[] = '[NOUVEAU MESSAGE]';
        $lines[] = "Utilisateur: {$message}";
        $lines[] = '';
        $lines[] = '(Rappel : ta réponse doit être dans la même langue que le message ci-dessus, pas forcément en français.)';

        return implode("\n", $lines);
    }
}
