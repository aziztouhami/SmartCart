import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import '../../../i18n';
import { formatPrice as fmt } from '../../../utils/format';
import OrderConfirmModal from './OrderConfirmModal';

const items = [
  { id: 1, name: 'Widget', price: 10, qty: 2 },
  { id: 2, name: 'Gadget', price: 5, qty: 1 },
];

const address = {
  street: '1 Rue X',
  city: 'Tunis',
  postalCode: '1000',
  country: 'Tunisia',
};

function setup(overrides = {}) {
  const onBack = jest.fn();
  const onConfirm = jest.fn();
  const props = {
    items,
    cartTotal: 25,
    address,
    phone: '  20123456  ',
    checkoutError: '',
    placing: false,
    onBack,
    onConfirm,
    ...overrides,
  };
  const user = userEvent.setup();
  const utils = render(<OrderConfirmModal {...props} />);
  return { ...utils, onBack, onConfirm, user };
}

describe('OrderConfirmModal', () => {
  it('lists each cart item with its quantity and line subtotal', () => {
    setup();
    expect(screen.getByText('Widget × 2')).toBeInTheDocument();
    expect(screen.getByText(`${fmt(20)} TND`)).toBeInTheDocument();
    expect(screen.getByText('Gadget × 1')).toBeInTheDocument();
    expect(screen.getByText(`${fmt(5)} TND`)).toBeInTheDocument();
  });

  it('shows the shipping address and the trimmed phone number', () => {
    setup();
    expect(screen.getByText('1 Rue X')).toBeInTheDocument();
    expect(screen.getByText('Tunis 1000, Tunisia')).toBeInTheDocument();
    expect(screen.getByText('20123456')).toBeInTheDocument();
  });

  it('omits the postal code segment when the address has none', () => {
    setup({ address: { ...address, postalCode: '' } });
    expect(screen.getByText('Tunis, Tunisia')).toBeInTheDocument();
  });

  it('shows the cart total', () => {
    setup({ cartTotal: 25 });
    expect(screen.getByText(`${fmt(25)} TND`)).toBeInTheDocument();
  });

  it('shows a checkout error message when provided', () => {
    setup({ checkoutError: 'Failed to place order. Please try again.' });
    expect(screen.getByText('Failed to place order. Please try again.')).toBeInTheDocument();
  });

  it('calls onConfirm when clicking "Confirm Order"', async () => {
    const { onConfirm, user } = setup();
    await user.click(screen.getByText('Confirm Order'));
    expect(onConfirm).toHaveBeenCalledTimes(1);
  });

  it('calls onBack when clicking "Back" or the × button', async () => {
    const { onBack, user } = setup();
    await user.click(screen.getByText('Back'));
    expect(onBack).toHaveBeenCalledTimes(1);
    await user.click(screen.getByText('✕'));
    expect(onBack).toHaveBeenCalledTimes(2);
  });

  it('calls onBack when clicking the overlay backdrop while not placing', async () => {
    const { onBack, container, user } = setup({ placing: false });
    const overlay = container.querySelector('.cp-overlay');
    await user.click(overlay);
    expect(onBack).toHaveBeenCalledTimes(1);
  });

  it('disables Back/Confirm and shows "Placing Order…" while placing, and ignores backdrop clicks', async () => {
    const { onBack, container, user } = setup({ placing: true });
    expect(screen.getByText('Back')).toBeDisabled();
    expect(screen.getByText('Placing Order…')).toBeInTheDocument();
    expect(screen.getByText('Placing Order…')).toBeDisabled();

    const overlay = container.querySelector('.cp-overlay');
    await user.click(overlay);
    expect(onBack).not.toHaveBeenCalled();

    await user.click(screen.getByRole('button', { name: '✕' }));
    expect(onBack).not.toHaveBeenCalled();
  });
});
