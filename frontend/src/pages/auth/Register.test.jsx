import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import '../../i18n';
import { register } from '../../services/authService';
import { categoryApi, brandApi } from '../../services/cartService';
import Register from './Register';

jest.mock('../../services/authService', () => ({
  register: jest.fn(),
}));

jest.mock('../../services/cartService', () => ({
  categoryApi: { list: jest.fn() },
  brandApi: { list: jest.fn() },
}));

function renderRegister() {
  return render(
    <MemoryRouter
      initialEntries={['/register']}
      future={{ v7_startTransition: true, v7_relativeSplatPath: true }}
    >
      <Routes>
        <Route path="/register" element={<Register />} />
        <Route path="/login" element={<div data-testid="login-page" />} />
      </Routes>
    </MemoryRouter>,
  );
}

describe('Register page', () => {
  beforeEach(() => {
    categoryApi.list.mockResolvedValue({ data: [] });
    brandApi.list.mockResolvedValue({ data: { data: [] } });
  });

  it('renders the registration fields and submit button', async () => {
    renderRegister();
    expect(screen.getByTestId('register-firstName')).toBeInTheDocument();
    expect(screen.getByTestId('register-lastName')).toBeInTheDocument();
    expect(screen.getByTestId('register-email')).toBeInTheDocument();
    expect(screen.getByTestId('register-password')).toBeInTheDocument();
    expect(screen.getByTestId('register-confirmPassword')).toBeInTheDocument();
    expect(screen.getByTestId('register-submit')).toBeInTheDocument();
  });

  it('shows a validation error when names are missing', async () => {
    const user = userEvent.setup();
    renderRegister();
    await user.click(screen.getByTestId('register-submit'));
    expect(await screen.findByTestId('register-error')).toBeInTheDocument();
    expect(register).not.toHaveBeenCalled();
  });

  it('shows a validation error for an invalid email', async () => {
    const user = userEvent.setup();
    renderRegister();
    await user.type(screen.getByTestId('register-firstName'), 'Ada');
    await user.type(screen.getByTestId('register-lastName'), 'Lovelace');
    await user.type(screen.getByTestId('register-email'), 'not-an-email');
    await user.type(screen.getByTestId('register-password'), 'password123');
    await user.type(screen.getByTestId('register-confirmPassword'), 'password123');
    await user.click(screen.getByTestId('register-submit'));

    expect(await screen.findByTestId('register-error')).toBeInTheDocument();
    expect(register).not.toHaveBeenCalled();
  });

  it('shows a validation error when the password is too short', async () => {
    const user = userEvent.setup();
    renderRegister();
    await user.type(screen.getByTestId('register-firstName'), 'Ada');
    await user.type(screen.getByTestId('register-lastName'), 'Lovelace');
    await user.type(screen.getByTestId('register-email'), 'ada@example.com');
    await user.type(screen.getByTestId('register-password'), 'short');
    await user.type(screen.getByTestId('register-confirmPassword'), 'short');
    await user.click(screen.getByTestId('register-submit'));

    expect(await screen.findByTestId('register-error')).toBeInTheDocument();
    expect(register).not.toHaveBeenCalled();
  });

  it('shows a validation error when passwords do not match', async () => {
    const user = userEvent.setup();
    renderRegister();
    await user.type(screen.getByTestId('register-firstName'), 'Ada');
    await user.type(screen.getByTestId('register-lastName'), 'Lovelace');
    await user.type(screen.getByTestId('register-email'), 'ada@example.com');
    await user.type(screen.getByTestId('register-password'), 'password123');
    await user.type(screen.getByTestId('register-confirmPassword'), 'password456');
    await user.click(screen.getByTestId('register-submit'));

    expect(await screen.findByTestId('register-error')).toBeInTheDocument();
    expect(register).not.toHaveBeenCalled();
  });

  it('registers successfully and redirects to login with the checkEmail banner', async () => {
    const user = userEvent.setup();
    register.mockResolvedValue({ email: 'ada@example.com' });
    renderRegister();

    await user.type(screen.getByTestId('register-firstName'), 'Ada');
    await user.type(screen.getByTestId('register-lastName'), 'Lovelace');
    await user.type(screen.getByTestId('register-email'), 'ada@example.com');
    await user.type(screen.getByTestId('register-password'), 'password123');
    await user.type(screen.getByTestId('register-confirmPassword'), 'password123');
    await user.click(screen.getByTestId('register-submit'));

    await waitFor(() =>
      expect(register).toHaveBeenCalledWith(
        'Ada',
        'Lovelace',
        'ada@example.com',
        'password123',
        false,
        [],
        [],
      ),
    );
    expect(await screen.findByTestId('login-page')).toBeInTheDocument();
  });

  it('shows the server error message when registration fails', async () => {
    const user = userEvent.setup();
    register.mockRejectedValue({ response: { data: { error: 'Email already in use.' } } });
    renderRegister();

    await user.type(screen.getByTestId('register-firstName'), 'Ada');
    await user.type(screen.getByTestId('register-lastName'), 'Lovelace');
    await user.type(screen.getByTestId('register-email'), 'ada@example.com');
    await user.type(screen.getByTestId('register-password'), 'password123');
    await user.type(screen.getByTestId('register-confirmPassword'), 'password123');
    await user.click(screen.getByTestId('register-submit'));

    expect(await screen.findByTestId('register-error')).toHaveTextContent('Email already in use.');
  });

  it('toggles password visibility independently for password and confirm password', async () => {
    const user = userEvent.setup();
    renderRegister();

    const passwordInput = screen.getByTestId('register-password');
    const confirmInput = screen.getByTestId('register-confirmPassword');
    expect(passwordInput).toHaveAttribute('type', 'password');
    expect(confirmInput).toHaveAttribute('type', 'password');

    await user.click(screen.getByLabelText('Show password'));
    expect(passwordInput).toHaveAttribute('type', 'text');
    expect(confirmInput).toHaveAttribute('type', 'password');
  });

  it('renders category and brand preference chips and toggles selection', async () => {
    categoryApi.list.mockResolvedValue({
      data: [{ id: 1, name: 'Electronics', children: [{ id: 10, name: 'Phones' }] }],
    });
    brandApi.list.mockResolvedValue({ data: { data: [{ id: 5, name: 'Apple' }] } });
    const user = userEvent.setup();
    renderRegister();

    const phoneChip = await screen.findByText('Phones');
    const appleChip = await screen.findByText('Apple');

    await user.click(phoneChip);
    await user.click(appleChip);

    expect(phoneChip).toHaveClass('auth-pref-chip--active');
    expect(appleChip).toHaveClass('auth-pref-chip--active');
  });

  it('has a link back to the login page', () => {
    renderRegister();
    expect(screen.getByText('Sign in').closest('a')).toHaveAttribute('href', '/login');
  });
});
