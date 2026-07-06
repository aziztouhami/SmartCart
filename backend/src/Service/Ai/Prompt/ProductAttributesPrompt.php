<?php

namespace App\Service\Ai\Prompt;

/**
 * Builds the prompt asking the LLM for standard-market features for a
 * product type name (e.g. "Casque audio"). See
 * ProductTypeService::suggestAttributes() for how the JSON result is
 * validated/sanitized before it's shown to the admin — this class only
 * owns the wording sent to the model.
 */
class ProductAttributesPrompt
{
    /**
     * @param string[] $existingNames Features the type already has (edit flow) —
     *                                asks the model for new ones only.
     */
    public function build(string $typeName, array $existingNames = []): string
    {
        $existingClause = '';
        if (!empty($existingNames)) {
            $existingClause = "\n\nThis type already has the following attributes: " . implode(', ', $existingNames)
                . '. Do NOT suggest them again — only suggest additional, relevant attributes that are still missing.';
        }

        return <<<PROMPT
            You are an e-commerce expert. For a product type named "{$typeName}", list the standard market attributes (technical characteristics) for this product type — the fields a buyer would expect to see on a product page.{$existingClause}

            Respond ONLY with a valid JSON object of this shape, with no surrounding text:
            {"attributes": [{"name": "string", "dataType": "text|number|boolean|select", "unit": "string or null", "options": ["string", ...] or null, "required": true|false}]}

            Rules:
            - dataType must be exactly one of: text, number, boolean, select.
            - options is required and non-empty only if dataType is "select"; otherwise it must be null.
            - unit is a short string (e.g. "mAh", "GB", "cm") only relevant for dataType="number"; otherwise null.
            - All attribute names and options must be in English.
            - Suggest between 3 and 8 relevant, non-redundant attributes.
            PROMPT;
    }
}
