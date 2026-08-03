import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Routes, Route, useLocation } from 'react-router-dom';
import '../../i18n';
import { formatPrice as fmt } from '../../utils/format';
import { CartProvider } from '../../context/CartContext';
import { FavoriteProvider } from '../../context/FavoriteContext';
import { isAuthenticated, getUser, updateLocalUser } from '../../services/authService';
import { orderApi, addressApi } from '../../services/cartService';
import Cart from './Cart';

jest.mock('../../components/Navbar', () => () => <div data-testid="navbar-stub" />);

jest.mock('../../services/authService', () => ({
  isAuthenticated: jest.fn(() => false),
  getUser: jest.fn(() => null),
  updateLocalUser: jest.fn(),
}));

jest.mock('../../services/cartService', () => ({
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
  orderApi: { checkout: jest.fn() },
  addressApi: { list: jest.fn(), create: jest.fn() },
}));

function LocationStub({ testid }) {
  const location = useLocation();
  return <div data-testid={testid}>{JSON.stringify(location.state)}</div>;
}

function renderCart(route = '/cart') {
  return render(
    <MemoryRouter
      initialEntries={[route]}
      future={{ v7_startTransition: true, v7_relativeSplatPath: true }}
    >
      <CartProvider>
        <FavoriteProvider>
          <Routes>
            <Route path="/cart" element={<Cart />} />
            <Route path="/login" element={<LocationStub testid="login-page" />} />
            <Route path="/" element={<div data-testid="home-page" />} />
            <Route path="/orders" element={<div data-testid="orders-page" />} />
          </Routes>
        </FavoriteProvider>
      </CartProvider>
    </MemoryRouter>,
  );
}

function seedCart(items) {
  localStorage.setItem('smartcart_cart', JSON.stringify(items));
}

const widget = { id: 1, name: 'Widget', price: 10, qty: 2, image: null, slug: 'widget', stock: 5 };
const gadget = { id: 2, name: 'Gadget', price: 5, qty: 1, image: null, slug: 'gadget', stock: 5 };

describe('Cart page', () => {
  beforeEach(() => {
    localStorage.clear();
    isAuthenticated.mockReturnValue(false);
    getUser.mockReturnValue(null);
    // favoriteApi.list is called by FavoriteProvider only when authenticated;
    // give it a safe default regardless.
    require('../../services/cartService').favoriteApi.list.mockResolvedValue({
      data: { data: [] },
    });
  });

  describe('empty cart', () => {
    it('shows the empty state and no order summary', () => {
      renderCart();
      expect(screen.getByTestId('cart-empty')).toBeInTheDocument();
      expect(screen.queryByText('Order Summary')).not.toBeInTheDocument();
    });

    it('navigates home when clicking "Browse Products"', async () => {
      const user = userEvent.setup();
      renderCart();
      await user.click(screen.getByTestId('cart-browse-products'));
      expect(await screen.findByTestId('home-page')).toBeInTheDocument();
    });
  });

  describe('cart with items', () => {
    beforeEach(() => {
      seedCart([widget, gadget]);
    });

    it('renders each item and the correct item count', () => {
      renderCart();
      expect(screen.getByText('Widget')).toBeInTheDocument();
      expect(screen.getByText('Gadget')).toBeInTheDocument();
      expect(screen.getByText('3 items')).toBeInTheDocument(); // 2 + 1
    });

    it('computes the order summary total as the sum of price * qty', () => {
      renderCart();
      // 10*2 + 5*1 = 25, shown both in the summary row and the total row.
      expect(screen.getAllByText(`${fmt(25)} TND`)).toHaveLength(2);
    });

    it('updates the total when an item quantity is incremented', async () => {
      const user = userEvent.setup();
      renderCart();
      const plusButtons = screen.getAllByText('+');
      await user.click(plusButtons[0]); // Widget: qty 2 -> 3, now 10*3 + 5*1 = 35
      await waitFor(() => {
        expect(screen.getAllByText(`${fmt(35)} TND`).length).toBeGreaterThan(0);
      });
    });

    it('removes an item from the list when clicking its delete button', async () => {
      const user = userEvent.setup();
      renderCart();
      const deleteButtons = screen.getAllByTitle('Remove');
      await user.click(deleteButtons[0]); // remove Widget
      await waitFor(() => expect(screen.queryByText('Widget')).not.toBeInTheDocument());
      expect(screen.getByText('Gadget')).toBeInTheDocument();
    });

    it('clears the whole cart and shows the empty state when clicking "Clear Cart"', async () => {
      const user = userEvent.setup();
      renderCart();
      await user.click(screen.getByText('Clear Cart'));
      await waitFor(() => expect(screen.getByTestId('cart-empty')).toBeInTheDocument());
    });

    it('redirects a guest to /login (with the cart as the return path) when trying to checkout', async () => {
      const user = userEvent.setup();
      renderCart();
      expect(screen.getByText('Sign In to Checkout')).toBeInTheDocument();
      await user.click(screen.getByText('Sign In to Checkout'));
      const loginPage = await screen.findByTestId('login-page');
      expect(JSON.parse(loginPage.textContent)).toEqual({ from: '/cart' });
    });

    it('also sends a guest to /login via the "Sign in to place your order" link', async () => {
      const user = userEvent.setup();
      renderCart();
      await user.click(screen.getByText('Sign in'));
      expect(await screen.findByTestId('login-page')).toBeInTheDocument();
    });
  });

  describe('checkout flow (authenticated)', () => {
    beforeEach(() => {
      seedCart([widget]);
      isAuthenticated.mockReturnValue(true);
      getUser.mockReturnValue({ phone: '20111222' });
    });

    it('opens the checkout modal, loads addresses, and preselects the default one', async () => {
      const user = userEvent.setup();
      addressApi.list.mockResolvedValue({
        data: [
          {
            id: 5,
            label: 'Home',
            street: '1 Rue X',
            city: 'Tunis',
            postalCode: '1000',
            country: 'Tunisia',
            isDefault: true,
          },
        ],
      });
      renderCart();

      await user.click(screen.getByText('Proceed to Checkout'));
      expect(addressApi.list).toHaveBeenCalledTimes(1);
      expect(await screen.findByText('Home')).toBeInTheDocument();
      expect(screen.getByDisplayValue('5')).toBeChecked();
    });

    it('shows a phone error when the phone field is left empty', async () => {
      const user = userEvent.setup();
      addressApi.list.mockResolvedValue({ data: [] });
      getUser.mockReturnValue({ phone: '' });
      renderCart();

      await user.click(screen.getByText('Proceed to Checkout'));
      await screen.findByPlaceholderText('123 Main Street');
      await user.type(screen.getByPlaceholderText('123 Main Street'), 'Street');
      await user.type(screen.getByPlaceholderText('Tunis'), 'City');
      await user.type(screen.getByPlaceholderText('Tunisia'), 'Country');

      await user.click(screen.getByText('Place Order'));
      expect(screen.getByText('Phone number is required.')).toBeInTheDocument();
      expect(orderApi.checkout).not.toHaveBeenCalled();
    });

    it('shows a phone error for a badly-formatted phone number', async () => {
      const user = userEvent.setup();
      addressApi.list.mockResolvedValue({ data: [] });
      getUser.mockReturnValue({ phone: '123' });
      renderCart();

      await user.click(screen.getByText('Proceed to Checkout'));
      await screen.findByPlaceholderText('123 Main Street');
      await user.click(screen.getByText('Place Order'));
      expect(
        screen.getByText('Please enter a valid Tunisian phone number (e.g. +216 XX XXX XXX).'),
      ).toBeInTheDocument();
    });

    it('requires street/city/country when placing an order with a new address', async () => {
      const user = userEvent.setup();
      addressApi.list.mockResolvedValue({ data: [] });
      renderCart();

      await user.click(screen.getByText('Proceed to Checkout'));
      await screen.findByPlaceholderText('123 Main Street');
      await user.click(screen.getByText('Place Order'));
      expect(screen.getByText('Street, city and country are required.')).toBeInTheDocument();
      expect(orderApi.checkout).not.toHaveBeenCalled();
    });

    it('goes through the confirmation step and places the order with a saved address', async () => {
      const user = userEvent.setup();
      addressApi.list.mockResolvedValue({
        data: [
          {
            id: 5,
            label: 'Home',
            street: '1 Rue X',
            city: 'Tunis',
            postalCode: '1000',
            country: 'Tunisia',
            isDefault: true,
          },
        ],
      });
      orderApi.checkout.mockResolvedValue({ data: { id: 777 } });
      renderCart();

      await user.click(screen.getByText('Proceed to Checkout'));
      await screen.findByText('Home');
      await user.click(screen.getByText('Place Order'));

      // Confirmation modal (stacked on top of the still-open checkout modal,
      // so the address line legitimately appears in both).
      expect(await screen.findByText('Confirm Your Order')).toBeInTheDocument();
      expect(screen.getAllByText('1 Rue X').length).toBeGreaterThan(0);

      await user.click(screen.getByText('Confirm Order'));

      await waitFor(() =>
        expect(orderApi.checkout).toHaveBeenCalledWith({
          addressId: 5,
          contactPhone: '20111222',
        }),
      );
      expect(updateLocalUser).toHaveBeenCalledWith({ phone: '20111222' });

      // Success screen
      expect(await screen.findByText('Order Placed!')).toBeInTheDocument();
      expect(screen.getByText('#777')).toBeInTheDocument();
    });

    it('places an order with a freshly-typed new address', async () => {
      const user = userEvent.setup();
      addressApi.list.mockResolvedValue({ data: [] });
      orderApi.checkout.mockResolvedValue({ data: { id: 42 } });
      renderCart();

      await user.click(screen.getByText('Proceed to Checkout'));
      await screen.findByPlaceholderText('123 Main Street');
      await user.type(screen.getByPlaceholderText('123 Main Street'), 'Avenue Habib');
      await user.type(screen.getByPlaceholderText('Tunis'), 'Sousse');
      await user.type(screen.getByPlaceholderText('Tunisia'), 'Tunisia');

      await user.click(screen.getByText('Place Order'));
      await user.click(await screen.findByText('Confirm Order'));

      await waitFor(() =>
        expect(orderApi.checkout).toHaveBeenCalledWith({
          street: 'Avenue Habib',
          city: 'Sousse',
          postalCode: '',
          country: 'Tunisia',
          contactPhone: '20111222',
        }),
      );
      expect(await screen.findByText('Order Placed!')).toBeInTheDocument();
    });

    it('shows an error and reopens the checkout modal when placing the order fails', async () => {
      const user = userEvent.setup();
      addressApi.list.mockResolvedValue({
        data: [
          {
            id: 5,
            label: 'Home',
            street: '1 Rue X',
            city: 'Tunis',
            postalCode: '',
            country: 'Tunisia',
            isDefault: true,
          },
        ],
      });
      orderApi.checkout.mockRejectedValue({ response: { data: { error: 'Item out of stock.' } } });
      renderCart();

      await user.click(screen.getByText('Proceed to Checkout'));
      await screen.findByText('Home');
      await user.click(screen.getByText('Place Order'));
      await user.click(await screen.findByText('Confirm Order'));

      expect(await screen.findByText('Item out of stock.')).toBeInTheDocument();
      // Back in the checkout modal (confirm modal closed), not the success screen.
      expect(screen.getByText('Shipping Address')).toBeInTheDocument();
      expect(screen.queryByText('Order Placed!')).not.toBeInTheDocument();
    });

    it('falls back to an empty address list (and "new" selection) when loading addresses fails', async () => {
      const user = userEvent.setup();
      addressApi.list.mockRejectedValue(new Error('network error'));
      renderCart();

      await user.click(screen.getByText('Proceed to Checkout'));
      await waitFor(() =>
        expect(screen.queryByText('Loading saved addresses…')).not.toBeInTheDocument(),
      );
      expect(screen.getByPlaceholderText('123 Main Street')).toBeInTheDocument();
    });

    it('navigates to /orders from the success screen when clicking "Track My Order"', async () => {
      const user = userEvent.setup();
      addressApi.list.mockResolvedValue({
        data: [
          {
            id: 5,
            label: 'Home',
            street: 'A',
            city: 'Tunis',
            postalCode: '',
            country: 'Tunisia',
            isDefault: true,
          },
        ],
      });
      orderApi.checkout.mockResolvedValue({ data: { id: 9 } });
      renderCart();

      await user.click(screen.getByText('Proceed to Checkout'));
      await screen.findByText('Home');
      await user.click(screen.getByText('Place Order'));
      await user.click(await screen.findByText('Confirm Order'));
      await screen.findByText('Order Placed!');

      await user.click(screen.getByText('Track My Order'));
      expect(await screen.findByTestId('orders-page')).toBeInTheDocument();
    });
  });
});
