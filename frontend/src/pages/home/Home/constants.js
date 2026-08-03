export const EMPTY_FACETS = {
  brands: [],
  productTypes: [],
  priceRange: { min: 0, max: 0 },
  attributes: [],
};

export const EMPTY_FILTERS = {
  brand: '',
  type: '',
  attrs: {},
  inStock: false,
  minPrice: '',
  maxPrice: '',
};

export const SORT_OPTIONS = [
  { value: 'createdAt-desc', labelKey: 'sort.newest', sortBy: 'createdAt', order: 'desc' },
  { value: 'price-asc', labelKey: 'sort.priceAsc', sortBy: 'price', order: 'asc' },
  { value: 'price-desc', labelKey: 'sort.priceDesc', sortBy: 'price', order: 'desc' },
  { value: 'name-asc', labelKey: 'sort.nameAsc', sortBy: 'name', order: 'asc' },
  { value: 'rating-desc', labelKey: 'sort.ratingDesc', sortBy: 'rating', order: 'desc' },
  {
    value: 'popularity-desc',
    labelKey: 'sort.popularityDesc',
    sortBy: 'popularity',
    order: 'desc',
  },
];

export function countActiveFilters(filters) {
  return (
    (filters.brand ? 1 : 0) +
    (filters.type ? 1 : 0) +
    Object.keys(filters.attrs).length +
    (filters.inStock ? 1 : 0) +
    (filters.minPrice !== '' ? 1 : 0) +
    (filters.maxPrice !== '' ? 1 : 0)
  );
}
