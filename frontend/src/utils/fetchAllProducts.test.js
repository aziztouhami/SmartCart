import { productApi } from '../services/cartService';
import { fetchAllProducts } from './fetchAllProducts';

jest.mock('../services/cartService', () => ({
  productApi: { list: jest.fn() },
}));

describe('fetchAllProducts', () => {
  beforeEach(() => jest.clearAllMocks());

  it('fetches only the first page when total fits within one page', async () => {
    productApi.list.mockResolvedValue({
      data: { data: [{ id: 1 }, { id: 2 }], total: 2 },
    });

    const result = await fetchAllProducts();

    expect(productApi.list).toHaveBeenCalledTimes(1);
    expect(productApi.list).toHaveBeenCalledWith({
      page: 1,
      limit: 50,
      sort: 'createdAt',
      order: 'desc',
    });
    expect(result).toEqual([{ id: 1 }, { id: 2 }]);
  });

  it('fetches and concatenates all remaining pages in order when there is more than one page', async () => {
    const page1 = Array.from({ length: 50 }, (_, i) => ({ id: i + 1 }));
    const page2 = Array.from({ length: 50 }, (_, i) => ({ id: i + 51 }));
    const page3 = [{ id: 101 }];

    productApi.list.mockImplementation(({ page }) => {
      if (page === 1) return Promise.resolve({ data: { data: page1, total: 101 } });
      if (page === 2) return Promise.resolve({ data: { data: page2, total: 101 } });
      if (page === 3) return Promise.resolve({ data: { data: page3, total: 101 } });
      return Promise.resolve({ data: { data: [], total: 101 } });
    });

    const result = await fetchAllProducts();

    expect(productApi.list).toHaveBeenCalledTimes(3);
    expect(productApi.list).toHaveBeenNthCalledWith(2, {
      page: 2,
      limit: 50,
      sort: 'createdAt',
      order: 'desc',
    });
    expect(productApi.list).toHaveBeenNthCalledWith(3, {
      page: 3,
      limit: 50,
      sort: 'createdAt',
      order: 'desc',
    });
    expect(result).toHaveLength(101);
    expect(result).toEqual([...page1, ...page2, ...page3]);
  });

  it('returns an empty array when the first page has no data', async () => {
    productApi.list.mockResolvedValue({ data: { data: [], total: 0 } });

    const result = await fetchAllProducts();

    expect(result).toEqual([]);
    expect(productApi.list).toHaveBeenCalledTimes(1);
  });

  it('tolerates a missing `data.data` or `data.total` in the response', async () => {
    productApi.list.mockResolvedValue({ data: {} });

    const result = await fetchAllProducts();

    expect(result).toEqual([]);
    expect(productApi.list).toHaveBeenCalledTimes(1);
  });
});
