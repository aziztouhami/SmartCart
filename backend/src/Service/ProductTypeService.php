<?php

namespace App\Service;

use App\DTO\Product\CreateAttributeRequest;
use App\DTO\Product\CreateProductTypeRequest;
use App\DTO\Product\UpdateProductTypeRequest;
use App\Entity\ProductType;
use App\Entity\ProductTypeAttribute;
use App\Prompts\ProductType\ProductAttributesPrompt;
use App\Service\Ai\GroqClientService;
use Doctrine\ORM\EntityManagerInterface;

class ProductTypeService
{
    public function __construct(
        private EntityManagerInterface $em,
        private SlugService $slugService,
        private GroqClientService $aiClient,
        private ProductAttributesPrompt $productAttributesPrompt,
    ) {
    }

    /**
     * Asks the LLM for standard-market features for a product type name the
     * admin is about to create (e.g. "Casque audio"). Nothing is persisted —
     * the admin reviews, edits, and confirms via the normal create()/
     * addAttribute() flow. The model is never trusted to emit a valid
     * dataType/options shape on its own, so every field is re-validated here
     * exactly as it would be for a human-submitted CreateAttributeRequest.
     *
     * @param string[] $existingNames features the type already has (edit flow) —
     *                                the model is asked for new ones only, and
     *                                the result is filtered again as a backstop
     *
     * @return array<int, array{name: string, dataType: string, unit: ?string, options: ?array, required: bool}>
     */
    public function suggestAttributes(string $typeName, array $existingNames = []): array
    {
        $prompt = $this->productAttributesPrompt->build($typeName, $existingNames);

        $result = $this->aiClient->generateJson($prompt);
        if (!isset($result['attributes']) || !is_array($result['attributes'])) {
            return [];
        }

        $existingLower = array_map(fn (string $n) => mb_strtolower(trim($n)), $existingNames);

        $suggestions = [];
        foreach ($result['attributes'] as $raw) {
            if (!is_array($raw) || empty($raw['name'])) {
                continue;
            }

            $name = trim((string) $raw['name']);
            if (in_array(mb_strtolower($name), $existingLower, true)) {
                continue;
            }

            $dataType = in_array($raw['dataType'] ?? null, ProductTypeAttribute::DATA_TYPES, true)
                ? $raw['dataType']
                : 'text';
            $options = $raw['options'] ?? null;

            // A "select" with no usable options is not a valid suggestion —
            // downgrade to "text" rather than surfacing something the admin
            // would immediately have to fix.
            if ('select' === $dataType && empty($options)) {
                $dataType = 'text';
            }

            $suggestions[] = [
                'name' => $name,
                'dataType' => $dataType,
                'unit' => 'number' === $dataType && !empty($raw['unit']) ? (string) $raw['unit'] : null,
                'options' => 'select' === $dataType ? array_values(array_map('strval', (array) $options)) : null,
                'required' => (bool) ($raw['required'] ?? false),
            ];
        }

        return $suggestions;
    }

    public function create(CreateProductTypeRequest $dto): ProductType
    {
        $type = new ProductType();
        $type->setName($dto->name);
        $type->setSlug($this->slugService->generateProductTypeSlug($dto->name));

        foreach ($dto->attributes as $raw) {
            $type->addAttribute($this->buildAttribute($type, $raw));
        }

        $this->em->persist($type);
        $this->em->flush();

        return $type;
    }

    /**
     * Add a new feature to an already-existing type (e.g. the admin realizes
     * "smartwatch" also needs a "Strap material" field after the fact).
     */
    public function addAttribute(ProductType $type, CreateAttributeRequest $dto): ProductTypeAttribute
    {
        $attribute = $this->buildAttribute($type, [
            'name' => $dto->name,
            'dataType' => $dto->dataType,
            'unit' => $dto->unit,
            'options' => $dto->options,
            'required' => $dto->required,
        ]);

        $type->addAttribute($attribute);
        $this->em->persist($attribute);
        $this->em->flush();

        return $attribute;
    }

    /**
     * Rename a type. The slug is intentionally left untouched once set —
     * products already reference it, and product detail pages may link to
     * /product-type/{slug}-style URLs in the future.
     */
    public function rename(ProductType $type, UpdateProductTypeRequest $dto): ProductType
    {
        $type->setName($dto->name);
        $this->em->flush();

        return $type;
    }

    /**
     * Drop a feature from a type. Any value already stored under that slug
     * on existing products is simply orphaned (harmless — it stops being
     * read or shown once the definition is gone).
     */
    public function removeAttribute(ProductType $type, ProductTypeAttribute $attribute): void
    {
        if ($attribute->getProductType()?->getId() !== $type->getId()) {
            throw new \RuntimeException('This feature does not belong to this product type', 404);
        }

        $type->removeAttribute($attribute);
        $this->em->remove($attribute);
        $this->em->flush();
    }

    public function delete(ProductType $type): void
    {
        if (!$type->getProducts()->isEmpty()) {
            throw new \RuntimeException('Cannot delete a type that still has products assigned to it', 409);
        }

        $this->em->remove($type);
        $this->em->flush();
    }

    /**
     * Validates a product's raw attribute payload against its type's feature
     * definitions: drops unknown keys, coerces values to the declared data
     * type, and enforces required features. Returns the cleaned map to store
     * on Product::$attributes.
     */
    public function resolveAttributeValues(ProductType $type, array $rawValues): array
    {
        $resolved = [];

        foreach ($type->getAttributes() as $attribute) {
            $slug = $attribute->getSlug();

            if (!array_key_exists($slug, $rawValues) || null === $rawValues[$slug] || '' === $rawValues[$slug]) {
                if ($attribute->isRequired()) {
                    throw new \RuntimeException(sprintf('Feature "%s" is required for this product type', $attribute->getName()), 400);
                }
                continue;
            }

            $resolved[$slug] = $this->coerceValue($attribute, $rawValues[$slug]);
        }

        return $resolved;
    }

    private function buildAttribute(ProductType $type, array $raw): ProductTypeAttribute
    {
        $name = trim((string) ($raw['name'] ?? ''));
        if ('' === $name) {
            throw new \RuntimeException('Each feature needs a name', 400);
        }

        $slug = $this->slugService->slugify($name);
        foreach ($type->getAttributes() as $existing) {
            if ($existing->getSlug() === $slug) {
                throw new \RuntimeException(sprintf('This type already has a feature named "%s"', $name), 409);
            }
        }

        $dataType = $raw['dataType'] ?? 'text';
        $options = $raw['options'] ?? null;

        if ('select' === $dataType && empty($options)) {
            throw new \RuntimeException(sprintf('Feature "%s" is a select but has no options', $name), 400);
        }

        $attribute = new ProductTypeAttribute();
        $attribute->setName($name);
        $attribute->setSlug($slug);

        try {
            $attribute->setDataType($dataType);
        } catch (\InvalidArgumentException $e) {
            throw new \RuntimeException($e->getMessage(), 400);
        }

        $attribute->setUnit($raw['unit'] ?? null);
        $attribute->setOptions('select' === $dataType ? array_values($options) : null);
        $attribute->setRequired((bool) ($raw['required'] ?? false));

        return $attribute;
    }

    private function coerceValue(ProductTypeAttribute $attribute, mixed $value): mixed
    {
        switch ($attribute->getDataType()) {
            case 'number':
                if (!is_numeric($value)) {
                    throw new \RuntimeException(sprintf('Feature "%s" must be a number', $attribute->getName()), 400);
                }

                return (float) $value;

            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);

            case 'select':
                $options = $attribute->getOptions() ?? [];
                if (!in_array($value, $options, true)) {
                    throw new \RuntimeException(sprintf('Feature "%s" must be one of: %s', $attribute->getName(), implode(', ', $options)), 400);
                }

                return $value;

            default:
                return (string) $value;
        }
    }
}
