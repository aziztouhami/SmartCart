<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function save(Product $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Product $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findBySlug(string $slug): ?Product
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    /**
     * Find products by category
     */
    public function findByCategory(int $categoryId, int $limit = 10, int $offset = 0): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.category = :categoryId')
            ->setParameter('categoryId', $categoryId)
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search products by name or description
     */
    public function search(string $query, int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.name LIKE :query OR p.description LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->setMaxResults($limit)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Products whose name/category/brand matches ANY of the given keywords —
     * used by the chatbot to find grounding candidates for a free-form
     * question. An AND-match (like findWithFilters' search) would too
     * easily return nothing once filler words are mixed in with the real
     * keywords, so this deliberately uses OR across both words and fields.
     *
     * @param string[] $keywords
     * @return Product[]
     */
    public function findByAnyKeyword(array $keywords, int $limit = 6): array
    {
        $keywords = array_values(array_unique(array_filter(
            $keywords,
            fn ($k) => mb_strlen($k) > 1
        )));
        if (empty($keywords)) {
            return [];
        }

        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->leftJoin('p.brand', 'b')
            ->addSelect('p, c, b');

        $orConditions = [];
        foreach ($keywords as $i => $word) {
            $key = "kw{$i}";
            $orConditions[] = "LOWER(p.name) LIKE :{$key} OR LOWER(c.name) LIKE :{$key} OR LOWER(b.name) LIKE :{$key}";
            $qb->setParameter($key, '%' . mb_strtolower($word) . '%');
        }
        $qb->andWhere(implode(' OR ', $orConditions));

        return $qb
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find products in stock
     */
    public function findInStock(int $limit = 10, int $offset = 0): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.stock > 0')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    private const ALLOWED_SORTS = ['name', 'price', 'createdAt', 'rating', 'popularity'];

    /**
     * Find products with optional filters, sorting and pagination
     *
     * @param array<string, string>|null $attributes feature filters, keyed by
     *        ProductTypeAttribute slug (e.g. ['color' => 'Black', 'ram' => '8GB'])
     */
    public function findWithFilters(
        ?string $search = null,
        ?int $categoryId = null,
        ?float $minPrice = null,
        ?float $maxPrice = null,
        ?bool $inStock = null,
        ?int $brandId = null,
        ?int $productTypeId = null,
        ?array $attributes = null,
        string $sortBy = 'createdAt',
        string $sortOrder = 'DESC',
        int $page = 1,
        int $limit = 12,
    ): array {
        $qb = $this->buildFilterQuery($search, $categoryId, $minPrice, $maxPrice, $inStock, $brandId, $productTypeId, $attributes);

        $sortBy = in_array($sortBy, self::ALLOWED_SORTS, true) ? $sortBy : 'createdAt';
        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

        if ($sortBy === 'rating') {
            // COALESCE to 0 so unrated products sort as "worst" rather than
            // landing first under DESC (Postgres puts NULLs first by default
            // on DESC, which would otherwise outrank every 5-star product).
            $qb->leftJoin('p.reviews', 'rv')
                ->addSelect('COALESCE(AVG(rv.rating), 0) AS HIDDEN avgRating')
                ->groupBy('p.id, c.id, b.id, t.id')
                ->orderBy('avgRating', $sortOrder);
        } elseif ($sortBy === 'popularity') {
            // Total units across all order rows regardless of status — a
            // rough "most popular" signal, not the precise revenue-grade
            // figure getTopSelling() computes for admin reporting.
            $qb->leftJoin('p.orderItems', 'oi')
                ->addSelect('COALESCE(SUM(oi.quantity), 0) AS HIDDEN totalSold')
                ->groupBy('p.id, c.id, b.id, t.id')
                ->orderBy('totalSold', $sortOrder);
        } else {
            $qb->orderBy('p.' . $sortBy, $sortOrder);
        }

        return $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count products matching the same filters (for pagination metadata)
     *
     * @param array<string, string>|null $attributes
     */
    public function countWithFilters(
        ?string $search = null,
        ?int $categoryId = null,
        ?float $minPrice = null,
        ?float $maxPrice = null,
        ?bool $inStock = null,
        ?int $brandId = null,
        ?int $productTypeId = null,
        ?array $attributes = null,
    ): int {
        return (int) $this->buildFilterQuery($search, $categoryId, $minPrice, $maxPrice, $inStock, $brandId, $productTypeId, $attributes)
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Autocomplete grouped into 4 priority tiers.
     *
     * Query strategy: identical DQL to the original findForAutocomplete (proven to work
     * with Doctrine + PostgreSQL). Limit raised to 20, PHP classifies each entity into
     * nameStart / nameContains / byBrand / byCategory.
     */
    public function findForAutocompleteGrouped(string $rawQuery): array
    {
        $empty = ['nameStart' => [], 'nameContains' => [], 'byBrand' => [], 'byCategory' => []];
        $lower = mb_strtolower(trim($rawQuery));
        $words = array_values(array_filter(preg_split('/\s+/', $lower)));
        if (empty($words)) {
            return $empty;
        }

        // Same CASE + word-by-word AND pattern as the original autocomplete method —
        // no DQL features beyond what was already working.
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->leftJoin('p.brand', 'b')
            ->addSelect(
                'CASE WHEN LOWER(p.name) LIKE :sw THEN 1 WHEN LOWER(p.name) LIKE :co THEN 2 ELSE 3 END AS HIDDEN tier'
            )
            ->setParameter('sw', $lower . '%')
            ->setParameter('co', '%' . $lower . '%');

        foreach ($words as $i => $word) {
            $key = 'w' . $i;
            $qb->andWhere("LOWER(p.name) LIKE :{$key} OR LOWER(c.name) LIKE :{$key} OR LOWER(b.name) LIKE :{$key}")
               ->setParameter($key, '%' . $word . '%');
        }

        $products = $qb
            ->orderBy('tier', 'ASC')
            ->addOrderBy('p.name', 'ASC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();

        // PHP classification into 4 tiers using the full lowercased query string.
        $groups = $empty;
        $limits = ['nameStart' => 5, 'nameContains' => 4, 'byBrand' => 3, 'byCategory' => 3];

        foreach ($products as $product) {
            $name  = mb_strtolower($product->getName() ?? '');
            $brand = $product->getBrand()    ? mb_strtolower($product->getBrand()->getName()    ?? '') : '';
            $cat   = $product->getCategory() ? mb_strtolower($product->getCategory()->getName() ?? '') : '';

            if (str_starts_with($name, $lower)) {
                $key = 'nameStart';
            } elseif (str_contains($name, $lower)) {
                $key = 'nameContains';
            } elseif ($brand !== '' && str_contains($brand, $lower)) {
                $key = 'byBrand';
            } elseif ($cat !== '' && str_contains($cat, $lower)) {
                $key = 'byCategory';
            } else {
                // multi-word query matched across different fields — put in nameContains
                $key = 'nameContains';
            }

            if (count($groups[$key]) < $limits[$key]) {
                $groups[$key][] = $product;
            }
        }

        return $groups;
    }

    /**
     * @deprecated Use findForAutocompleteGrouped instead.
     */
    public function findForAutocomplete(string $rawQuery, int $limit = 6): array
    {
        $lower = mb_strtolower(trim($rawQuery));
        $words = array_values(array_filter(preg_split('/\s+/', $lower)));
        if (empty($words)) {
            return [];
        }

        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->leftJoin('p.brand', 'b')
            ->addSelect(
                'CASE WHEN LOWER(p.name) LIKE :sw THEN 1 WHEN LOWER(p.name) LIKE :co THEN 2 ELSE 3 END AS HIDDEN relevance'
            )
            ->setParameter('sw', $lower . '%')
            ->setParameter('co', '%' . $lower . '%');

        foreach ($words as $i => $word) {
            $key = 'w' . $i;
            $qb->andWhere("LOWER(p.name) LIKE :{$key} OR LOWER(c.name) LIKE :{$key} OR LOWER(b.name) LIKE :{$key}")
               ->setParameter($key, '%' . $word . '%');
        }

        return $qb
            ->orderBy('relevance', 'ASC')
            ->addOrderBy('p.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param array<string, string>|null $attributes
     */
    private function buildFilterQuery(
        ?string $search,
        ?int $categoryId,
        ?float $minPrice,
        ?float $maxPrice,
        ?bool $inStock,
        ?int $brandId = null,
        ?int $productTypeId = null,
        ?array $attributes = null,
    ): \Doctrine\ORM\QueryBuilder {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->leftJoin('p.brand', 'b')
            ->leftJoin('p.productType', 't')
            ->addSelect('p, c, b, t');

        if ($search !== null && $search !== '') {
            $words = array_values(array_filter(preg_split('/\s+/', trim($search))));
            if (!empty($words)) {
                foreach ($words as $i => $word) {
                    $key = 'sw' . $i;
                    $qb->andWhere(
                        "LOWER(p.name) LIKE :{$key} OR LOWER(c.name) LIKE :{$key} OR LOWER(b.name) LIKE :{$key}"
                    )->setParameter($key, '%' . mb_strtolower($word) . '%');
                }
            }
        }

        if ($categoryId !== null) {
            // Match products directly in the category OR in any child of the category
            $qb->andWhere('p.category = :cid OR IDENTITY(c.parent) = :cid')
                ->setParameter('cid', $categoryId);
        }

        if ($minPrice !== null) {
            $qb->andWhere('p.price >= :minPrice')
                ->setParameter('minPrice', $minPrice);
        }

        if ($maxPrice !== null) {
            $qb->andWhere('p.price <= :maxPrice')
                ->setParameter('maxPrice', $maxPrice);
        }

        if ($inStock === true) {
            $qb->andWhere('p.stock > 0');
        }

        if ($brandId !== null) {
            $qb->andWhere('p.brand = :brandId')
                ->setParameter('brandId', $brandId);
        }

        if ($productTypeId !== null) {
            $qb->andWhere('p.productType = :productTypeId')
                ->setParameter('productTypeId', $productTypeId);
        }

        if (!empty($attributes)) {
            $matchingIds = $this->findIdsMatchingAttributes($attributes);
            // No product matches all requested feature values — short-circuit
            // with an impossible id rather than an empty IN(), which is
            // invalid SQL.
            $qb->andWhere('p.id IN (:attrIds)')
                ->setParameter('attrIds', empty($matchingIds) ? [-1] : $matchingIds);
        }

        return $qb;
    }

    /**
     * Product ids whose JSON `attributes` column matches every requested
     * slug => value pair. Raw SQL because DQL has no JSON accessor function
     * registered (no doctrine extensions package installed) — this is the
     * one place that needs it, everything else stays in the QueryBuilder so
     * pagination/sorting/joins work normally on the result.
     *
     * @param array<string, string> $attributes
     * @return int[]
     */
    private function findIdsMatchingAttributes(array $attributes): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $conditions = [];
        $params = [];
        $i = 0;
        foreach ($attributes as $slug => $value) {
            $key = "slug{$i}";
            $valKey = "val{$i}";
            $conditions[] = "attributes->>:{$key} = :{$valKey}";
            $params[$key] = $slug;
            $params[$valKey] = (string) $value;
            $i++;
        }

        $rows = $conn->fetchAllAssociative(
            'SELECT id FROM product WHERE ' . implode(' AND ', $conditions),
            $params
        );

        return array_map(fn ($row) => (int) $row['id'], $rows);
    }

    /**
     * Facets for the "intelligent filter" sidebar: which brands, product
     * types and feature values actually occur among products matching the
     * current search/category scope, plus the price range.
     *
     * Each facet is computed against every *other* currently active filter
     * but not its own (e.g. the type facet ignores the type filter, the
     * "color" attribute facet ignores the color filter) — so picking a
     * value narrows the *other* facets down to what's actually compatible
     * (e.g. selecting color=grey hides product types that have no grey
     * items) while the facet you just picked from stays fully selectable
     * instead of collapsing to only the value you chose.
     *
     * @param array<string, string>|null $attributes currently selected attr filters (slug => value)
     *
     * @return array{
     *     brands: array<int, array{id: int, name: string, count: int}>,
     *     productTypes: array<int, array{id: int, name: string, slug: string, count: int}>,
     *     priceRange: array{min: float, max: float},
     *     attributes: array<int, array{slug: string, name: string, dataType: string, unit: ?string, options: ?array, values: array<int, array{value: string, count: int}>, valuesByType: array<int, array<int, array{value: string, count: int}>>, productTypeIds: int[]}>
     * }
     */
    public function getFacets(
        ?string $search,
        ?int $categoryId,
        ?int $brandId = null,
        ?int $productTypeId = null,
        ?array $attributes = null,
        ?float $minPrice = null,
        ?float $maxPrice = null,
        ?bool $inStock = null,
    ): array {
        $attributes = $attributes ?: [];

        $brandScope = $this->buildFilterQuery($search, $categoryId, $minPrice, $maxPrice, $inStock, null, $productTypeId, $attributes ?: null)
            ->getQuery()->getResult();

        $typeScope = $this->buildFilterQuery($search, $categoryId, $minPrice, $maxPrice, $inStock, $brandId, null, $attributes ?: null)
            ->getQuery()->getResult();

        $priceScope = $this->buildFilterQuery($search, $categoryId, null, null, $inStock, $brandId, $productTypeId, $attributes ?: null)
            ->getQuery()->getResult();

        $brandCounts = [];
        foreach ($brandScope as $product) {
            if ($brand = $product->getBrand()) {
                $brandCounts[$brand->getId()] ??= ['id' => $brand->getId(), 'name' => $brand->getName(), 'count' => 0];
                $brandCounts[$brand->getId()]['count']++;
            }
        }

        $typeCounts = [];
        $typesInScope = []; // typeId => ProductType, used below to gather attribute defs
        foreach ($typeScope as $product) {
            if ($type = $product->getProductType()) {
                $typeCounts[$type->getId()] ??= ['id' => $type->getId(), 'name' => $type->getName(), 'slug' => $type->getSlug(), 'count' => 0];
                $typeCounts[$type->getId()]['count']++;
                $typesInScope[$type->getId()] = $type;
            }
        }

        $prices = array_map(fn ($p) => (float) $p->getPrice(), $priceScope);

        // Every attribute slug defined by a type currently in scope, and
        // which type(s) define it — same purpose as before: lets the
        // frontend hide attributes irrelevant to the selected type.
        $attributeDefBySlug = [];
        $attributeTypeIds = []; // slug => [typeId => true]
        foreach ($typesInScope as $typeId => $type) {
            foreach ($type->getAttributes() as $def) {
                $attributeDefBySlug[$def->getSlug()] ??= $def;
                $attributeTypeIds[$def->getSlug()][$typeId] = true;
            }
        }

        $toSortedValues = function (array $counts): array {
            $values = [];
            foreach ($counts as $value => $count) {
                $values[] = ['value' => $value, 'count' => $count];
            }
            usort($values, fn ($a, $b) => $b['count'] <=> $a['count']);
            return $values;
        };

        $attributesResult = [];
        foreach ($attributeDefBySlug as $slug => $def) {
            $attrsWithoutSlug = $attributes;
            unset($attrsWithoutSlug[$slug]);

            $slugScope = $this->buildFilterQuery($search, $categoryId, $minPrice, $maxPrice, $inStock, $brandId, $productTypeId, $attrsWithoutSlug ?: null)
                ->getQuery()->getResult();

            $valuesGlobal = []; // value => count
            $valuesByType = []; // typeId => value => count

            foreach ($slugScope as $product) {
                $type = $product->getProductType();
                if (!$type || !isset($attributeTypeIds[$slug][$type->getId()])) {
                    continue;
                }
                $value = $product->getAttributes()[$slug] ?? null;
                if ($value === null) {
                    continue;
                }
                $stringValue = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
                $valuesGlobal[$stringValue] = ($valuesGlobal[$stringValue] ?? 0) + 1;
                $valuesByType[$type->getId()][$stringValue] = ($valuesByType[$type->getId()][$stringValue] ?? 0) + 1;
            }

            if (empty($valuesGlobal)) {
                continue;
            }

            $attributesResult[] = [
                'slug' => $slug,
                'name' => $def->getName(),
                'dataType' => $def->getDataType(),
                'unit' => $def->getUnit(),
                'options' => $def->getOptions(),
                'values' => $toSortedValues($valuesGlobal),
                'valuesByType' => array_map($toSortedValues, $valuesByType),
                'productTypeIds' => array_keys($attributeTypeIds[$slug]),
            ];
        }

        return [
            'brands' => array_values($brandCounts),
            'productTypes' => array_values($typeCounts),
            'priceRange' => ['min' => empty($prices) ? 0.0 : min($prices), 'max' => empty($prices) ? 0.0 : max($prices)],
            'attributes' => $attributesResult,
        ];
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findLowStock(int $threshold = 5, int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.stock <= :threshold')
            ->andWhere('p.stock >= 0')
            ->setParameter('threshold', $threshold)
            ->orderBy('p.stock', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countLowStock(int $threshold = 5): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.stock <= :threshold')
            ->andWhere('p.stock > 0')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countOutOfStock(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.stock = 0')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Top products by total units sold (excludes carts and cancelled orders).
     */
    public function getTopSelling(int $limit = 5): array
    {
        return $this->createQueryBuilder('p')
            ->addSelect('SUM(oi.quantity) AS HIDDEN totalSold')
            ->join('p.orderItems', 'oi')
            ->join('oi.order', 'o')
            ->andWhere('o.status NOT IN (:excluded)')
            ->setParameter('excluded', ['cart', 'cancelled'])
            ->groupBy('p.id')
            ->orderBy('totalSold', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Whole catalog with category/brand/productType eager-loaded — for call
     * sites that genuinely need every product (e.g. checking which ones
     * match a store-wide promotion) and would otherwise N+1 on those
     * relations while building a DTO.
     */
    public function findAllWithRelations(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->leftJoin('p.brand', 'b')
            ->leftJoin('p.productType', 't')
            ->addSelect('p, c, b, t')
            ->getQuery()
            ->getResult();
    }

    /**
     * Product count per root category, counting products directly in the
     * root OR in any of its direct children (same semantics as
     * countWithFilters(categoryId: $rootId) applied to each root, but
     * batched into a single query instead of one COUNT per root).
     *
     * @param int[] $rootCategoryIds
     * @return array<int, int> rootCategoryId => product count
     */
    public function countByCategoryIds(array $rootCategoryIds): array
    {
        if (empty($rootCategoryIds)) {
            return [];
        }

        $rows = $this->createQueryBuilder('p')
            ->select('CASE WHEN p.category IN (:ids) THEN IDENTITY(p.category) ELSE IDENTITY(c.parent) END AS rootId, COUNT(p.id) AS cnt')
            ->join('p.category', 'c')
            ->andWhere('p.category IN (:ids) OR IDENTITY(c.parent) IN (:ids)')
            ->setParameter('ids', $rootCategoryIds)
            ->groupBy('rootId')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['rootId']] = (int) $row['cnt'];
        }
        return $counts;
    }

    /**
     * Newest products first, ranked 1.0 (newest) down to ~0.0 (oldest) —
     * same shape ColdStartRecommendationService::fallbackToNewest produced
     * via findAll()+usort, but ordered in SQL instead of in PHP.
     */
    public function findNewestRanked(): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
