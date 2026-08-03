import React, { useState, useEffect, useCallback } from 'react';
import { useSearchParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Search } from 'lucide-react';
import Navbar from '../../components/Navbar';
import ProductCard, { SkeletonCard } from '../../components/ProductCard';
import { Button, Badge } from '../../components/ui';
import { productApi } from '../../services/cartService';
import { useCategories } from '../../context/CategoryContext';
import FilterSidebar from './Home/FilterSidebar';
import HomeSections from './Home/HomeSections';
import { EMPTY_FACETS, EMPTY_FILTERS, SORT_OPTIONS, countActiveFilters } from './Home/constants';
import './Home.css';

// ── Home page ─────────────────────────────────────────────────────────────────
export default function Home() {
  const { t } = useTranslation('home');
  const [searchParams, setSearchParams] = useSearchParams();
  const q = searchParams.get('q') || '';
  const catId = searchParams.get('cat') ? Number(searchParams.get('cat')) : null;

  const { categories } = useCategories();
  const [products, setProducts] = useState([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [loadingMore, setLoadingMore] = useState(false);

  const [filters, setFilters] = useState(EMPTY_FILTERS);
  const [sortValue, setSortValue] = useState('createdAt-desc');
  const [facets, setFacets] = useState(EMPTY_FACETS);
  const [facetsLoading, setFacetsLoading] = useState(false);

  const LIMIT = 24;
  const hasFilters = q || catId;
  const sortOption = SORT_OPTIONS.find(o => o.value === sortValue) || SORT_OPTIONS[0];

  const buildParams = useCallback(
    page => {
      const params = { page, limit: LIMIT, sort: sortOption.sortBy, order: sortOption.order };
      if (q) params.q = q;
      if (catId) params.category = catId;
      if (filters.brand) params.brand = filters.brand;
      if (filters.type) params.type = filters.type;
      if (filters.inStock) params.inStock = true;
      if (filters.minPrice !== '') params.minPrice = filters.minPrice;
      if (filters.maxPrice !== '') params.maxPrice = filters.maxPrice;
      if (Object.keys(filters.attrs).length > 0) params.attr = filters.attrs;
      return params;
    },
    [q, catId, filters, sortOption],
  );

  // Reset filters/sort whenever the search context itself changes (new
  // query or category) — facets for the new scope are about to be reloaded
  // anyway, and last context's brand/attribute picks rarely still apply.
  useEffect(() => {
    setFilters(EMPTY_FILTERS);
    setSortValue('createdAt-desc');
  }, [q, catId]);

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
    productApi
      .facets(params)
      .then(res => setFacets(res.data || EMPTY_FACETS))
      .catch(() => setFacets(EMPTY_FACETS))
      .finally(() => setFacetsLoading(false));
  }, [hasFilters, buildParams]);

  // Fetch products whenever query/category/filters/sort changes (only needed for the filtered view)
  useEffect(() => {
    if (!hasFilters) {
      setLoading(false);
      return;
    }
    setLoading(true);
    setPage(1);

    productApi
      .list(buildParams(1))
      .then(res => {
        setProducts(res.data.data || []);
        setTotal(res.data.total || 0);
      })
      .catch(() => {
        setProducts([]);
        setTotal(0);
      })
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

  const clearFilters = () => {
    setSearchParams({});
    setFilters(EMPTY_FILTERS);
    setSortValue('createdAt-desc');
  };

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
                  {q && (
                    <Badge tone="primary" variant="soft" size="md">
                      {t('filterRow.search')} <strong>"{q}"</strong>
                    </Badge>
                  )}
                  {catId && activeCatName && (
                    <Badge tone="primary" variant="soft" size="md">
                      {t('filterRow.category')} <strong>{activeCatName}</strong>
                    </Badge>
                  )}
                  {activeFilterCount > 0 && (
                    <Badge tone="neutral" variant="soft" size="md">
                      {t('filterRow.filtersActive', { count: activeFilterCount })}
                    </Badge>
                  )}
                  <span className="h-filter-count">
                    {t('filterRow.productsFound', { count: total })}
                  </span>
                  <Button
                    variant="ghost"
                    size="sm"
                    className="h-filter-clear"
                    onClick={clearFilters}
                  >
                    ✕ {t('filterRow.clear')}
                  </Button>
                </div>

                {/* Product grid */}
                {loading ? (
                  <div className="h-grid">
                    {Array.from({ length: 12 }).map((_, i) => (
                      <SkeletonCard key={i} />
                    ))}
                  </div>
                ) : products.length === 0 ? (
                  <div className="h-empty">
                    <div className="h-empty-icon">
                      <Search size={28} />
                    </div>
                    <h3>{t('empty.title')}</h3>
                    <p>{t('empty.subtitle')}</p>
                    <Button variant="outline" onClick={clearFilters}>
                      {t('empty.viewAllProducts')}
                    </Button>
                  </div>
                ) : (
                  <>
                    <div className="h-grid">
                      {products.map(p => (
                        <ProductCard key={p.id} product={p} />
                      ))}
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
                          {loadingMore
                            ? t('loading')
                            : t('loadMore', { count: total - products.length })}
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
