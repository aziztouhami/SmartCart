import React from 'react';
import { render, screen, waitFor, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import '../../i18n';
import { login, googleLogin, resendVerification } from '../../services/authService';
import { useCart } from '../../context/CartContext';
import { useFavorites } from '../../context/FavoriteContext';
import Login from './Login';

jest.mock('../../services/authService', () => ({
  login: jest.fn(),
  googleLogin: jest.fn(),
  resendVerification: jest.fn(),
}));

jest.mock('../../context/CartContext', () => ({
  useCart: jest.fn(),
}));

jest.mock('../../context/FavoriteContext', () => ({
  useFavorites: jest.fn(),
}));

// Captures the options useGoogleLogin was called with so tests can trigger
// onSuccess/onError directly, without needing a real GoogleOAuthProvider or
// Google's script.
let capturedGoogleOptions;
jest.mock('@react-oauth/google', () => ({
  useGoogleLogin: opts => {
    capturedGoogleOptions = opts;
    return jest.fn();
  },
}));

function renderLogin({ route = '/login', state } = {}) {
  const entry = state ? { pathname: route, state } : route;
  return render(
    <MemoryRouter
      initialEntries={[entry]}
      future={{ v7_startTransition: true, v7_relativeSplatPath: true }}
    >
      <Routes>
        <Route path="/login" element={<Login />} />
        <Route path="/register" element={<div data-testid="register-page" />} />
        <Route path="/" element={<div data-testid="home-page" />} />
        <Route path="/admin" element={<div data-testid="admin-page" />} />
        <Route path="/cart" element={<div data-testid="cart-page" />} />
      </Routes>
    </MemoryRouter>,
  );
}

describe('Login page', () => {
  let syncWithBackend;
  let loadFavorites;

  beforeEach(() => {
    capturedGoogleOptions = undefined;
    syncWithBackend = jest.fn().mockResolvedValue();
    loadFavorites = jest.fn().mockResolvedValue();
    useCart.mockReturnValue({ syncWithBackend });
    useFavorites.mockReturnValue({ loadFavorites });
  });

  it('renders the email/password fields and the sign-in button', () => {
    renderLogin();
    expect(screen.getByTestId('login-email')).toBeInTheDocument();
    expect(screen.getByTestId('login-password')).toBeInTheDocument();
    expect(screen.getByTestId('login-submit')).toHaveTextContent('Sign In');
  });

  it('shows a validation error when submitting with empty fields', async () => {
    const user = userEvent.setup();
    renderLogin();
    await user.click(screen.getByTestId('login-submit'));
    expect(await screen.findByTestId('login-error')).toHaveTextContent(
      'Please fill in all fields.',
    );
    expect(login).not.toHaveBeenCalled();
  });

  it('logs in a regular user and navigates home', async () => {
    const user = userEvent.setup();
    login.mockResolvedValue({ user: { id: 1, roles: ['ROLE_USER'] } });
    renderLogin();

    await user.type(screen.getByTestId('login-email'), 'ada@example.com');
    await user.type(screen.getByTestId('login-password'), 'secret123');
    await user.click(screen.getByTestId('login-submit'));

    await waitFor(() => expect(login).toHaveBeenCalledWith('ada@example.com', 'secret123'));
    expect(syncWithBackend).toHaveBeenCalled();
    expect(loadFavorites).toHaveBeenCalled();
    expect(await screen.findByTestId('home-page')).toBeInTheDocument();
  });

  it('sends an admin user to /admin', async () => {
    const user = userEvent.setup();
    login.mockResolvedValue({ user: { id: 1, roles: ['ROLE_USER', 'ROLE_ADMIN'] } });
    renderLogin();

    await user.type(screen.getByTestId('login-email'), 'admin@example.com');
    await user.type(screen.getByTestId('login-password'), 'secret123');
    await user.click(screen.getByTestId('login-submit'));

    expect(await screen.findByTestId('admin-page')).toBeInTheDocument();
  });

  it('redirects to the `from` location after a successful login instead of home', async () => {
    const user = userEvent.setup();
    login.mockResolvedValue({ user: { id: 1, roles: ['ROLE_USER'] } });
    renderLogin({ state: { from: '/cart' } });

    await user.type(screen.getByTestId('login-email'), 'ada@example.com');
    await user.type(screen.getByTestId('login-password'), 'secret123');
    await user.click(screen.getByTestId('login-submit'));

    expect(await screen.findByTestId('cart-page')).toBeInTheDocument();
  });

  it('shows the server error message on invalid credentials, with no resend option', async () => {
    const user = userEvent.setup();
    login.mockRejectedValue({ response: { data: { error: 'Invalid credentials.' } } });
    renderLogin();

    await user.type(screen.getByTestId('login-email'), 'ada@example.com');
    await user.type(screen.getByTestId('login-password'), 'wrong');
    await user.click(screen.getByTestId('login-submit'));

    expect(await screen.findByTestId('login-error')).toHaveTextContent('Invalid credentials.');
    expect(screen.queryByText('Resend confirmation email')).not.toBeInTheDocument();
  });

  it('falls back to a generic message when the server sends no error text', async () => {
    const user = userEvent.setup();
    login.mockRejectedValue({});
    renderLogin();

    await user.type(screen.getByTestId('login-email'), 'ada@example.com');
    await user.type(screen.getByTestId('login-password'), 'wrong');
    await user.click(screen.getByTestId('login-submit'));

    expect(await screen.findByTestId('login-error')).toHaveTextContent(
      'Invalid credentials. Please try again.',
    );
  });

  describe('EMAIL_NOT_VERIFIED branch', () => {
    it('shows a resend-confirmation button when the backend returns code EMAIL_NOT_VERIFIED', async () => {
      const user = userEvent.setup();
      login.mockRejectedValue({
        response: {
          data: { error: 'Please verify your email first.', code: 'EMAIL_NOT_VERIFIED' },
        },
      });
      renderLogin();

      await user.type(screen.getByTestId('login-email'), 'ada@example.com');
      await user.type(screen.getByTestId('login-password'), 'secret123');
      await user.click(screen.getByTestId('login-submit'));

      const errorBox = await screen.findByTestId('login-error');
      expect(errorBox).toHaveTextContent('Please verify your email first.');
      expect(screen.getByText('Resend confirmation email')).toBeInTheDocument();
    });

    it('resends the verification email and shows a confirmation once sent', async () => {
      const user = userEvent.setup();
      login.mockRejectedValue({
        response: {
          data: { error: 'Please verify your email first.', code: 'EMAIL_NOT_VERIFIED' },
        },
      });
      resendVerification.mockResolvedValue();
      renderLogin();

      await user.type(screen.getByTestId('login-email'), 'ada@example.com');
      await user.type(screen.getByTestId('login-password'), 'secret123');
      await user.click(screen.getByTestId('login-submit'));
      await screen.findByText('Resend confirmation email');

      await user.click(screen.getByText('Resend confirmation email'));

      expect(resendVerification).toHaveBeenCalledWith('ada@example.com');
      expect(
        await screen.findByText('Confirmation email resent — check your inbox.'),
      ).toBeInTheDocument();
      expect(screen.queryByText('Resend confirmation email')).not.toBeInTheDocument();
    });

    it('re-enables the resend button if resending itself fails', async () => {
      const user = userEvent.setup();
      login.mockRejectedValue({
        response: {
          data: { error: 'Please verify your email first.', code: 'EMAIL_NOT_VERIFIED' },
        },
      });
      resendVerification.mockRejectedValue(new Error('network error'));
      renderLogin();

      await user.type(screen.getByTestId('login-email'), 'ada@example.com');
      await user.type(screen.getByTestId('login-password'), 'secret123');
      await user.click(screen.getByTestId('login-submit'));
      await screen.findByText('Resend confirmation email');

      await user.click(screen.getByText('Resend confirmation email'));

      await waitFor(() => expect(resendVerification).toHaveBeenCalled());
      expect(await screen.findByText('Resend confirmation email')).toBeInTheDocument();
    });

    it('does not show a resend option for any other error code', async () => {
      const user = userEvent.setup();
      login.mockRejectedValue({
        response: { data: { error: 'Account locked.', code: 'ACCOUNT_LOCKED' } },
      });
      renderLogin();

      await user.type(screen.getByTestId('login-email'), 'ada@example.com');
      await user.type(screen.getByTestId('login-password'), 'secret123');
      await user.click(screen.getByTestId('login-submit'));

      expect(await screen.findByTestId('login-error')).toHaveTextContent('Account locked.');
      expect(screen.queryByText('Resend confirmation email')).not.toBeInTheDocument();
    });
  });

  it('shows the registration-success banner with the prefilled email when redirected from Register', () => {
    renderLogin({ state: { checkEmail: true, prefillEmail: 'new@example.com' } });
    expect(
      screen.getByText(
        'Registration successful! Check your inbox at new@example.com to confirm your account before signing in.',
      ),
    ).toBeInTheDocument();
    expect(screen.getByTestId('login-email')).toHaveValue('new@example.com');
  });

  it('clears a previous error as soon as the user edits a field', async () => {
    const user = userEvent.setup();
    login.mockRejectedValueOnce({ response: { data: { error: 'Invalid credentials.' } } });
    renderLogin();

    await user.type(screen.getByTestId('login-email'), 'ada@example.com');
    await user.type(screen.getByTestId('login-password'), 'wrong');
    await user.click(screen.getByTestId('login-submit'));
    expect(await screen.findByTestId('login-error')).toBeInTheDocument();

    await user.type(screen.getByTestId('login-password'), 'x');
    expect(screen.queryByTestId('login-error')).not.toBeInTheDocument();
  });

  it('toggles the password visibility', async () => {
    const user = userEvent.setup();
    renderLogin();
    const passwordInput = screen.getByTestId('login-password');
    expect(passwordInput).toHaveAttribute('type', 'password');
    await user.click(screen.getByLabelText('Show password'));
    expect(passwordInput).toHaveAttribute('type', 'text');
    await user.click(screen.getByLabelText('Hide password'));
    expect(passwordInput).toHaveAttribute('type', 'password');
  });

  it('has a link to the register page', () => {
    renderLogin();
    expect(screen.getByText('Create Account').closest('a')).toHaveAttribute('href', '/register');
  });

  describe('Google sign-in', () => {
    it('logs in via Google, syncs cart/favorites, and navigates home for a non-admin user', async () => {
      googleLogin.mockResolvedValue({ user: { id: 2, roles: ['ROLE_USER'] } });
      renderLogin();

      expect(capturedGoogleOptions).toBeDefined();
      await act(async () => {
        await capturedGoogleOptions.onSuccess({ access_token: 'tok-123' });
      });

      await waitFor(() => expect(googleLogin).toHaveBeenCalledWith('tok-123'));
      expect(syncWithBackend).toHaveBeenCalled();
      expect(loadFavorites).toHaveBeenCalled();
      expect(await screen.findByTestId('home-page')).toBeInTheDocument();
    });

    it('sends an admin Google user to /admin', async () => {
      googleLogin.mockResolvedValue({ user: { id: 2, roles: ['ROLE_ADMIN'] } });
      renderLogin();

      await act(async () => {
        await capturedGoogleOptions.onSuccess({ access_token: 'tok-123' });
      });
      expect(await screen.findByTestId('admin-page')).toBeInTheDocument();
    });

    it('shows an error message when the Google login backend call fails', async () => {
      googleLogin.mockRejectedValue({
        response: { data: { error: 'Google account not linked.' } },
      });
      renderLogin();

      await act(async () => {
        await capturedGoogleOptions.onSuccess({ access_token: 'tok-123' });
      });
      expect(await screen.findByTestId('login-error')).toHaveTextContent(
        'Google account not linked.',
      );
    });

    it('shows a generic error message when the Google popup itself fails', async () => {
      renderLogin();
      act(() => {
        capturedGoogleOptions.onError();
      });
      expect(await screen.findByTestId('login-error')).toHaveTextContent(
        'Google sign in failed. Please try again.',
      );
    });
  });
});
