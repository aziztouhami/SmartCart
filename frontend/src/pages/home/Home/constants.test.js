import { EMPTY_FACETS, EMPTY_FILTERS, SORT_OPTIONS, countActiveFilters } from './constants';

describe('Home constants', () => {
  it('exposes an empty facets shape', () => {
    expect(EMPTY_FACETS).toEqual({
      brands: [],
      productTypes: [],
      priceRange: { min: 0, max: 0 },
      attributes: [],
    });
  });

  it('exposes an empty filters shape', () => {
    expect(EMPTY_FILTERS).toEqual({
      brand: '',
      type: '',
      attrs: {},
      inStock: false,
      minPrice: '',
      maxPrice: '',
    });
  });

  it('exposes 6 sort options each with a value/labelKey/sortBy/order', () => {
    expect(SORT_OPTIONS).toHaveLength(6);
    SORT_OPTIONS.forEach(o => {
      expect(o).toHaveProperty('value');
      expect(o).toHaveProperty('labelKey');
      expect(o).toHaveProperty('sortBy');
      expect(o).toHaveProperty('order');
    });
  });
});

describe('countActiveFilters', () => {
  it('returns 0 for the empty filters', () => {
    expect(countActiveFilters(EMPTY_FILTERS)).toBe(0);
  });

  it('counts brand, type, inStock, minPrice and maxPrice as 1 each', () => {
    expect(
      countActiveFilters({
        brand: '5',
        type: '2',
        attrs: {},
        inStock: true,
        minPrice: '10',
        maxPrice: '100',
      }),
    ).toBe(5);
  });

  it('counts each attribute key separately', () => {
    expect(
      countActiveFilters({
        ...EMPTY_FILTERS,
        attrs: { color: 'red', size: 'M' },
      }),
    ).toBe(2);
  });

  it('does not count an empty-string minPrice/maxPrice', () => {
    expect(countActiveFilters({ ...EMPTY_FILTERS, minPrice: '', maxPrice: '' })).toBe(0);
  });

  it('counts a numeric 0 minPrice as active since it is not the empty string', () => {
    expect(countActiveFilters({ ...EMPTY_FILTERS, minPrice: 0 })).toBe(1);
  });
});
