import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import '../../i18n';
import { productApi } from '../../services/cartService';
import { useCategories } from '../../context/CategoryContext';
import Home from './Home';

jest.mock('../../services/cartService', () => ({
  productApi: { list: jest.fn(), facets: jest.fn() },
}));

jest.mock('../../context/CategoryContext', () => ({
  useCategories: jest.fn(),
}));

jest.mock('../../components/Navbar', () => () => <div data-testid="navbar" />);

jest.mock('../../components/ProductCard', () => ({
  __esModule: true,
  default: ({ product }) => <div data-testid="product-card">{product.name}</div>,
  SkeletonCard: () => <div data-testid="skeleton-card" />,
}));

jest.mock('./Home/HomeSections', () => ({ categories }) => (
  <div data-testid="home-sections">{categories.length} categories</div>
));

jest.mock('./Home/FilterSidebar', () => ({ filters, onChange, sortValue, onSortChange }) => (
  <div data-testid="filter-sidebar">
    <button data-testid="set-instock" onClick={() => onChange({ ...filters, inStock: true })}>
      set in stock
    </button>
    <button data-testid="set-sort" onClick={() => onSortChange('price-asc')}>
      {sortValue}
    </button>
  </div>
));

function renderHome(route = '/') {
  return render(
    <MemoryRouter
      initialEntries={[route]}
      future={{ v7_startTransition: true, v7_relativeSplatPath: true }}
    >
      <Routes>
        <Route path="/" element={<Home />} />
      </Routes>
    </MemoryRouter>,
  );
}

describe('Home page', () => {
  beforeEach(() => {
    useCategories.mockReturnValue({ categories: [] });
    productApi.list.mockResolvedValue({ data: { data: [], total: 0 } });
    productApi.facets.mockResolvedValue({
      data: { brands: [], productTypes: [], priceRange: { min: 0, max: 0 }, attributes: [] },
    });
  });

  it('shows the sectioned homepage (no filters) when there is no query or category', () => {
    renderHome('/');
    expect(screen.getByTestId('home-sections')).toBeInTheDocument();
    expect(screen.queryByTestId('filter-sidebar')).not.toBeInTheDocument();
  });

  it('switches to the filtered layout when a search query is present', async () => {
    renderHome('/?q=phone');
    expect(await screen.findByTestId('filter-sidebar')).toBeInTheDocument();
    expect(screen.queryByTestId('home-sections')).not.toBeInTheDocument();
  });

  it('switches to the filtered layout when a category is present', async () => {
    renderHome('/?cat=5');
    expect(await screen.findByTestId('filter-sidebar')).toBeInTheDocument();
  });

  it('fetches products with the query text on the filtered view', async () => {
    renderHome('/?q=phone');
    await waitFor(() =>
      expect(productApi.list).toHaveBeenCalledWith(
        expect.objectContaining({ q: 'phone', page: 1, limit: 24 }),
      ),
    );
  });

  it('shows the search badge with the query text', async () => {
    renderHome('/?q=phone');
    expect(await screen.findByText('Search:')).toBeInTheDocument();
    expect(screen.getByText('"phone"')).toBeInTheDocument();
  });

  it('renders fetched products in the grid once loaded', async () => {
    productApi.list.mockResolvedValue({
      data: { data: [{ id: 1, name: 'Widget' }], total: 1 },
    });
    renderHome('/?q=widget');
    expect(await screen.findByText('Widget')).toBeInTheDocument();
  });

  it('shows the empty state when there are no matching products', async () => {
    renderHome('/?q=nonexistent');
    expect(await screen.findByText('No products found')).toBeInTheDocument();
  });

  it('shows a Load More button when more products remain, and fetches the next page on click', async () => {
    const user = userEvent.setup();
    productApi.list.mockResolvedValue({
      data: { data: [{ id: 1, name: 'Widget' }], total: 2 },
    });
    renderHome('/?q=widget');
    const loadMoreButton = await screen.findByText(/Load More/);

    productApi.list.mockResolvedValue({
      data: { data: [{ id: 2, name: 'Gadget' }], total: 2 },
    });
    await user.click(loadMoreButton);

    expect(await screen.findByText('Gadget')).toBeInTheDocument();
    expect(screen.getByText('Widget')).toBeInTheDocument();
  });

  it('clears filters, sort and the search params when the clear button is clicked', async () => {
    const user = userEvent.setup();
    productApi.list.mockResolvedValue({
      data: { data: [{ id: 1, name: 'Widget' }], total: 1 },
    });
    renderHome('/?q=widget');
    await screen.findByText('Widget');

    await user.click(screen.getByText(/Clear/));

    expect(await screen.findByTestId('home-sections')).toBeInTheDocument();
  });

  it('reloads facets and products when a filter changes via the sidebar', async () => {
    renderHome('/?q=widget');
    await screen.findByTestId('filter-sidebar');
    productApi.list.mockClear();

    const user = userEvent.setup();
    await user.click(screen.getByTestId('set-instock'));

    await waitFor(() =>
      expect(productApi.list).toHaveBeenCalledWith(expect.objectContaining({ inStock: true })),
    );
  });

  it('reloads products with the new sort when the sidebar changes sort', async () => {
    renderHome('/?q=widget');
    await screen.findByTestId('filter-sidebar');
    productApi.list.mockClear();

    const user = userEvent.setup();
    await user.click(screen.getByTestId('set-sort'));

    await waitFor(() =>
      expect(productApi.list).toHaveBeenCalledWith(
        expect.objectContaining({ sort: 'price', order: 'asc' }),
      ),
    );
  });
});
