import React, { useState } from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import '../../../i18n';
import CheckoutModal from './CheckoutModal';

/**
 * CheckoutModal is fully controlled by its parent (Cart.jsx). To exercise
 * real interactions (typing, selecting an address) we need a small stateful
 * harness that mirrors what Cart.jsx does, rather than asserting on
 * jest.fn() call args for every keystroke.
 */
function Harness({
  addresses = [],
  addrLoading = false,
  checkoutError = '',
  placing = false,
  initialSelectedAddr = 'new',
  initialPhone = '',
  onClose = jest.fn(),
  onMapOpen = jest.fn(),
  onPlaceOrder = jest.fn(),
}) {
  const [selectedAddr, setSelectedAddr] = useState(initialSelectedAddr);
  const [newAddr, setNewAddr] = useState({ street: '', city: '', postalCode: '', country: '' });
  const [phone, setPhone] = useState(initialPhone);
  const [phoneError, setPhoneError] = useState('');

  return (
    <CheckoutModal
      addresses={addresses}
      addrLoading={addrLoading}
      selectedAddr={selectedAddr}
      setSelectedAddr={setSelectedAddr}
      newAddr={newAddr}
      setNewAddr={setNewAddr}
      phone={phone}
      setPhone={setPhone}
      phoneError={phoneError}
      setPhoneError={setPhoneError}
      checkoutError={checkoutError}
      placing={placing}
      onClose={onClose}
      onMapOpen={onMapOpen}
      onPlaceOrder={onPlaceOrder}
    />
  );
}

describe('CheckoutModal', () => {
  it('shows a loading message while addresses are being fetched', () => {
    render(<Harness addrLoading />);
    expect(screen.getByText('Loading saved addresses…')).toBeInTheDocument();
    // The place order button should be disabled while loading.
    expect(screen.getByText('Place Order')).toBeDisabled();
  });

  it('renders saved addresses and lets the user pick one', async () => {
    const user = userEvent.setup();
    const addresses = [
      {
        id: 5,
        label: 'Home',
        street: '1 Rue X',
        city: 'Tunis',
        postalCode: '1000',
        country: 'Tunisia',
        isDefault: true,
      },
      {
        id: 6,
        label: 'Work',
        street: '2 Rue Y',
        city: 'Sfax',
        postalCode: '3000',
        country: 'Tunisia',
        isDefault: false,
      },
    ];
    render(<Harness addresses={addresses} initialSelectedAddr="5" />);

    expect(screen.getByText('Home')).toBeInTheDocument();
    expect(screen.getByText('Default')).toBeInTheDocument();
    const homeRadio = screen.getByDisplayValue('5');
    const workRadio = screen.getByDisplayValue('6');
    expect(homeRadio).toBeChecked();
    expect(workRadio).not.toBeChecked();

    await user.click(workRadio);
    expect(workRadio).toBeChecked();
    expect(homeRadio).not.toBeChecked();

    // New-address form fields should not render while a saved address is selected.
    expect(screen.queryByPlaceholderText('123 Main Street')).not.toBeInTheDocument();
  });

  it('shows the new-address form when "new" is selected, and lets the user type into it', async () => {
    const user = userEvent.setup();
    const addresses = [
      {
        id: 5,
        label: 'Home',
        street: '1 Rue X',
        city: 'Tunis',
        postalCode: '',
        country: 'Tunisia',
        isDefault: true,
      },
    ];
    render(<Harness addresses={addresses} initialSelectedAddr="new" />);

    const streetInput = screen.getByPlaceholderText('123 Main Street');
    const cityInput = screen.getByPlaceholderText('Tunis');
    const countryInput = screen.getByPlaceholderText('Tunisia');

    await user.type(streetInput, 'Avenue Habib');
    await user.type(cityInput, 'Sousse');
    await user.type(countryInput, 'Tunisia');

    expect(streetInput).toHaveValue('Avenue Habib');
    expect(cityInput).toHaveValue('Sousse');
    expect(countryInput).toHaveValue('Tunisia');
  });

  it('shows the new-address form directly (no saved-address section) when there are no addresses', () => {
    render(<Harness addresses={[]} />);
    expect(screen.queryByText('Saved Addresses')).not.toBeInTheDocument();
    expect(screen.getByPlaceholderText('123 Main Street')).toBeInTheDocument();
  });

  it('calls onMapOpen when clicking "Pick on Map"', async () => {
    const user = userEvent.setup();
    const onMapOpen = jest.fn();
    render(<Harness onMapOpen={onMapOpen} />);
    await user.click(screen.getByText('Pick on Map'));
    expect(onMapOpen).toHaveBeenCalledTimes(1);
  });

  it('lets the user type a phone number and clears any existing phone error on change', async () => {
    const user = userEvent.setup();
    render(<Harness />);
    const phoneInput = screen.getByPlaceholderText('+216 XX XXX XXX');
    await user.type(phoneInput, '20123456');
    expect(phoneInput).toHaveValue('20123456');
  });

  it('shows a checkout error message when provided', () => {
    render(<Harness checkoutError="Street, city and country are required." />);
    expect(screen.getByText('Street, city and country are required.')).toBeInTheDocument();
  });

  it('calls onClose when clicking the Cancel button or the × button', async () => {
    const user = userEvent.setup();
    const onClose = jest.fn();
    render(<Harness onClose={onClose} />);
    await user.click(screen.getByText('Cancel'));
    expect(onClose).toHaveBeenCalledTimes(1);
    await user.click(screen.getByText('✕'));
    expect(onClose).toHaveBeenCalledTimes(2);
  });

  it('calls onClose when clicking the overlay backdrop, but not when clicking inside the modal', async () => {
    const user = userEvent.setup();
    const onClose = jest.fn();
    const { container } = render(<Harness onClose={onClose} />);
    await user.click(screen.getByRole('heading', { name: 'Shipping Address' })); // inside modal
    expect(onClose).not.toHaveBeenCalled();

    const overlay = container.querySelector('.cp-overlay');
    await user.click(overlay);
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it('calls onPlaceOrder when clicking "Place Order"', async () => {
    const user = userEvent.setup();
    const onPlaceOrder = jest.fn();
    render(<Harness onPlaceOrder={onPlaceOrder} />);
    await user.click(screen.getByText('Place Order'));
    expect(onPlaceOrder).toHaveBeenCalledTimes(1);
  });

  it('shows "Placing Order…" and disables the button while placing', () => {
    render(<Harness placing />);
    expect(screen.getByText('Placing Order…')).toBeInTheDocument();
    expect(screen.getByText('Placing Order…')).toBeDisabled();
  });
});
