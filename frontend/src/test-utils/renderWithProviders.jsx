import React from 'react';
import { render } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { CartProvider } from '../context/CartContext';
import { FavoriteProvider } from '../context/FavoriteContext';
import '../i18n';

/**
 * Wraps a component with the same providers App.js does (router, cart,
 * favorites, i18n) so components that call useCart()/useFavorites()/
 * useTranslation() can be rendered in isolation without duplicating that
 * setup in every test file.
 */
export function renderWithProviders(ui, { route = '/' } = {}) {
  return render(
    <MemoryRouter
      initialEntries={[route]}
      future={{ v7_startTransition: true, v7_relativeSplatPath: true }}
    >
      <CartProvider>
        <FavoriteProvider>{ui}</FavoriteProvider>
      </CartProvider>
    </MemoryRouter>,
  );
}
