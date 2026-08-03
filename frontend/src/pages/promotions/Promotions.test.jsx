import React from 'react';
import { render, screen } from '@testing-library/react';
import '../../i18n';
import { productApi } from '../../services/cartService';
import Promotions from './Promotions';

jest.mock('../../services/cartService', () => ({
  productApi: { promotions: jest.fn() },
}));

jest.mock('../../components/Navbar', () => () => <div data-testid="navbar" />);
jest.mock('../../components/ProductCard', () => ({
  __esModule: true,
  default: ({ product }) => <div data-testid="product-card">{product.name}</div>,
  SkeletonCard: () => <div data-testid="skeleton-card" />,
}));

describe('Promotions page', () => {
  it('shows skeleton cards while loading', () => {
    productApi.promotions.mockReturnValue(new Promise(() => {}));
    render(<Promotions />);
    expect(screen.getAllByTestId('skeleton-card')).toHaveLength(10);
  });

  it('shows the empty state when there are no active promotions', async () => {
    productApi.promotions.mockResolvedValue({ data: [] });
    render(<Promotions />);
    expect(await screen.findByText('No active promotions')).toBeInTheDocument();
  });

  it('falls back to the empty state when the request fails', async () => {
    productApi.promotions.mockRejectedValue(new Error('network error'));
    render(<Promotions />);
    expect(await screen.findByText('No active promotions')).toBeInTheDocument();
  });

  it('renders promoted products and the count badge once loaded', async () => {
    productApi.promotions.mockResolvedValue({
      data: [
        { id: 1, name: 'Widget' },
        { id: 2, name: 'Gadget' },
      ],
    });
    render(<Promotions />);

    expect(await screen.findByText('Widget')).toBeInTheDocument();
    expect(screen.getByText('Gadget')).toBeInTheDocument();
    expect(screen.getByText('2 promoted products')).toBeInTheDocument();
  });

  it('fetches at most 50 promotions', async () => {
    productApi.promotions.mockResolvedValue({ data: [] });
    render(<Promotions />);
    expect(productApi.promotions).toHaveBeenCalledWith(50);
  });
});
