import { productApi } from '../services/cartService';

const PAGE_LIMIT = 50;

export async function fetchAllProducts() {
  const first = await productApi.list({
    page: 1,
    limit: PAGE_LIMIT,
    sort: 'createdAt',
    order: 'desc',
  });
  const firstPage = first.data.data || [];
  const total = first.data.total || 0;
  const totalPages = Math.ceil(total / PAGE_LIMIT);

  if (totalPages <= 1) return firstPage;

  const remainingPages = await Promise.all(
    Array.from({ length: totalPages - 1 }, (_, i) =>
      productApi
        .list({ page: i + 2, limit: PAGE_LIMIT, sort: 'createdAt', order: 'desc' })
        .then(res => res.data.data || []),
    ),
  );

  return remainingPages.reduce((all, page) => all.concat(page), firstPage);
}
