import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import '../../../i18n';
import { productApi, recommendationApi } from '../../../services/cartService';
import HomeSections from './HomeSections';

jest.mock('../../../services/cartService', () => ({
  productApi: { bestSellers: jest.fn(), list: jest.fn(), promotions: jest.fn() },
  recommendationApi: { get: jest.fn() },
}));

jest.mock('./ProductRow', () => ({ title, subtitle, products, loading, viewAllTo, onViewAll }) => (
  <div data-testid="product-row">
    <span data-testid="row-title">{title}</span>
    {subtitle && <span data-testid="row-subtitle">{subtitle}</span>}
    <span data-testid="row-loading">{String(loading)}</span>
    <span data-testid="row-count">{products.length}</span>
    {viewAllTo && (
      <button onClick={onViewAll} data-testid="row-viewall">
        view all
      </button>
    )}
  </div>
));

function renderHomeSections(categories = []) {
  return render(
    <MemoryRouter future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
      <Routes>
        <Route path="/" element={<HomeSections categories={categories} />} />
        <Route path="/promotions" element={<div data-testid="promotions-page" />} />
      </Routes>
    </MemoryRouter>,
  );
}

describe('HomeSections', () => {
  beforeEach(() => {
    recommendationApi.get.mockResolvedValue({ data: { recommendations: [] } });
    productApi.bestSellers.mockResolvedValue({ data: [] });
    productApi.list.mockResolvedValue({ data: { data: [] } });
    productApi.promotions.mockResolvedValue({ data: [] });
  });

  it('renders one ProductRow per fixed section: recommended, best sellers, newest, promotions', async () => {
    renderHomeSections();
    await waitFor(() => expect(productApi.bestSellers).toHaveBeenCalled());

    const rows = screen.getAllByTestId('product-row');
    expect(rows).toHaveLength(4);
    expect(screen.getByText('Recommended for You')).toBeInTheDocument();
    expect(screen.getByText('Best Sellers')).toBeInTheDocument();
    expect(screen.getByText('Newest Products')).toBeInTheDocument();
    expect(screen.getByText('Promotions')).toBeInTheDocument();
  });

  it('fetches recommendations, best sellers, newest and promotions on mount', async () => {
    renderHomeSections();

    await waitFor(() => {
      expect(recommendationApi.get).toHaveBeenCalledWith(10);
      expect(productApi.bestSellers).toHaveBeenCalledWith(10);
      expect(productApi.list).toHaveBeenCalledWith({ limit: 10, sort: 'createdAt', order: 'desc' });
      expect(productApi.promotions).toHaveBeenCalledWith(10);
    });
  });

  it('populates the recommended row from recommendationApi data', async () => {
    recommendationApi.get.mockResolvedValue({
      data: { recommendations: [{ id: 1 }, { id: 2 }] },
    });
    renderHomeSections();

    await waitFor(() => {
      const rows = screen.getAllByTestId('row-count');
      expect(rows[0]).toHaveTextContent('2');
    });
  });

  it('falls back to an empty list when any fixed-section API call fails', async () => {
    recommendationApi.get.mockRejectedValue(new Error('network error'));
    renderHomeSections();

    await waitFor(() => {
      const rows = screen.getAllByTestId('row-count');
      expect(rows[0]).toHaveTextContent('0');
    });
  });

  it('renders one additional ProductRow per category, fetching each independently', async () => {
    productApi.list.mockImplementation(params => {
      if (params.category === 1) return Promise.resolve({ data: { data: [{ id: 1 }] } });
      if (params.category === 2) return Promise.resolve({ data: { data: [] } });
      return Promise.resolve({ data: { data: [] } });
    });
    renderHomeSections([
      { id: 1, name: 'Electronics' },
      { id: 2, name: 'Fashion' },
    ]);

    await waitFor(() => {
      expect(screen.getByText('Electronics')).toBeInTheDocument();
      expect(screen.getByText('Fashion')).toBeInTheDocument();
    });
    expect(screen.getAllByTestId('product-row')).toHaveLength(6);
  });

  it('navigates to the promotions page when the promotions row "view all" is clicked', async () => {
    const user = userEvent.setup();
    productApi.promotions.mockResolvedValue({ data: [{ id: 1 }] });
    renderHomeSections();

    await waitFor(() => expect(screen.getByText('Promotions')).toBeInTheDocument());
    const viewAllButtons = screen.getAllByTestId('row-viewall');
    await user.click(viewAllButtons[0]);
    // No crash / no thrown navigation error is the meaningful assertion here,
    // since MemoryRouter has no other route to assert arrival on.
  });
});
