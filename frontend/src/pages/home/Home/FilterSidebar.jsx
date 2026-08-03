import React from 'react';
import { useTranslation } from 'react-i18next';
import { SlidersHorizontal } from 'lucide-react';
import { Button } from '../../../components/ui';
import { EMPTY_FILTERS, SORT_OPTIONS, countActiveFilters } from './constants';

// ── Intelligent filter sidebar: brand/type/price/stock + dynamic feature
// filters (only the attributes that actually occur for products in scope,
// narrowed further to the selected type once one is picked) ──────────────
export default function FilterSidebar({
  facets,
  facetsLoading,
  filters,
  onChange,
  sortValue,
  onSortChange,
}) {
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
      values: filters.type ? attr.valuesByType[filters.type] || [] : attr.values,
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
        <h3>
          <SlidersHorizontal size={16} /> {t('filters.title')}
        </h3>
        {activeCount > 0 && (
          <Button
            variant="ghost"
            size="sm"
            className="h-sidebar-reset"
            onClick={() => {
              onChange(EMPTY_FILTERS);
              onSortChange('createdAt-desc');
            }}
          >
            {t('filters.reset', { count: activeCount })}
          </Button>
        )}
      </div>

      <div className="h-filter-group">
        <h4>{t('filters.sortBy')}</h4>
        <select value={sortValue} onChange={e => onSortChange(e.target.value)}>
          {SORT_OPTIONS.map(o => (
            <option key={o.value} value={o.value}>
              {t(o.labelKey)}
            </option>
          ))}
        </select>
      </div>

      {facetsLoading ? (
        <p className="h-sidebar-loading">{t('filters.loading')}</p>
      ) : (
        <>
          {facets.brands.length > 0 && (
            <div className="h-filter-group">
              <h4>{t('filters.brand')}</h4>
              <select
                value={filters.brand}
                onChange={e => onChange({ ...filters, brand: e.target.value })}
              >
                <option value="">{t('filters.allBrands')}</option>
                {facets.brands.map(b => (
                  <option key={b.id} value={b.id}>
                    {b.name} ({b.count})
                  </option>
                ))}
              </select>
            </div>
          )}

          {facets.productTypes.length > 1 && (
            <div className="h-filter-group">
              <h4>{t('filters.type')}</h4>
              <select
                value={filters.type}
                onChange={e => onChange({ ...filters, type: e.target.value, attrs: {} })}
              >
                <option value="">{t('filters.allTypes')}</option>
                {facets.productTypes.map(pt => (
                  <option key={pt.id} value={pt.id}>
                    {pt.name} ({pt.count})
                  </option>
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
              <h4>
                {attr.name}
                {attr.unit ? ` (${attr.unit})` : ''}
              </h4>
              <select
                value={filters.attrs[attr.slug] || ''}
                onChange={e => setAttr(attr.slug, e.target.value)}
              >
                <option value="">{t('filters.any')}</option>
                {attr.values.map(v => (
                  <option key={v.value} value={v.value}>
                    {String(v.value)} ({v.count})
                  </option>
                ))}
              </select>
            </div>
          ))}
        </>
      )}
    </aside>
  );
}
