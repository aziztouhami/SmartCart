import React from 'react';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import '../i18n';
import { getUser, isAuthenticated, logout } from '../services/authService';
import { useCart } from '../context/CartContext';
import { useFavorites } from '../context/FavoriteContext';
import { useCategories } from '../context/CategoryContext';
import { productApi } from '../services/cartService';
import Navbar from './Navbar';

jest.mock('../services/authService', () => ({
  getUser: jest.fn(),
  isAuthenticated: jest.fn(),
  logout: jest.fn(),
}));
jest.mock('../context/CartContext', () => ({ useCart: jest.fn() }));
jest.mock('../context/FavoriteContext', () => ({ useFavorites: jest.fn() }));
jest.mock('../context/CategoryContext', () => ({ useCategories: jest.fn() }));
jest.mock('../services/cartService', () => ({
  productApi: { autocomplete: jest.fn() },
}));
jest.mock('./LanguageSwitcher', () => () => <div data-testid="language-switcher" />);

const mockNavigate = jest.fn();
jest.mock('react-router-dom', () => ({
  ...jest.requireActual('react-router-dom'),
  useNavigate: () => mockNavigate,
}));

function renderNavbar(route = '/') {
  return render(
    <MemoryRouter
      initialEntries={[route]}
      future={{ v7_startTransition: true, v7_relativeSplatPath: true }}
    >
      <Navbar />
    </MemoryRouter>,
  );
}

const emptyAutocomplete = {
  data: { nameStart: [], nameContains: [], byBrand: [], byCategory: [] },
};

// The navbar highlights the matched query substring by splitting the
// suggestion name across a <mark> and plain text nodes, so the full name is
// never a single text node — a plain getByText(name) can't find it.
function suggestionNameMatcher(name) {
  return (_content, element) =>
    element?.classList?.contains('h-sugg-name') && element.textContent === name;
}

describe('Navbar', () => {
  let resetCart;

  beforeEach(() => {
    mockNavigate.mockClear();
    resetCart = jest.fn();
    useCart.mockReturnValue({ cartCount: 0, resetCart });
    useFavorites.mockReturnValue({ favCount: 0 });
    useCategories.mockReturnValue({ categories: [] });
    productApi.autocomplete.mockResolvedValue(emptyAutocomplete);
    isAuthenticated.mockReturnValue(false);
    getUser.mockReturnValue(null);
  });

  it('renders the logo, search input and cart/favorites buttons', () => {
    renderNavbar();
    expect(screen.getByTestId('nav-logo')).toBeInTheDocument();
    expect(screen.getByTestId('nav-search-input')).toBeInTheDocument();
    expect(screen.getByTestId('nav-cart-button')).toBeInTheDocument();
    expect(screen.getByTestId('nav-favorites-button')).toBeInTheDocument();
  });

  it('navigates home when the logo is clicked', async () => {
    const user = userEvent.setup();
    renderNavbar();
    await user.click(screen.getByTestId('nav-logo'));
    expect(mockNavigate).toHaveBeenCalledWith('/');
  });

  it('shows Sign In / Create Account when logged out, and navigates on click', async () => {
    const user = userEvent.setup();
    renderNavbar();
    expect(screen.getByTestId('nav-signin-button')).toBeInTheDocument();

    await user.click(screen.getByTestId('nav-signin-button'));
    expect(mockNavigate).toHaveBeenCalledWith('/login');

    await user.click(screen.getByTestId('nav-register-button'));
    expect(mockNavigate).toHaveBeenCalledWith('/register');
  });

  it('shows the cart count badge only when the cart has items', () => {
    useCart.mockReturnValue({ cartCount: 3, resetCart });
    renderNavbar();
    expect(screen.getByTestId('nav-cart-count')).toHaveTextContent('3');
  });

  it('does not show a cart badge when the cart is empty', () => {
    renderNavbar();
    expect(screen.queryByTestId('nav-cart-count')).not.toBeInTheDocument();
  });

  it('redirects to login with a return state when favorites is clicked while logged out', async () => {
    const user = userEvent.setup();
    renderNavbar();
    await user.click(screen.getByTestId('nav-favorites-button'));
    expect(mockNavigate).toHaveBeenCalledWith('/login', { state: { from: '/favorites' } });
  });

  it('navigates straight to favorites when logged in', async () => {
    isAuthenticated.mockReturnValue(true);
    getUser.mockReturnValue({
      firstName: 'Ada',
      lastName: 'Lovelace',
      email: 'ada@example.com',
      roles: ['ROLE_USER'],
    });
    useFavorites.mockReturnValue({ favCount: 2 });
    const user = userEvent.setup();
    renderNavbar();

    expect(screen.getByText('2')).toBeInTheDocument(); // favorites badge
    await user.click(screen.getByTestId('nav-favorites-button'));
    expect(mockNavigate).toHaveBeenCalledWith('/favorites');
  });

  it('navigates to the cart page when the cart icon is clicked', async () => {
    const user = userEvent.setup();
    renderNavbar();
    await user.click(screen.getByTestId('nav-cart-button'));
    expect(mockNavigate).toHaveBeenCalledWith('/cart');
  });

  describe('logged-in user menu', () => {
    beforeEach(() => {
      isAuthenticated.mockReturnValue(true);
      getUser.mockReturnValue({
        firstName: 'Ada',
        lastName: 'Lovelace',
        email: 'ada@example.com',
        roles: ['ROLE_USER'],
      });
    });

    it('shows the user initials and first name instead of sign-in buttons', () => {
      renderNavbar();
      expect(screen.queryByTestId('nav-signin-button')).not.toBeInTheDocument();
      expect(screen.getByText('Ada')).toBeInTheDocument();
      expect(screen.getByText('AL')).toBeInTheDocument();
    });

    it('opens the dropdown and shows profile/favorites/orders/logout, but not admin panel', async () => {
      const user = userEvent.setup();
      renderNavbar();
      await user.click(screen.getByText('Ada'));

      expect(screen.getByText('My Profile')).toBeInTheDocument();
      expect(screen.getByText('My Favorites')).toBeInTheDocument();
      expect(screen.getByText('My Orders')).toBeInTheDocument();
      expect(screen.getByText('Log Out')).toBeInTheDocument();
      expect(screen.queryByText('Admin Panel')).not.toBeInTheDocument();
    });

    it('shows the admin panel link for admin users', async () => {
      getUser.mockReturnValue({
        firstName: 'Ada',
        lastName: 'Lovelace',
        email: 'ada@example.com',
        roles: ['ROLE_USER', 'ROLE_ADMIN'],
      });
      const user = userEvent.setup();
      renderNavbar();
      await user.click(screen.getByText('Ada'));

      await user.click(screen.getByText('Admin Panel'));
      expect(mockNavigate).toHaveBeenCalledWith('/admin');
    });

    it('navigates to /profile from the dropdown and closes it', async () => {
      const user = userEvent.setup();
      renderNavbar();
      await user.click(screen.getByText('Ada'));
      await user.click(screen.getByText('My Profile'));

      expect(mockNavigate).toHaveBeenCalledWith('/profile');
      expect(screen.queryByText('My Profile')).not.toBeInTheDocument();
    });

    it('logs out, resets the cart and navigates to login', async () => {
      const user = userEvent.setup();
      renderNavbar();
      await user.click(screen.getByText('Ada'));
      await user.click(screen.getByText('Log Out'));

      expect(logout).toHaveBeenCalled();
      expect(resetCart).toHaveBeenCalled();
      expect(mockNavigate).toHaveBeenCalledWith('/login');
    });
  });

  describe('search', () => {
    it('debounces autocomplete calls and renders grouped suggestions', async () => {
      productApi.autocomplete.mockResolvedValue({
        data: {
          nameStart: [
            { id: 1, name: 'Widget', price: 10, brand: null, category: null, inStock: true },
          ],
          nameContains: [
            { id: 2, name: 'Super Widget', price: 20, brand: null, category: null, inStock: true },
          ],
          byBrand: [],
          byCategory: [],
        },
      });
      const user = userEvent.setup();
      renderNavbar();

      await user.type(screen.getByTestId('nav-search-input'), 'wid');

      await waitFor(
        () => expect(productApi.autocomplete).toHaveBeenCalledWith('wid', expect.anything()),
        {
          timeout: 3000,
        },
      );
      expect(await screen.findByText('Other name matches')).toBeInTheDocument();
      expect(screen.getByText(suggestionNameMatcher('Super Widget'))).toBeInTheDocument();
    });

    it('shows an out-of-stock tag and section headers for brand/category matches', async () => {
      productApi.autocomplete.mockResolvedValue({
        data: {
          nameStart: [],
          nameContains: [],
          byBrand: [
            {
              id: 3,
              name: 'Acme Thing',
              price: 5,
              brand: { name: 'Acme' },
              category: { name: 'Tools' },
              inStock: false,
            },
          ],
          byCategory: [],
        },
      });
      const user = userEvent.setup();
      renderNavbar();
      await user.type(screen.getByTestId('nav-search-input'), 'acme');

      expect(await screen.findByText('By Brand')).toBeInTheDocument();
      expect(screen.getByText('Out of stock')).toBeInTheDocument();
    });

    it('shows a clear button once text is entered, and clears the query on click', async () => {
      const user = userEvent.setup();
      renderNavbar();
      const input = screen.getByTestId('nav-search-input');
      await user.type(input, 'abc');
      expect(input).toHaveValue('abc');

      await user.click(screen.getByText('✕'));
      expect(input).toHaveValue('');
      expect(mockNavigate).toHaveBeenCalledWith('/');
    });

    it('submits a search on Enter when there are no suggestions', async () => {
      const user = userEvent.setup();
      renderNavbar();
      await user.type(screen.getByTestId('nav-search-input'), 'shoes{Enter}');
      expect(mockNavigate).toHaveBeenCalledWith('/?q=shoes');
    });

    it('navigates to the product page when a suggestion is clicked', async () => {
      productApi.autocomplete.mockResolvedValue({
        data: {
          nameStart: [
            { id: 7, name: 'Widget', price: 10, brand: null, category: null, inStock: true },
          ],
          nameContains: [],
          byBrand: [],
          byCategory: [],
        },
      });
      const user = userEvent.setup();
      renderNavbar();
      await user.type(screen.getByTestId('nav-search-input'), 'wid');

      const suggestionName = await screen.findByText(suggestionNameMatcher('Widget'));
      fireEvent.mouseDown(suggestionName.closest('.h-sugg-item'));

      expect(mockNavigate).toHaveBeenCalledWith('/product/7');
      expect(screen.getByTestId('nav-search-input')).toHaveValue('');
    });

    it('navigates the highlighted suggestion via ArrowDown + Enter', async () => {
      productApi.autocomplete.mockResolvedValue({
        data: {
          nameStart: [
            { id: 7, name: 'Widget', price: 10, brand: null, category: null, inStock: true },
            { id: 8, name: 'Widget Pro', price: 20, brand: null, category: null, inStock: true },
          ],
          nameContains: [],
          byBrand: [],
          byCategory: [],
        },
      });
      const user = userEvent.setup();
      renderNavbar();
      const input = screen.getByTestId('nav-search-input');
      await user.type(input, 'wid');
      await screen.findByText(suggestionNameMatcher('Widget Pro'));

      await user.keyboard('{ArrowDown}{Enter}');
      expect(mockNavigate).toHaveBeenCalledWith('/product/7');
    });

    it('closes suggestions on Escape', async () => {
      productApi.autocomplete.mockResolvedValue({
        data: {
          nameStart: [
            { id: 7, name: 'Widget', price: 10, brand: null, category: null, inStock: true },
          ],
          nameContains: [],
          byBrand: [],
          byCategory: [],
        },
      });
      const user = userEvent.setup();
      renderNavbar();
      await user.type(screen.getByTestId('nav-search-input'), 'wid');
      await screen.findByText(suggestionNameMatcher('Widget'));

      await user.keyboard('{Escape}');
      expect(screen.queryByText(suggestionNameMatcher('Widget'))).not.toBeInTheDocument();
    });

    it('submits via the "see all results" footer button', async () => {
      productApi.autocomplete.mockResolvedValue({
        data: {
          nameStart: [
            { id: 7, name: 'Widget', price: 10, brand: null, category: null, inStock: true },
          ],
          nameContains: [],
          byBrand: [],
          byCategory: [],
        },
      });
      const user = userEvent.setup();
      renderNavbar();
      await user.type(screen.getByTestId('nav-search-input'), 'widget');
      await screen.findByText('Widget');

      await user.click(screen.getByText(/See all results for/));
      expect(mockNavigate).toHaveBeenCalledWith('/?q=widget');
    });
  });

  describe('category bar', () => {
    const categories = [
      {
        id: 1,
        name: 'Electronics',
        children: [
          { id: 10, name: 'Phones' },
          { id: 11, name: 'Laptops' },
        ],
      },
      { id: 2, name: 'Books', children: [] },
    ];

    it('renders the promotions link and category pills', async () => {
      useCategories.mockReturnValue({ categories });
      const user = userEvent.setup();
      renderNavbar();

      expect(screen.getByText('Promotions')).toBeInTheDocument();
      expect(screen.getByText('Electronics')).toBeInTheDocument();
      expect(screen.getByText('Books')).toBeInTheDocument();

      await user.click(screen.getByText('Promotions'));
      expect(mockNavigate).toHaveBeenCalledWith('/promotions');
    });

    it('navigates to a filtered view when a category pill is clicked', async () => {
      useCategories.mockReturnValue({ categories });
      const user = userEvent.setup();
      renderNavbar();

      await user.click(screen.getByText('Books'));
      expect(mockNavigate).toHaveBeenCalledWith('/?cat=2');
    });

    it('shows a mega menu with subcategories on hover, and navigates when a child is clicked', async () => {
      useCategories.mockReturnValue({ categories });
      const user = userEvent.setup();
      renderNavbar();

      await user.hover(screen.getByText('Electronics'));
      expect(await screen.findByText('Phones')).toBeInTheDocument();
      expect(screen.getByText('Laptops')).toBeInTheDocument();

      fireEvent.click(screen.getByText('Phones'));
      expect(mockNavigate).toHaveBeenCalledWith('/?cat=10');
    });

    it('does not show a mega menu for a category with no children', async () => {
      useCategories.mockReturnValue({ categories });
      const user = userEvent.setup();
      renderNavbar();

      await user.hover(screen.getByText('Books'));
      expect(screen.queryByText('Phones')).not.toBeInTheDocument();
    });
  });
});
