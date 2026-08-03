import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import '../../../i18n';
import { formatPrice as fmt } from '../../../utils/format';
import CartItemRow from './CartItemRow';

const baseItem = {
  id: 1,
  name: 'Widget',
  price: 10,
  qty: 2,
  image: null,
  stock: 5,
};

function setup(itemOverrides = {}) {
  const item = { ...baseItem, ...itemOverrides };
  const updateQty = jest.fn();
  const removeFromCart = jest.fn();
  const user = userEvent.setup();
  render(<CartItemRow item={item} updateQty={updateQty} removeFromCart={removeFromCart} />);
  return { item, updateQty, removeFromCart, user };
}

describe('CartItemRow', () => {
  it('renders the name, per-unit price, and line subtotal', () => {
    setup();
    expect(screen.getByText('Widget')).toBeInTheDocument();
    expect(screen.getByText(`${fmt(10)} TND / unit`)).toBeInTheDocument();
    expect(screen.getByText(`${fmt(20)} TND`)).toBeInTheDocument();
  });

  it('shows the initial letter placeholder when there is no image', () => {
    setup({ image: null });
    expect(screen.getByText('W')).toBeInTheDocument();
  });

  it('renders an <img> when the item has an image', () => {
    setup({ image: 'http://example.com/widget.png' });
    const img = screen.getByRole('img', { name: 'Widget' });
    expect(img).toHaveAttribute('src', 'http://example.com/widget.png');
  });

  it('shows a low-stock warning when stock is 5 or fewer', () => {
    setup({ stock: 3 });
    expect(screen.getByText('Only 3 left!')).toBeInTheDocument();
  });

  it('does not show a low-stock warning when stock is above 5', () => {
    setup({ stock: 20 });
    expect(screen.queryByText(/left!/)).not.toBeInTheDocument();
  });

  it('decrements the quantity when clicking "-" and qty > 1', async () => {
    const { updateQty, removeFromCart, user } = setup({ qty: 2 });
    await user.click(screen.getByText('−'));
    expect(updateQty).toHaveBeenCalledWith(1, 1);
    expect(removeFromCart).not.toHaveBeenCalled();
  });

  it('removes the item instead of decrementing when qty is 1', async () => {
    const { updateQty, removeFromCart, user } = setup({ qty: 1 });
    await user.click(screen.getByText('−'));
    expect(removeFromCart).toHaveBeenCalledWith(1);
    expect(updateQty).not.toHaveBeenCalled();
  });

  it('increments the quantity when clicking "+"', async () => {
    const { updateQty, user } = setup({ qty: 2, stock: 5 });
    await user.click(screen.getByText('+'));
    expect(updateQty).toHaveBeenCalledWith(1, 3);
  });

  it('disables the "+" button once qty reaches the available stock', () => {
    setup({ qty: 5, stock: 5 });
    expect(screen.getByText('+')).toBeDisabled();
  });

  it('does not disable "+" when stock is null (unknown/unbounded)', () => {
    setup({ qty: 999, stock: null });
    expect(screen.getByText('+')).not.toBeDisabled();
  });

  it('calls removeFromCart when clicking the delete button', async () => {
    const { removeFromCart, user } = setup();
    await user.click(screen.getByTitle('Remove'));
    expect(removeFromCart).toHaveBeenCalledWith(1);
  });
});
