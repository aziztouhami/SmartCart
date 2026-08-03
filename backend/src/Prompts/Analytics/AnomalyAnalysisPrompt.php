<?php

namespace App\Prompts\Analytics;

/**
 * Builds the prompt asking the LLM to interpret one entity's behavioral KPIs
 * (views, cart adds, purchases, favorites, ratings, conversion rate,
 * current price/stock, and a weekly sell-through time series) and flag
 * anomalies in plain language. One shared class for products/categories/
 * brands/product types rather than four near-duplicates — only the entity
 * label and the feature data passed in differ.
 *
 * See AnomalyAnalysisService for how the JSON result is validated/sanitized
 * before it's shown to the admin — this class only owns the wording sent to
 * the model.
 */
class AnomalyAnalysisPrompt
{
    /**
     * @param array<string, mixed>                                 $features   the entity's feature vector (see the
     *                                                                         matching Service\Feature\*Builder)
     * @param array<int, array{weekStart: string, unitsSold: int}> $timeSeries units sold per week, oldest first
     */
    public function build(string $entityType, string $entityName, array $features, array $timeSeries): string
    {
        $featureLines = [];
        foreach ($features as $key => $value) {
            if (null === $value) {
                $value = 'unknown';
            } elseif (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            $featureLines[] = "- {$key}: {$value}";
        }

        $timeSeriesLines = [];
        foreach ($timeSeries as $week) {
            $timeSeriesLines[] = "- week of {$week['weekStart']}: {$week['unitsSold']} units sold";
        }
        if (empty($timeSeriesLines)) {
            $timeSeriesLines[] = '(no purchases recorded in this period)';
        }

        return <<<PROMPT
            You are an e-commerce data analyst. Analyze the following {$entityType} named "{$entityName}" and identify any anomalies or notable patterns in its behavioral KPIs.

            [KPIs]
            {$this->joinLines($featureLines)}

            [WEEKLY SELL-THROUGH — units sold per week, oldest first]
            {$this->joinLines($timeSeriesLines)}

            Important constraints:
            - Only "current price" and "current stock" are available as single data points — there is NO historical price-change data at all. Never claim or imply a price trend; if price is relevant, only describe it as-is right now.
            - The weekly sell-through series above IS real historical data (derived from purchase records) — use it freely to spot trends, drops, or spikes in sales velocity.
            - Base every finding strictly on the numbers given. Do not invent data that isn't listed above.

            Respond ONLY with a valid JSON object of this shape, with no surrounding text:
            {"healthScore": 0-100, "summary": "two sentences in plain language", "anomalies": [{"metric": "string", "severity": "low|medium|high", "finding": "string", "recommendation": "string"}]}

            Rules:
            - healthScore: overall health from 0 (serious problems) to 100 (excellent), based on the KPIs above.
            - severity must be exactly one of: low, medium, high.
            - anomalies can be an empty array if nothing stands out — do not invent problems that aren't supported by the data.
            - Keep "finding" and "recommendation" concise (one sentence each).
            PROMPT;
    }

    /**
     * @param string[] $lines
     */
    private function joinLines(array $lines): string
    {
        return implode("\n", $lines);
    }
}
