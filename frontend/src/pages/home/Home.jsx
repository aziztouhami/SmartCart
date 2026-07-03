import React, { useState, useEffect, useCallback } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Search, Wand2, TrendingUp, Clock, Percent, SlidersHorizontal } from 'lucide-react';
import Navbar from '../../components/Navbar';
import ProductCard, { SkeletonCard } from '../../components/ProductCard';
import { Button, Badge } from '../../components/ui';
import { productApi, recommendationApi } from '../../services/cartService';
import { useCategories } from '../../context/CategoryContext';
import { CATEGORY_ICONS, DEFAULT_CATEGORY_ICON } from '../../constants/categoryIcons';
import './Home.css';

// Kept for backward compatibility (ProductDetail imports this)
export const ALL_PRODUCTS = [];

const EMPTY_FACETS = { brands: [], productTypes: [], priceRange: { min: 0, max: 0 }, attributes: [] };
const EMPTY_FILTERS = { brand: '', type: '', attrs: {}, inStock: false, minPrice: '', maxPrice: '' };

const SORT_OPTIONS = [
  { value: 'createdAt-desc',  labelKey: 'sort.newest',          sortBy: 'createdAt', order: 'desc' },
  { value: 'price-asc',       labelKey: 'sort.priceAsc',        sortBy: 'price',     order: 'asc'  },
  { value: 'price-desc',      labelKey: 'sort.priceDesc',       sortBy: 'price',     order: 'desc' },
  { value: 'name-asc',        labelKey: 'sort.nameAsc',         sortBy: 'name',      order: 'asc'  },
  { value: 'rating-desc',     labelKey: 'sort.ratingDesc',      sortBy: 'rating',    order: 'desc' },
  { value: 'popularity-desc', labelKey: 'sort.popularityDesc',  sortBy: 'popularity', order: 'desc' },
];

function countActiveFilters(filters) {
  return (filters.brand ? 1 : 0)
    + (filters.type ? 1 : 0)
    + Object.keys(filters.attrs).length
    + (filters.inStock ? 1 : 0)
    + (filters.minPrice !== '' ? 1 : 0)
    + (filters.maxPrice !== '' ? 1 : 0);
}

// ── Intelligent filter sidebar: brand/type/price/stock + dynamic feature
// filters (only the attributes that actually occur for products in scope,
// narrowed further to the selected type once one is picked) ──────────────
function FilterSidebar({ facets, facetsLoading, filters, onChange, sortValue, onSortChange }) {
  const { t } = useTranslation('home');
  const activeCount = countActiveFilters(filters);
  // Once a type is picked, use that type's own value counts (valuesByType) —
  // not the slug's global tally, which pools values across every type that
  // happens to reuse the same attribute slug (e.g. "color" on laptops AND
  // smartphones) and would otherwise offer values no laptop actually has.
  const visibleAttributes = facets.attributes
    .filter(attr => !filters.type || attr.productTypeIds.includes(Number(filters.type)))
    .map(attr => ({
      ...attr,
      values: filters.type ? (attr.valuesByType[filters.type] || []) : attr.values,
    }))
    .filter(attr => attr.values.length > 0);

  const setAttr = (slug, value) => {
    const attrs = { ...filters.attrs };
    if (value) attrs[slug] = value;
    else delete attrs[slug];
    onChange({ ...filters, attrs });
  };

  return (
    <aside className="h-sidebar">
      <div className="h-sidebar-head">
        <h3><SlidersHorizontal size={16} /> {t('filters.title')}</h3>
        {activeCount > 0 && (
          <Button variant="ghost" size="sm" className="h-sidebar-reset" onClick={() => { onChange(EMPTY_FILTERS); onSortChange('createdAt-desc'); }}>{t('filters.reset', { count: activeCount })}</Button>
        )}
      </div>

      <div className="h-filter-group">
        <h4>{t('filters.sortBy')}</h4>
        <select value={sortValue} onChange={e => onSortChange(e.target.value)}>
          {SORT_OPTIONS.map(o => <option key={o.value} value={o.value}>{t(o.labelKey)}</option>)}
        </select>
      </div>

      {facetsLoading ? (
        <p className="h-sidebar-loading">{t('filters.loading')}</p>
      ) : (
        <>
          {facets.brands.length > 0 && (
            <div className="h-filter-group">
              <h4>{t('filters.brand')}</h4>
              <select value={filters.brand} onChange={e => onChange({ ...filters, brand: e.target.value })}>
                <option value="">{t('filters.allBrands')}</option>
                {facets.brands.map(b => (
                  <option key={b.id} value={b.id}>{b.name} ({b.count})</option>
                ))}
              </select>
            </div>
          )}

          {facets.productTypes.length > 1 && (
            <div className="h-filter-group">
              <h4>{t('filters.type')}</h4>
              <select value={filters.type} onChange={e => onChange({ ...filters, type: e.target.value, attrs: {} })}>
                <option value="">{t('filters.allTypes')}</option>
                {facets.productTypes.map(pt => (
                  <option key={pt.id} value={pt.id}>{pt.name} ({pt.count})</option>
                ))}
              </select>
            </div>
          )}

          <div className="h-filter-group">
            <h4>{t('filters.price')}</h4>
            <div className="h-price-row">
              <input
                type="number"
                placeholder={t('filters.priceMin', { value: Math.floor(facets.priceRange.min) })}
                value={filters.minPrice}
                onChange={e => onChange({ ...filters, minPrice: e.target.value })}
              />
              <span>–</span>
              <input
                type="number"
                placeholder={t('filters.priceMax', { value: Math.ceil(facets.priceRange.max) })}
                value={filters.maxPrice}
                onChange={e => onChange({ ...filters, maxPrice: e.target.value })}
              />
            </div>
          </div>

          <div className="h-filter-group">
            <label className="h-checkbox-row">
              <input
                type="checkbox"
                checked={filters.inStock}
                onChange={e => onChange({ ...filters, inStock: e.target.checked })}
              />
              {t('filters.inStockOnly')}
            </label>
          </div>

          {visibleAttributes.map(attr => (
            <div className="h-filter-group" key={attr.slug}>
              <h4>{attr.name}{attr.unit ? ` (${attr.unit})` : ''}</h4>
              <select value={filters.attrs[attr.slug] || ''} onChange={e => setAttr(attr.slug, e.target.value)}>
                <option value="">{t('filters.any')}</option>
                {attr.values.map(v => (
                  <option key={v.value} value={v.value}>{String(v.value)} ({v.count})</option>
                ))}
              </select>
            </div>
          ))}
        </>
      )}
    </aside>
  );
}

// ── Horizontal product row (used for all homepage sections) ───────────────────
function ProductRow({ icon: Icon, title, subtitle, products, loading, viewAllTo, onViewAll, skeletonCount = 6 }) {
  const { t } = useTranslation('home');
  if (!loading && products.length === 0) return null;

  return (
    <div className="h-section">
      <div className="h-section-header">
        <div className="h-section-left">
          <span className="h-section-emoji"><Icon size={22} /></span>
          <div>
            <h2 className="h-section-title">{title}</h2>
            {subtitle && <p className="h-section-sub">{subtitle}</p>}
          </div>
        </div>
        {viewAllTo && (
          <Button variant="outline" size="sm" className="h-btn-viewall" onClick={onViewAll}>{t('viewAll')}</Button>
        )}
      </div>

      <div className="h-scroll">
        {loading
          ? Array.from({ length: skeletonCount }).map((_, i) => <SkeletonCard key={i} />)
          : products.map(p => <ProductCard key={p.id} product={p} />)
        }
      </div>
    </div>
  );
}

// ── Sectioned homepage (no filters active) ─────────────────────────────────────
function HomeSections({ categories }) {
  const { t } = useTranslation('home');
  const navigate = useNavigate();

  const [recommended,        setRecommended]        = useState([]);
  const [recommendedLoading, setRecommendedLoading]  = useState(true);
  const [bestSellers,        setBestSellers]         = useState([]);
  const [bestSellersLoading, setBestSellersLoading]  = useState(true);
  const [newest,             setNewest]              = useState([]);
  const [newestLoading,      setNewestLoading]       = useState(true);
  const [promoProducts,      setPromoProducts]       = useState([]);
  const [promoLoading,       setPromoLoading]        = useState(true);
  const [catProducts,        setCatProducts]         = useState({});
  const [catLoading,         setCatLoading]          = useState(true);

  useEffect(() => {
    // Session-based: driven by what this visitor (guest session or, if
    // logged in, their interaction history) has actually viewed/carted.
    // Empty with nothing to base it on yet (cold start) — ProductRow
    // already hides the whole section when products is empty, so a
    // brand-new visitor simply doesn't see it.
    recommendationApi.get(10)
      .then(res => setRecommended(res.data.recommendations || []))
      .catch(() => setRecommended([]))
      .finally(() => setRecommendedLoading(false));

    productApi.bestSellers(10)
      .then(res => setBestSellers(res.data || []))
      .catch(() => setBestSellers([]))
      .finally(() => setBestSellersLoading(false));

    productApi.list({ limit: 10, sort: 'createdAt', order: 'desc' })
      .then(res => setNewest(res.data.data || []))
      .catch(() => setNewest([]))
      .finally(() => setNewestLoading(false));

    productApi.promotions(10)
      .then(res => setPromoProducts(res.data || []))
      .catch(() => setPromoProducts([]))
      .finally(() => setPromoLoading(false));
  }, []);

  useEffect(() => {
    if (categories.length === 0) return;
    setCatLoading(true);
    Promise.all(
      categories.map(cat =>
        productApi.list({ category: cat.id, limit: 8, sort: 'createdAt', order: 'desc' })
          .then(res => [cat.id, res.data.data || []])
          .catch(() => [cat.id, []])
      )
    ).then(entries => setCatProducts(Object.fromEntries(entries)))
      .finally(() => setCatLoading(false));
  }, [categories]);

  return (
    <>
      <ProductRow
        icon={Wand2}
        title={t('sections.recommended.title')}
        subtitle={t('sections.recommended.subtitle')}
        products={recommended}
        loading={recommendedLoading}
      />

      <ProductRow
        icon={TrendingUp}
        title={t('sections.bestSellers.title')}
        subtitle={t('sections.bestSellers.subtitle')}
        products={bestSellers}
        loading={bestSellersLoading}
      />

      <ProductRow
        icon={Clock}
        title={t('sections.newest.title')}
        subtitle={t('sections.newest.subtitle')}
        products={newest}
        loading={newestLoading}
      />

      <ProductRow
        icon={Percent}
        title={t('sections.promotions.title')}
        subtitle={t('sections.promotions.subtitle')}
        products={promoProducts}
        loading={promoLoading}
        viewAllTo="/promotions"
        onViewAll={() => navigate('/promotions')}
      />

      {categories.map(cat => (
        <ProductRow
          key={cat.id}
          icon={CATEGORY_ICONS[cat.name] || DEFAULT_CATEGORY_ICON}
          title={cat.name}
          products={catProducts[cat.id] || []}
          loading={catLoading}
          viewAllTo={`/?cat=${cat.id}`}
          onViewAll={() => navigate(`/?cat=${cat.id}`)}
        />
      ))}
    </>
  );
}

// ── Home page ─────────────────────────────────────────────────────────────────
export default function Home() {
  const { t } = useTranslation('home');
  const [searchParams, setSearchParams] = useSearchParams();
  const q     = searchParams.get('q') || '';
  const catId = searchParams.get('cat') ? Number(searchParams.get('cat')) : null;

  const { categories } = useCategories();
  const [products,   setProducts]   = useState([]);
  const [total,      setTotal]      = useState(0);
  const [page,       setPage]       = useState(1);
  const [loading,    setLoading]    = useState(true);
  const [loadingMore, setLoadingMore] = useState(false);

  const [filters,  setFilters]  = useState(EMPTY_FILTERS);
  const [sortValue, setSortValue] = useState('createdAt-desc');
  const [facets,        setFacets]        = useState(EMPTY_FACETS);
  const [facetsLoading, setFacetsLoading] = useState(false);

  const LIMIT = 24;
  const hasFilters = q || catId;
  const sortOption = SORT_OPTIONS.find(o => o.value === sortValue) || SORT_OPTIONS[0];

  const buildParams = useCallback((page) => {
    const params = { page, limit: LIMIT, sort: sortOption.sortBy, order: sortOption.order };
    if (q)     params.q        = q;
    if (catId) params.category = catId;
    if (filters.brand)    params.brand = filters.brand;
    if (filters.type)     params.type  = filters.type;
    if (filters.inStock)  params.inStock = true;
    if (filters.minPrice !== '') params.minPrice = filters.minPrice;
    if (filters.maxPrice !== '') params.maxPrice = filters.maxPrice;
    if (Object.keys(filters.attrs).length > 0) params.attr = filters.attrs;
    return params;
  }, [q, catId, filters, sortOption]);

  // Reset filters/sort whenever the search context itself changes (new
  // query or category) — facets for the new scope are about to be reloaded
  // anyway, and last context's brand/attribute picks rarely still apply.
  useEffect(() => { setFilters(EMPTY_FILTERS); setSortValue('createdAt-desc'); }, [q, catId]);

  // Facets describe what's filterable for this q/category/filter scope —
  // reloaded whenever any filter changes too (not just q/category), so
  // e.g. picking color=grey narrows the Type dropdown to types that
  // actually have a grey item (each facet excludes its own filter when
  // computed — see ProductRepository::getFacets — so it stays selectable).
  useEffect(() => {
    if (!hasFilters) return;
    setFacetsLoading(true);
    const params = buildParams(1);
    delete params.page;
    delete params.limit;
    delete params.sort;
    delete params.order;
    productApi.facets(params)
      .then(res => setFacets(res.data || EMPTY_FACETS))
      .catch(() => setFacets(EMPTY_FACETS))
      .finally(() => setFacetsLoading(false));
  }, [hasFilters, buildParams]);

  // Fetch products whenever query/category/filters/sort changes (only needed for the filtered view)
  useEffect(() => {
    if (!hasFilters) { setLoading(false); return; }
    setLoading(true);
    setPage(1);

    productApi.list(buildParams(1))
      .then(res => {
        setProducts(res.data.data || []);
        setTotal(res.data.total  || 0);
      })
      .catch(() => { setProducts([]); setTotal(0); })
      .finally(() => setLoading(false));
  }, [hasFilters, buildParams]);

  // Load more products (next page)
  const loadMore = useCallback(async () => {
    const nextPage = page + 1;
    setLoadingMore(true);
    try {
      const res = await productApi.list(buildParams(nextPage));
      setProducts(prev => [...prev, ...(res.data.data || [])]);
      setPage(nextPage);
    } finally {
      setLoadingMore(false);
    }
  }, [page, buildParams]);

  const clearFilters = () => { setSearchParams({}); setFilters(EMPTY_FILTERS); setSortValue('createdAt-desc'); };

  const hasMore = products.length < total;
  const activeFilterCount = countActiveFilters(filters);

  // Active category name for header display
  const activeCatName = (() => {
    if (!catId) return null;
    for (const parent of categories) {
      if (parent.id === catId) return parent.name;
      for (const child of parent.children) {
        if (child.id === catId) return child.name;
      }
    }
    return null;
  })();

  return (
    <div className="h-page">
      <Navbar />

      <main className="h-main">
        <div className="h-container">

          {!hasFilters ? (
            <HomeSections categories={categories} />
          ) : (
            <div className="h-filtered-layout">
              <FilterSidebar
                facets={facets}
                facetsLoading={facetsLoading}
                filters={filters}
                onChange={setFilters}
                sortValue={sortValue}
                onSortChange={setSortValue}
              />

              <div className="h-results">
                {/* Active filter badge */}
                <div className="h-filter-row">
                  {q     && <Badge tone="primary" variant="soft" size="md">{t('filterRow.search')} <strong>"{q}"</strong></Badge>}
                  {catId && activeCatName && <Badge tone="primary" variant="soft" size="md">{t('filterRow.category')} <strong>{activeCatName}</strong></Badge>}
                  {activeFilterCount > 0 && <Badge tone="neutral" variant="soft" size="md">{t('filterRow.filtersActive', { count: activeFilterCount })}</Badge>}
                  <span className="h-filter-count">{t('filterRow.productsFound', { count: total })}</span>
                  <Button variant="ghost" size="sm" className="h-filter-clear" onClick={clearFilters}>✕ {t('filterRow.clear')}</Button>
                </div>

                {/* Product grid */}
                {loading ? (
                  <div className="h-grid">
                    {Array.from({ length: 12 }).map((_, i) => <SkeletonCard key={i} />)}
                  </div>
                ) : products.length === 0 ? (
                  <div className="h-empty">
                    <div className="h-empty-icon"><Search size={28} /></div>
                    <h3>{t('empty.title')}</h3>
                    <p>{t('empty.subtitle')}</p>
                    <Button variant="outline" onClick={clearFilters}>{t('empty.viewAllProducts')}</Button>
                  </div>
                ) : (
                  <>
                    <div className="h-grid">
                      {products.map(p => <ProductCard key={p.id} product={p} />)}
                    </div>

                    {hasMore && (
                      <div className="h-load-more">
                        <Button
                          variant="outline"
                          size="lg"
                          className="h-btn-loadmore"
                          onClick={loadMore}
                          disabled={loadingMore}
                        >
                          {loadingMore ? t('loading') : t('loadMore', { count: total - products.length })}
                        </Button>
                      </div>
                    )}
                  </>
                )}
              </div>
            </div>
          )}

        </div>
      </main>
    </div>
  );
}
