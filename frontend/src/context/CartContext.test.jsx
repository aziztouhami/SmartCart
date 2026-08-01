import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

jest.mock('../services/authService', () => ({
  isAuthenticated: jest.fn(() => false),
}));

jest.mock('../services/cartService', () => ({
  cartApi: { getCart: jest.fn(), syncCart: jest.fn(), addItem: jest.fn(), updateItem: jest.fn(), removeItem: jest.fn(), clearCart: jest.fn() },
  guestEventApi: { track: jest.fn() },
}));

import { guestEventApi } from '../services/cartService';
import { CartProvider, useCart } from './CartContext';

const product = (overrides = {}) => ({
  id: 1,
  name: 'Widget',
  price: 10,
  images: [],
  stock: 5,
  ...overrides,
});

function TestConsumer() {
  const { items, addToCart, removeFromCart, updateQty, clearCart, cartCount, cartTotal } = useCart();
  return (
    <div>
      <span data-testid="count">{cartCount}</span>
      <span data-testid="total">{cartTotal}</span>
      <ul>
        {items.map((i) => (
          <li key={i.id} data-testid="item">{i.name} x{i.qty}</li>
        ))}
      </ul>
      <button onClick={() => addToCart(product(), 1)}>add-widget</button>
      <button onClick={() => addToCart(product({ id: 2, name: 'Gadget', price: 5 }), 1)}>add-gadget</button>
      <button onClick={() => removeFromCart(1)}>remove-widget</button>
      <button onClick={() => updateQty(1, 5)}>set-qty-5</button>
      <button onClick={() => updateQty(1, 0)}>set-qty-0</button>
      <button onClick={() => clearCart()}>clear</button>
    </div>
  );
}

function setup() {
  const user = userEvent.setup();
  render(
    <CartProvider>
      <TestConsumer />
    </CartProvider>,
  );
  return { user };
}

describe('CartContext (guest / local cart)', () => {
  beforeEach(() => {
    localStorage.clear();
    // CRA's Jest config sets resetMocks: true, which wipes any implementation
    // set inside the jest.mock() factory before every test — so it has to be
    // (re)installed here instead.
    guestEventApi.track.mockResolvedValue();
  });

  it('starts empty', () => {
    setup();
    expect(screen.getByTestId('count')).toHaveTextContent('0');
    expect(screen.queryAllByTestId('item')).toHaveLength(0);
  });

  it('adds a new product to the cart', async () => {
    const { user } = setup();
    await user.click(screen.getByText('add-widget'));

    expect(screen.getByTestId('count')).toHaveTextContent('1');
    expect(screen.getByTestId('item')).toHaveTextContent('Widget x1');
  });

  it('increments the quantity when adding the same product again', async () => {
    const { user } = setup();
    await user.click(screen.getByText('add-widget'));
    await user.click(screen.getByText('add-widget'));

    expect(screen.getByTestId('count')).toHaveTextContent('2');
    expect(screen.getAllByTestId('item')).toHaveLength(1);
    expect(screen.getByTestId('item')).toHaveTextContent('Widget x2');
  });

  it('computes cartTotal as the sum of price * qty across distinct items', async () => {
    const { user } = setup();
    await user.click(screen.getByText('add-widget'));  // 10 * 1
    await user.click(screen.getByText('add-gadget'));  // 5 * 1

    expect(screen.getByTestId('total')).toHaveTextContent('15');
  });

  it('updates the quantity of an existing item', async () => {
    const { user } = setup();
    await user.click(screen.getByText('add-widget'));
    await user.click(screen.getByText('set-qty-5'));

    expect(screen.getByTestId('item')).toHaveTextContent('Widget x5');
    expect(screen.getByTestId('count')).toHaveTextContent('5');
  });

  it('ignores a quantity update below 1', async () => {
    const { user } = setup();
    await user.click(screen.getByText('add-widget'));
    await user.click(screen.getByText('set-qty-0'));

    expect(screen.getByTestId('item')).toHaveTextContent('Widget x1');
  });

  it('removes an item from the cart', async () => {
    const { user } = setup();
    await user.click(screen.getByText('add-widget'));
    await user.click(screen.getByText('remove-widget'));

    expect(screen.getByTestId('count')).toHaveTextContent('0');
    expect(screen.queryAllByTestId('item')).toHaveLength(0);
  });

  it('clears the whole cart', async () => {
    const { user } = setup();
    await user.click(screen.getByText('add-widget'));
    await user.click(screen.getByText('add-gadget'));
    await user.click(screen.getByText('clear'));

    expect(screen.getByTestId('count')).toHaveTextContent('0');
  });

  it('persists the cart to localStorage so it survives a reload', async () => {
    const { user } = setup();
    await user.click(screen.getByText('add-widget'));

    const stored = JSON.parse(localStorage.getItem('smartcart_cart'));
    expect(stored).toHaveLength(1);
    expect(stored[0]).toMatchObject({ id: 1, name: 'Widget', qty: 1 });
  });
});
