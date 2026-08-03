import api from './api';

export const cartApi = {
  getCart: () => api.get('/cart'),
  addItem: (productId, quantity = 1) => api.post('/cart/items', { productId, quantity }),
  updateItem: (itemId, quantity) => api.put(`/cart/items/${itemId}`, { quantity }),
  removeItem: itemId => api.delete(`/cart/items/${itemId}`),
  clearCart: () => api.delete('/cart'),
  syncCart: (items, strategy = 'merge') => api.post('/cart/sync', { items, strategy }),
};

export const orderApi = {
  checkout: data => api.post('/orders/checkout', data),
  getOrders: (page = 1, limit = 10) => api.get(`/orders?page=${page}&limit=${limit}`),
  getOrder: id => api.get(`/orders/${id}`),
  cancel: id => api.post(`/orders/${id}/cancel`),
};

export const addressApi = {
  list: () => api.get('/profile/addresses'),
  create: data => api.post('/profile/addresses', data),
  update: (id, data) => api.put(`/profile/addresses/${id}`, data),
  remove: id => api.delete(`/profile/addresses/${id}`),
};

// Flattens { attr: { color: 'Black', ram: '8GB' } } into { 'attr[color]': 'Black', 'attr[ram]': '8GB' }
// so axios's plain-key serializer produces attr%5Bcolor%5D=Black — exactly
// the bracket-array query format Symfony's request->query->all('attr') parses.
function withFlatAttrs(params) {
  if (!params?.attr || typeof params.attr !== 'object') return params;
  const { attr, ...rest } = params;
  const flat = { ...rest };
  Object.entries(attr).forEach(([slug, value]) => {
    if (value !== null && value !== undefined && value !== '') {
      flat[`attr[${slug}]`] = value;
    }
  });
  return flat;
}

export const productApi = {
  list: params => api.get('/products', { params: withFlatAttrs(params) }),
  get: id => api.get(`/products/${id}`),
  autocomplete: (q, config) => api.get('/products/autocomplete', { params: { q }, ...config }),
  bestSellers: (limit = 10) => api.get('/products/best-sellers', { params: { limit } }),
  promotions: (limit = 20) => api.get('/products/promotions', { params: { limit } }),
  activity: id => api.get(`/products/${id}/activity`),
  facets: params => api.get('/products/facets', { params: withFlatAttrs(params) }),
};

export const interactionApi = {
  // Authenticated-only — the backend rejects this without a JWT.
  track: (productId, type, value = null) =>
    api.post(`/products/${productId}/interact`, { type, value }),
};

export const guestEventApi = {
  // Public — tracked under the X-Session-Id header api.js attaches to every request.
  track: (productId, type) => api.post('/guest/events', { productId, type }),
};

export const recommendationApi = {
  get: (limit = 8) => api.get('/recommendations', { params: { limit } }),
  forProduct: (productId, limit = 8) =>
    api.get(`/recommendations/product/${productId}`, { params: { limit } }),
};

export const categoryApi = {
  list: () => api.get('/categories'),
  get: id => api.get(`/categories/${id}`),
  products: (id, params) => api.get(`/categories/${id}/products`, { params }),
};

export const adminProductApi = {
  create: data => api.post('/admin/products', data),
  update: (id, data) => api.put(`/admin/products/${id}`, data),
  remove: id => api.delete(`/admin/products/${id}`),
  updateStock: (id, data) => api.patch(`/admin/products/${id}/stock`, data),
};

export const productTypeApi = {
  list: () => api.get('/admin/product-types'),
  create: data => api.post('/admin/product-types', data),
  rename: (typeId, data) => api.put(`/admin/product-types/${typeId}`, data),
  addAttribute: (typeId, data) => api.post(`/admin/product-types/${typeId}/attributes`, data),
  removeAttribute: (typeId, attributeId) =>
    api.delete(`/admin/product-types/${typeId}/attributes/${attributeId}`),
  remove: typeId => api.delete(`/admin/product-types/${typeId}`),
  suggestAttributes: (name, existingNames = []) =>
    api.post('/admin/product-types/suggest-attributes', { name, existingNames }),
};

export const adminCategoryApi = {
  create: data => api.post('/admin/categories', data),
  update: (id, data) => api.put(`/admin/categories/${id}`, data),
  remove: id => api.delete(`/admin/categories/${id}`),
};

export const dashboardApi = {
  get: (lowStockThreshold = 5) => api.get('/admin/dashboard', { params: { lowStockThreshold } }),
};

// Local LLM inference can easily take 1-2+ minutes on CPU-only hardware —
// well past the shared client's default timeout (api.js) — so these calls
// get their own, much longer allowance.
const ANALYZE_TIMEOUT = 180000;

export const adminAnalyticsApi = {
  analyzeProduct: id =>
    api.post(`/admin/analytics/products/${id}/analyze`, null, { timeout: ANALYZE_TIMEOUT }),
  analyzeCategory: id =>
    api.post(`/admin/analytics/categories/${id}/analyze`, null, { timeout: ANALYZE_TIMEOUT }),
  analyzeBrand: id =>
    api.post(`/admin/analytics/brands/${id}/analyze`, null, { timeout: ANALYZE_TIMEOUT }),
  analyzeProductType: id =>
    api.post(`/admin/analytics/product-types/${id}/analyze`, null, { timeout: ANALYZE_TIMEOUT }),
};

export const adminPromotionApi = {
  list: (page = 1, limit = 20) => api.get('/admin/promotions', { params: { page, limit } }),
  create: data => api.post('/admin/promotions', data),
  end: id => api.patch(`/admin/promotions/${id}/end`),
  remove: id => api.delete(`/admin/promotions/${id}`),
};

export const profileApi = {
  get: () => api.get('/profile'),
  update: data => api.put('/profile', data),
  changePassword: data => api.put('/profile/password', data),
  requestDeletion: () => api.delete('/profile'),
};

export const favoriteApi = {
  list: (page = 1, limit = 200) => api.get(`/profile/favorites?page=${page}&limit=${limit}`),
  add: productId => api.post('/profile/favorites', { productId }),
  remove: productId => api.delete(`/profile/favorites/${productId}`),
};

export const brandApi = {
  list: (page = 1, limit = 20) => api.get(`/brands?page=${page}&limit=${limit}`),
  get: id => api.get(`/brands/${id}`),
};

export const adminBrandApi = {
  create: data => api.post('/admin/brands', data),
  update: (id, data) => api.put(`/admin/brands/${id}`, data),
  remove: id => api.delete(`/admin/brands/${id}`),
  uploadImage: file => {
    const fd = new FormData();
    fd.append('image', file);
    return api.post('/admin/brands/upload-image', fd);
  },
};

export const reviewApi = {
  list: (productId, page = 1, limit = 10) =>
    api.get(`/products/${productId}/reviews?page=${page}&limit=${limit}`),
  create: (productId, data) => api.post(`/products/${productId}/reviews`, data),
  remove: reviewId => api.delete(`/reviews/${reviewId}`),
  myReviews: () => api.get('/profile/reviews'),
};

export const adminOrderApi = {
  getOrders: (status, page = 1, limit = 20) => {
    const params = new URLSearchParams({ page: String(page), limit: String(limit) });
    if (status) params.set('status', status);
    return api.get(`/admin/orders?${params}`);
  },
  getOrder: id => api.get(`/admin/orders/${id}`),
  updateStatus: (id, status) => api.put(`/admin/orders/${id}/status`, { status }),
};
