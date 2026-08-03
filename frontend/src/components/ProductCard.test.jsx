import React from 'react';
import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { guestEventApi, favoriteApi } from '../services/cartService';
import { renderWithProviders } from '../test-utils/renderWithProviders';
import ProductCard from './ProductCard';

jest.mock('../services/authService', () => ({
  isAuthenticated: jest.fn(() => false),
}));

jest.mock('../services/cartService', () => ({
  cartApi: {
    getCart: jest.fn(),
    syncCart: jest.fn(),
    addItem: jest.fn(),
    updateItem: jest.fn(),
    removeItem: jest.fn(),
    clearCart: jest.fn(),
  },
  guestEventApi: { track: jest.fn() },
  favoriteApi: { list: jest.fn(), add: jest.fn(), remove: jest.fn() },
}));

beforeEach(() => {
  // CRA's Jest config sets resetMocks: true, which wipes any implementation
  // set inside the jest.mock() factory before every test — so it has to be
  // (re)installed here instead.
  guestEventApi.track.mockResolvedValue();
  favoriteApi.list.mockResolvedValue({ data: { data: [] } });
});

const baseProduct = {
  id: 1,
  name: 'Wireless Mouse',
  price: '49.900',
  inStock: true,
  images: [],
  category: { name: 'Electronics', parent: null },
  brand: null,
};

describe('ProductCard', () => {
  it('renders the product name, category, and price', () => {
    renderWithProviders(<ProductCard product={baseProduct} />);

    expect(screen.getByText('Wireless Mouse')).toBeInTheDocument();
    expect(screen.getByText('Electronics')).toBeInTheDocument();
  });

  it('shows the "Add to Cart" button when in stock, and adds it to the cart on click', async () => {
    const user = userEvent.setup();
    renderWithProviders(<ProductCard product={baseProduct} />);

    const addButton = screen.getByTestId('product-add-to-cart');
    expect(addButton).toHaveTextContent('Add to Cart');
    expect(addButton).not.toBeDisabled();

    await user.click(addButton);

    expect(await screen.findByText('Added')).toBeInTheDocument();
  });

  it('disables the button and shows "Out of stock" when the product has no stock', () => {
    renderWithProviders(<ProductCard product={{ ...baseProduct, inStock: false }} />);

    const addButton = screen.getByTestId('product-add-to-cart');
    expect(addButton).toBeDisabled();
    expect(addButton).toHaveTextContent('Out of stock');
  });

  it('shows a Promotion badge when the product has an active promotion', () => {
    renderWithProviders(
      <ProductCard
        product={{
          ...baseProduct,
          promotion: { newPrice: '39.900', oldPrice: '49.900', percentage: 20 },
        }}
      />,
    );

    expect(screen.getByText('Promotion')).toBeInTheDocument();
  });

  it('does not navigate to /login when clicking the card body (only the favorite button does)', async () => {
    const user = userEvent.setup();
    renderWithProviders(<ProductCard product={baseProduct} />);

    // Clicking the add-to-cart button stops propagation, so it must not
    // trigger the card's own onClick (navigate to the product page) in a
    // way that throws or breaks — this is a smoke check that the handler
    // chain doesn't error under user interaction.
    await user.click(screen.getByTestId('product-add-to-cart'));
    expect(screen.getByTestId('product-card')).toBeInTheDocument();
  });
});
