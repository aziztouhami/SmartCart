import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import '../../i18n';
import { getUser, logout, updateLocalUser } from '../../services/authService';
import { addressApi, profileApi, brandApi } from '../../services/cartService';
import { useCart } from '../../context/CartContext';
import { useCategories } from '../../context/CategoryContext';
import Profile from './Profile';

jest.mock('../../services/authService', () => ({
  getUser: jest.fn(),
  logout: jest.fn(),
  updateLocalUser: jest.fn(),
}));

jest.mock('../../services/cartService', () => ({
  addressApi: { list: jest.fn(), create: jest.fn(), update: jest.fn(), remove: jest.fn() },
  profileApi: {
    get: jest.fn(),
    update: jest.fn(),
    changePassword: jest.fn(),
    requestDeletion: jest.fn(),
  },
  brandApi: { list: jest.fn() },
}));

jest.mock('../../context/CartContext', () => ({ useCart: jest.fn() }));
jest.mock('../../context/CategoryContext', () => ({ useCategories: jest.fn() }));

jest.mock('./AddressMapModal', () => ({ initial, onSave, onClose }) => (
  <div data-testid="address-map-modal">
    <span data-testid="modal-mode">{initial ? 'edit' : 'add'}</span>
    <button
      onClick={() =>
        onSave({
          label: 'Home',
          street: '1 Main St',
          city: 'Tunis',
          postalCode: '1000',
          country: 'Tunisia',
          isDefault: false,
        })
      }
    >
      save address
    </button>
    <button onClick={onClose}>close modal</button>
  </div>
));

const baseUser = {
  firstName: 'Ada',
  lastName: 'Lovelace',
  email: 'ada@example.com',
  phone: '',
  roles: ['ROLE_USER'],
};

function renderProfile() {
  return render(
    <MemoryRouter future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
      <Routes>
        <Route path="/" element={<Profile />} />
        <Route path="/login" element={<div data-testid="login-page" />} />
      </Routes>
    </MemoryRouter>,
  );
}

describe('Profile page', () => {
  let resetCart;

  beforeEach(() => {
    getUser.mockReturnValue(baseUser);
    resetCart = jest.fn();
    useCart.mockReturnValue({ resetCart });
    useCategories.mockReturnValue({ leafCategories: [] });

    addressApi.list.mockResolvedValue({ data: [] });
    profileApi.get.mockResolvedValue({
      data: { marketingOptIn: false, preferredCategoryIds: [], preferredBrandIds: [] },
    });
    brandApi.list.mockResolvedValue({ data: { data: [] } });
  });

  it('renders the user name, email and initials', async () => {
    renderProfile();
    expect(screen.getByText('Ada Lovelace')).toBeInTheDocument();
    expect(screen.getByText('ada@example.com')).toBeInTheDocument();
    expect(screen.getByText('AL')).toBeInTheDocument();
  });

  it('shows an admin badge only for admin users', async () => {
    getUser.mockReturnValue({ ...baseUser, roles: ['ROLE_USER', 'ROLE_ADMIN'] });
    renderProfile();
    expect(screen.getByText('Admin')).toBeInTheDocument();
  });

  it('does not show an admin badge for regular users', () => {
    renderProfile();
    expect(screen.queryByText('Admin')).not.toBeInTheDocument();
  });

  it('saves personal info and shows a success toast', async () => {
    const user = userEvent.setup();
    profileApi.update.mockResolvedValue({
      data: { firstName: 'Ada', lastName: 'Byron', email: 'ada@example.com', phone: '' },
    });
    renderProfile();

    const lastNameInput = screen.getByPlaceholderText('Last name');
    await user.clear(lastNameInput);
    await user.type(lastNameInput, 'Byron');
    await user.click(screen.getByText('Save Changes'));

    await waitFor(() =>
      expect(profileApi.update).toHaveBeenCalledWith({
        firstName: 'Ada',
        lastName: 'Byron',
        email: 'ada@example.com',
        phone: '',
      }),
    );
    expect(updateLocalUser).toHaveBeenCalled();
    expect(await screen.findByText('Profile updated successfully.')).toBeInTheDocument();
  });

  it('shows a toast error when saving personal info fails', async () => {
    const user = userEvent.setup();
    profileApi.update.mockRejectedValue({ response: { data: { error: 'Email already taken.' } } });
    renderProfile();

    await user.click(screen.getByText('Save Changes'));
    expect(await screen.findByText('Email already taken.')).toBeInTheDocument();
  });

  it('validates the password form before calling the API', async () => {
    const user = userEvent.setup();
    renderProfile();

    await user.click(screen.getByText('Update Password'));
    expect(await screen.findByText('Enter your current password.')).toBeInTheDocument();
    expect(profileApi.changePassword).not.toHaveBeenCalled();
  });

  it('validates the new password length', async () => {
    const user = userEvent.setup();
    renderProfile();

    const [currentInput] = screen.getAllByPlaceholderText('••••••••');
    await user.type(currentInput, 'current-pw');
    await user.type(screen.getByPlaceholderText('min. 8 characters'), 'short');
    await user.click(screen.getByText('Update Password'));

    expect(
      await screen.findByText('New password must be at least 8 characters.'),
    ).toBeInTheDocument();
  });

  it('validates that the new password and confirmation match', async () => {
    const user = userEvent.setup();
    renderProfile();

    const [currentInput, confirmInput] = screen.getAllByPlaceholderText('••••••••');
    await user.type(currentInput, 'current-pw');
    await user.type(screen.getByPlaceholderText('min. 8 characters'), 'newpassword1');
    await user.type(confirmInput, 'newpassword2');
    await user.click(screen.getByText('Update Password'));

    expect(await screen.findByText('Passwords do not match.')).toBeInTheDocument();
  });

  it('changes the password successfully and clears the form', async () => {
    const user = userEvent.setup();
    profileApi.changePassword.mockResolvedValue({});
    renderProfile();

    const [currentInput, confirmInput] = screen.getAllByPlaceholderText('••••••••');
    await user.type(currentInput, 'current-pw');
    await user.type(screen.getByPlaceholderText('min. 8 characters'), 'newpassword1');
    await user.type(confirmInput, 'newpassword1');
    await user.click(screen.getByText('Update Password'));

    await waitFor(() =>
      expect(profileApi.changePassword).toHaveBeenCalledWith({
        currentPassword: 'current-pw',
        newPassword: 'newpassword1',
      }),
    );
    expect(await screen.findByText('Password changed successfully.')).toBeInTheDocument();
    expect(currentInput).toHaveValue('');
  });

  it('toggles marketing opt-in and reverts on failure', async () => {
    const user = userEvent.setup();
    profileApi.update.mockRejectedValue(new Error('network error'));
    renderProfile();
    await screen.findByText('Ada Lovelace');

    const checkbox = screen.getByRole('checkbox');
    await user.click(checkbox);

    expect(
      await screen.findByText('Failed to update notification preference.'),
    ).toBeInTheDocument();
    expect(checkbox).not.toBeChecked();
  });

  it('opens the delete-account confirm modal, cancels without deleting', async () => {
    const user = userEvent.setup();
    renderProfile();

    await user.click(screen.getByText('Delete My Account'));
    expect(screen.getByText('Delete your account?')).toBeInTheDocument();

    await user.click(screen.getByText('Cancel'));
    expect(screen.queryByText('Delete your account?')).not.toBeInTheDocument();
    expect(profileApi.requestDeletion).not.toHaveBeenCalled();
  });

  it('confirms account deletion, logs out and navigates to login', async () => {
    const user = userEvent.setup();
    profileApi.requestDeletion.mockResolvedValue({});
    renderProfile();

    await user.click(screen.getByText('Delete My Account'));
    await user.click(screen.getByText('Yes, Delete My Account'));

    await waitFor(() => expect(profileApi.requestDeletion).toHaveBeenCalled());
    expect(logout).toHaveBeenCalled();
    expect(resetCart).toHaveBeenCalled();
    expect(await screen.findByTestId('login-page')).toBeInTheDocument();
  });

  it('logs out via the nav button', async () => {
    const user = userEvent.setup();
    renderProfile();

    await user.click(screen.getByText('Log Out'));
    expect(logout).toHaveBeenCalled();
    expect(resetCart).toHaveBeenCalled();
    expect(await screen.findByTestId('login-page')).toBeInTheDocument();
  });

  it('shows the loading message, then the empty-address state', async () => {
    renderProfile();
    expect(await screen.findByText('No addresses saved yet.')).toBeInTheDocument();
  });

  it('renders saved addresses with default badge and coordinates', async () => {
    addressApi.list.mockResolvedValue({
      data: [
        {
          id: 1,
          label: 'Home',
          street: '1 Main St',
          city: 'Tunis',
          postalCode: '1000',
          country: 'Tunisia',
          isDefault: true,
          lat: 36.8,
          lng: 10.18,
        },
      ],
    });
    renderProfile();

    expect(await screen.findByText('1 Main St')).toBeInTheDocument();
    expect(screen.getByText('Default')).toBeInTheDocument();
    expect(screen.getByText('36.80000, 10.18000')).toBeInTheDocument();
    expect(screen.queryByText('Set Default')).not.toBeInTheDocument();
  });

  it('opens the add-address modal and saves a new address', async () => {
    const user = userEvent.setup();
    addressApi.create.mockResolvedValue({
      data: { id: 9, label: 'Home', street: '1 Main St', city: 'Tunis', isDefault: false },
    });
    renderProfile();
    await screen.findByText('No addresses saved yet.');

    await user.click(screen.getByText('Add Address'));
    expect(await screen.findByTestId('address-map-modal')).toBeInTheDocument();
    expect(screen.getByTestId('modal-mode')).toHaveTextContent('add');

    await user.click(screen.getByText('save address'));

    await waitFor(() => expect(addressApi.create).toHaveBeenCalled());
    expect(await screen.findByText('Address added.')).toBeInTheDocument();
  });

  it('opens the edit-address modal pre-filled for an existing address', async () => {
    const user = userEvent.setup();
    addressApi.list.mockResolvedValue({
      data: [{ id: 1, label: 'Home', street: '1 Main St', city: 'Tunis', isDefault: false }],
    });
    renderProfile();
    await screen.findByText('1 Main St');

    await user.click(screen.getByText('Edit'));
    expect(await screen.findByTestId('modal-mode')).toHaveTextContent('edit');
  });

  it('sets an address as default', async () => {
    const user = userEvent.setup();
    addressApi.list.mockResolvedValue({
      data: [{ id: 1, label: 'Home', street: '1 Main St', city: 'Tunis', isDefault: false }],
    });
    addressApi.update.mockResolvedValue({});
    renderProfile();
    await screen.findByText('1 Main St');

    await user.click(screen.getByText('Set Default'));
    await waitFor(() => expect(addressApi.update).toHaveBeenCalledWith(1, { isDefault: true }));
    expect(await screen.findByText('Default address updated.')).toBeInTheDocument();
  });

  it('deletes an address', async () => {
    const user = userEvent.setup();
    addressApi.list.mockResolvedValue({
      data: [{ id: 1, label: 'Home', street: '1 Main St', city: 'Tunis', isDefault: false }],
    });
    addressApi.remove.mockResolvedValue({});
    renderProfile();
    await screen.findByText('1 Main St');

    await user.click(screen.getByText('Delete'));
    await waitFor(() => expect(addressApi.remove).toHaveBeenCalledWith(1));
    expect(screen.queryByText('1 Main St')).not.toBeInTheDocument();
  });

  it('renders category and brand preference chips, and toggles them', async () => {
    useCategories.mockReturnValue({ leafCategories: [{ id: 1, name: 'Phones' }] });
    brandApi.list.mockResolvedValue({ data: { data: [{ id: 2, name: 'Apple' }] } });
    profileApi.update.mockResolvedValue({});
    const user = userEvent.setup();
    renderProfile();

    const phonesChip = await screen.findByText('Phones');
    await user.click(phonesChip);

    await waitFor(() =>
      expect(profileApi.update).toHaveBeenCalledWith({ preferredCategoryIds: [1] }),
    );
    expect(phonesChip).toHaveClass('pf-pref-chip--active');
  });
});
