import api from './api';
import { productApi, cartApi, orderApi, adminAnalyticsApi } from './cartService';

jest.mock('./api', () => ({
  __esModule: true,
  default: { get: jest.fn(), post: jest.fn(), put: jest.fn(), delete: jest.fn(), patch: jest.fn() },
}));

describe('productApi.list', () => {
  beforeEach(() => jest.clearAllMocks());

  it('passes plain filters straight through', () => {
    productApi.list({ category: 3, page: 2 });

    expect(api.get).toHaveBeenCalledWith('/products', {
      params: { category: 3, page: 2 },
    });
  });

  it('flattens an `attr` object into attr[slug] bracket-notation keys', () => {
    productApi.list({ category: 3, attr: { color: 'Black', ram: '8GB' } });

    expect(api.get).toHaveBeenCalledWith('/products', {
      params: { category: 3, 'attr[color]': 'Black', 'attr[ram]': '8GB' },
    });
  });

  it('drops attr entries that are null, undefined, or an empty string', () => {
    productApi.list({ attr: { color: 'Black', ram: '', size: null, weight: undefined } });

    expect(api.get).toHaveBeenCalledWith('/products', {
      params: { 'attr[color]': 'Black' },
    });
  });

  it('passes params through unchanged when there is no attr key', () => {
    productApi.list(undefined);
    expect(api.get).toHaveBeenCalledWith('/products', { params: undefined });
  });
});

describe('productApi.facets', () => {
  it('also flattens attr filters (shares withFlatAttrs with .list)', () => {
    productApi.facets({ attr: { color: 'Red' } });
    expect(api.get).toHaveBeenCalledWith('/products/facets', {
      params: { 'attr[color]': 'Red' },
    });
  });
});

describe('cartApi', () => {
  beforeEach(() => jest.clearAllMocks());

  it('addItem posts productId and quantity', () => {
    cartApi.addItem(42, 3);
    expect(api.post).toHaveBeenCalledWith('/cart/items', { productId: 42, quantity: 3 });
  });

  it('addItem defaults quantity to 1', () => {
    cartApi.addItem(42);
    expect(api.post).toHaveBeenCalledWith('/cart/items', { productId: 42, quantity: 1 });
  });

  it('syncCart defaults to the merge strategy', () => {
    const items = [{ productId: 1, quantity: 2 }];
    cartApi.syncCart(items);
    expect(api.post).toHaveBeenCalledWith('/cart/sync', { items, strategy: 'merge' });
  });
});

describe('orderApi.getOrders', () => {
  it('builds the paginated query string with sensible defaults', () => {
    orderApi.getOrders();
    expect(api.get).toHaveBeenCalledWith('/orders?page=1&limit=10');
  });
});

describe('adminAnalyticsApi', () => {
  beforeEach(() => jest.clearAllMocks());

  it('analyzeProduct posts to the product analyze endpoint with an extended timeout', () => {
    adminAnalyticsApi.analyzeProduct(7);
    expect(api.post).toHaveBeenCalledWith('/admin/analytics/products/7/analyze', null, {
      timeout: 180000,
    });
  });

  it('analyzeCategory posts to the category analyze endpoint with an extended timeout', () => {
    adminAnalyticsApi.analyzeCategory(3);
    expect(api.post).toHaveBeenCalledWith('/admin/analytics/categories/3/analyze', null, {
      timeout: 180000,
    });
  });

  it('analyzeBrand posts to the brand analyze endpoint with an extended timeout', () => {
    adminAnalyticsApi.analyzeBrand(9);
    expect(api.post).toHaveBeenCalledWith('/admin/analytics/brands/9/analyze', null, {
      timeout: 180000,
    });
  });

  it('analyzeProductType posts to the product-types analyze endpoint with an extended timeout', () => {
    adminAnalyticsApi.analyzeProductType(2);
    expect(api.post).toHaveBeenCalledWith('/admin/analytics/product-types/2/analyze', null, {
      timeout: 180000,
    });
  });
});
