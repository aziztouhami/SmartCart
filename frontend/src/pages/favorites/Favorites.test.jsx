import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import '../../i18n';
import { useFavorites } from '../../context/FavoriteContext';
import { useCart } from '../../context/CartContext';
import Favorites from './Favorites';

jest.mock('../../context/FavoriteContext', () => ({ useFavorites: jest.fn() }));
jest.mock('../../context/CartContext', () => ({ useCart: jest.fn() }));
jest.mock('../../components/Navbar', () => () => <div data-testid="navbar" />);

const mockNavigate = jest.fn();
jest.mock('react-router-dom', () => ({
  ...jest.requireActual('react-router-dom'),
  useNavigate: () => mockNavigate,
}));

function renderFavorites() {
  return render(
    <MemoryRouter future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
      <Favorites />
    </MemoryRouter>,
  );
}

const favItem = {
  id: 1,
  productId: 42,
  productName: 'Widget',
  productImage: '/widget.jpg',
  productCategory: 'Electronics',
  productBrand: 'Acme',
  productPrice: 19.99,
  productInStock: true,
};

describe('Favorites page', () => {
  let toggleFavorite;
  let addToCart;

  beforeEach(() => {
    toggleFavorite = jest.fn();
    addToCart = jest.fn();
    useCart.mockReturnValue({ addToCart });
    mockNavigate.mockClear();
  });

  it('shows a loading spinner', () => {
    useFavorites.mockReturnValue({ items: [], loading: true, toggleFavorite });
    const { container } = renderFavorites();
    expect(container.querySelector('.fv-loading')).toBeInTheDocument();
  });

  it('shows the empty state when there are no favorites', () => {
    useFavorites.mockReturnValue({ items: [], loading: false, toggleFavorite });
    renderFavorites();
    expect(screen.getByText('No favorites yet')).toBeInTheDocument();
  });

  it('navigates home from the empty state browse button', async () => {
    useFavorites.mockReturnValue({ items: [], loading: false, toggleFavorite });
    const user = userEvent.setup();
    renderFavorites();

    await user.click(screen.getByText('Browse Products'));
    expect(mockNavigate).toHaveBeenCalledWith('/');
  });

  it('renders favorite cards with name, price, category, brand and stock status', () => {
    useFavorites.mockReturnValue({ items: [favItem], loading: false, toggleFavorite });
    renderFavorites();

    expect(screen.getByText('Widget')).toBeInTheDocument();
    expect(screen.getByText('Electronics')).toBeInTheDocument();
    expect(screen.getByText('Acme')).toBeInTheDocument();
    expect(screen.getByText('19,990')).toBeInTheDocument();
    expect(screen.getByText('In Stock')).toBeInTheDocument();
    expect(screen.getByText('1 saved product')).toBeInTheDocument();
  });

  it('shows an out-of-stock badge and disables add-to-cart for unavailable items', () => {
    useFavorites.mockReturnValue({
      items: [{ ...favItem, productInStock: false }],
      loading: false,
      toggleFavorite,
    });
    renderFavorites();

    expect(screen.getByText('Out of Stock')).toBeInTheDocument();
    expect(screen.getByText('Unavailable').closest('button')).toBeDisabled();
  });

  it('removes a favorite via the heart button without navigating', async () => {
    useFavorites.mockReturnValue({ items: [favItem], loading: false, toggleFavorite });
    const user = userEvent.setup();
    renderFavorites();

    await user.click(screen.getByTitle('Remove from favorites'));
    expect(toggleFavorite).toHaveBeenCalledWith(42);
    expect(mockNavigate).not.toHaveBeenCalled();
  });

  it('navigates to the product page when the card body is clicked', async () => {
    useFavorites.mockReturnValue({ items: [favItem], loading: false, toggleFavorite });
    const user = userEvent.setup();
    renderFavorites();

    await user.click(screen.getByText('Widget'));
    expect(mockNavigate).toHaveBeenCalledWith('/product/42');
  });

  it('adds an in-stock favorite to the cart', async () => {
    useFavorites.mockReturnValue({ items: [favItem], loading: false, toggleFavorite });
    const user = userEvent.setup();
    renderFavorites();

    await user.click(screen.getByText('Add to Cart'));
    expect(addToCart).toHaveBeenCalledWith(
      { id: 42, name: 'Widget', price: 19.99, image: '/widget.jpg', stock: null },
      1,
    );
  });

  it('shows an initial placeholder when there is no product image', () => {
    useFavorites.mockReturnValue({
      items: [{ ...favItem, productImage: null }],
      loading: false,
      toggleFavorite,
    });
    renderFavorites();
    expect(screen.getByText('W')).toBeInTheDocument();
  });
});
