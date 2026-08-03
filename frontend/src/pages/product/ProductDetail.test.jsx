import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import '../../i18n';
import {
  productApi,
  interactionApi,
  guestEventApi,
  recommendationApi,
} from '../../services/cartService';
import { useCart } from '../../context/CartContext';
import { useFavorites } from '../../context/FavoriteContext';
import { isAuthenticated } from '../../services/authService';
import ProductDetail from './ProductDetail';

jest.mock('../../services/cartService', () => ({
  productApi: { get: jest.fn(), activity: jest.fn() },
  interactionApi: { track: jest.fn() },
  guestEventApi: { track: jest.fn() },
  recommendationApi: { forProduct: jest.fn() },
}));

jest.mock('../../context/CartContext', () => ({ useCart: jest.fn() }));
jest.mock('../../context/FavoriteContext', () => ({ useFavorites: jest.fn() }));
jest.mock('../../services/authService', () => ({ isAuthenticated: jest.fn() }));

jest.mock('../../components/Navbar', () => () => <div data-testid="navbar" />);
jest.mock('../../components/ProductCard', () => ({ product }) => (
  <div data-testid="product-card">{product.name}</div>
));
jest.mock('./ProductDetail/ImageGallery', () => ({ images }) => (
  <div data-testid="image-gallery">{images.length} images</div>
));
jest.mock('./ProductDetail/ReviewsSection', () => ({ productId }) => (
  <div data-testid="reviews-section">reviews for {productId}</div>
));

const baseProduct = {
  id: 42,
  name: 'Widget 3000',
  price: 19.99,
  stock: 5,
  averageRating: 0,
  reviewCount: 0,
  category: { id: 3, name: 'Widgets' },
  images: [],
};

function renderProductDetail(route = '/product/42') {
  return render(
    <MemoryRouter
      initialEntries={[route]}
      future={{ v7_startTransition: true, v7_relativeSplatPath: true }}
    >
      <Routes>
        <Route path="/product/:id" element={<ProductDetail />} />
        <Route path="/" element={<div data-testid="home-page" />} />
        <Route path="/login" element={<div data-testid="login-page" />} />
      </Routes>
    </MemoryRouter>,
  );
}

describe('ProductDetail page', () => {
  let addToCart;
  let toggleFavorite;
  let isFavorite;

  beforeEach(() => {
    jest.useRealTimers();
    addToCart = jest.fn().mockResolvedValue();
    toggleFavorite = jest.fn().mockResolvedValue();
    isFavorite = jest.fn().mockReturnValue(false);
    useCart.mockReturnValue({ addToCart });
    useFavorites.mockReturnValue({ isFavorite, toggleFavorite });
    isAuthenticated.mockReturnValue(true);

    productApi.get.mockResolvedValue({ data: baseProduct });
    productApi.activity.mockResolvedValue({ data: { viewingNow: 0, inCarts: 0 } });
    interactionApi.track.mockResolvedValue();
    guestEventApi.track.mockResolvedValue();
    recommendationApi.forProduct.mockResolvedValue({ data: { similar: [], complementary: [] } });
  });

  it('shows a not-found message when the product does not exist', async () => {
    productApi.get.mockRejectedValue({ response: { status: 404 } });
    renderProductDetail();

    expect(await screen.findByText('Product not found')).toBeInTheDocument();
  });

  it('renders the product name, price and stock badge once loaded', async () => {
    renderProductDetail();
    expect(await screen.findByRole('heading', { name: 'Widget 3000' })).toBeInTheDocument();
    expect(screen.getByText('19,990')).toBeInTheDocument();
    expect(screen.getByText('Low Stock — only 5 left')).toBeInTheDocument();
  });

  it('shows a low-stock badge when stock is 10 or below', async () => {
    productApi.get.mockResolvedValue({ data: { ...baseProduct, stock: 3 } });
    renderProductDetail();
    expect(await screen.findByText('Low Stock — only 3 left')).toBeInTheDocument();
  });

  it('shows an out-of-stock badge and hides the add-to-cart controls when stock is 0', async () => {
    productApi.get.mockResolvedValue({ data: { ...baseProduct, stock: 0 } });
    renderProductDetail();
    expect(await screen.findByText('Out of Stock')).toBeInTheDocument();
    expect(screen.queryByText('Add to Cart')).not.toBeInTheDocument();
  });

  it('tracks an authenticated view via interactionApi, not guestEventApi', async () => {
    renderProductDetail();
    await waitFor(() => expect(interactionApi.track).toHaveBeenCalledWith('42', 'view'));
    expect(guestEventApi.track).not.toHaveBeenCalled();
  });

  it('tracks a guest view via guestEventApi when not authenticated', async () => {
    isAuthenticated.mockReturnValue(false);
    renderProductDetail();
    await waitFor(() => expect(guestEventApi.track).toHaveBeenCalledWith('42', 'view'));
    expect(interactionApi.track).not.toHaveBeenCalled();
  });

  it('shows the promotion price and percentage when the product is on promotion', async () => {
    productApi.get.mockResolvedValue({
      data: {
        ...baseProduct,
        promotion: { newPrice: 15, oldPrice: 20, percentage: 25, endDate: null },
      },
    });
    renderProductDetail();
    expect(await screen.findByText('-25% off')).toBeInTheDocument();
    expect(screen.getByText('Limited time offer')).toBeInTheDocument();
  });

  it('increments and decrements quantity within stock bounds', async () => {
    const user = userEvent.setup();
    renderProductDetail();
    await screen.findByRole('heading', { name: 'Widget 3000' });

    const [decrement, increment] = screen.getAllByRole('button', { name: /−|\+/ });
    expect(screen.getByText('1')).toBeInTheDocument();

    await user.click(increment);
    expect(screen.getByText('2')).toBeInTheDocument();

    await user.click(decrement);
    expect(screen.getByText('1')).toBeInTheDocument();
    expect(decrement).toBeDisabled();
  });

  it('adds the product to the cart with the selected quantity and shows confirmation', async () => {
    const user = userEvent.setup();
    renderProductDetail();
    await screen.findByRole('heading', { name: 'Widget 3000' });

    await user.click(screen.getByText('Add to Cart'));

    await waitFor(() => expect(addToCart).toHaveBeenCalledWith(baseProduct, 1));
    expect(await screen.findByText('Added to Cart!')).toBeInTheDocument();
  });

  it('toggles the favorite state when authenticated', async () => {
    const user = userEvent.setup();
    renderProductDetail();
    await screen.findByRole('heading', { name: 'Widget 3000' });

    await user.click(screen.getByText('Save'));
    expect(toggleFavorite).toHaveBeenCalledWith(42);
  });

  it('redirects to login when trying to favorite while unauthenticated', async () => {
    isAuthenticated.mockReturnValue(false);
    const user = userEvent.setup();
    renderProductDetail();
    await screen.findByRole('heading', { name: 'Widget 3000' });

    await user.click(screen.getByText('Save'));
    expect(await screen.findByTestId('login-page')).toBeInTheDocument();
    expect(toggleFavorite).not.toHaveBeenCalled();
  });

  it('renders complementary and similar products when present', async () => {
    recommendationApi.forProduct.mockResolvedValue({
      data: {
        similar: [{ id: 1, name: 'Similar Item' }],
        complementary: [{ id: 2, name: 'Complementary Item' }],
      },
    });
    renderProductDetail();

    expect(await screen.findByText('Frequently Bought Together')).toBeInTheDocument();
    expect(screen.getByText('Complementary Item')).toBeInTheDocument();
    expect(screen.getByText('You May Also Like')).toBeInTheDocument();
    expect(screen.getByText('Similar Item')).toBeInTheDocument();
  });

  it('navigates home when the breadcrumb Home button is clicked', async () => {
    const user = userEvent.setup();
    renderProductDetail();
    await screen.findByRole('heading', { name: 'Widget 3000' });

    await user.click(screen.getByText('Home'));
    expect(await screen.findByTestId('home-page')).toBeInTheDocument();
  });

  it('renders the reviews section with the product id', async () => {
    renderProductDetail();
    expect(await screen.findByText('reviews for 42')).toBeInTheDocument();
  });
});
