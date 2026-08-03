import React from 'react';
import { render, screen } from '@testing-library/react';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import '../../i18n';
import { verifyEmail } from '../../services/authService';
import VerifyEmail from './VerifyEmail';

jest.mock('../../services/authService', () => ({
  verifyEmail: jest.fn(),
}));

function renderVerifyEmail(search = '?token=abc123') {
  return render(
    <MemoryRouter initialEntries={[`/verify-email${search}`]}>
      <Routes>
        <Route path="/verify-email" element={<VerifyEmail />} />
      </Routes>
    </MemoryRouter>,
  );
}

describe('VerifyEmail page', () => {
  it('shows an error immediately when no token is present, without calling the API', async () => {
    renderVerifyEmail('');
    expect(
      await screen.findByText('This confirmation link is missing its token.'),
    ).toBeInTheDocument();
    expect(verifyEmail).not.toHaveBeenCalled();
  });

  it('shows a success message when the token is verified', async () => {
    verifyEmail.mockResolvedValue({ message: 'All done, welcome aboard!' });
    renderVerifyEmail('?token=abc123');

    expect(await screen.findByText('All done, welcome aboard!')).toBeInTheDocument();
    expect(screen.getByText("You're all set!")).toBeInTheDocument();
    expect(verifyEmail).toHaveBeenCalledWith('abc123');
  });

  it('falls back to a default success message when the server sends none', async () => {
    verifyEmail.mockResolvedValue({});
    renderVerifyEmail('?token=abc123');

    expect(await screen.findByText('Your email has been confirmed.')).toBeInTheDocument();
  });

  it('shows the server error message when verification fails', async () => {
    verifyEmail.mockRejectedValue({ response: { data: { error: 'This link has expired.' } } });
    renderVerifyEmail('?token=abc123');

    expect(await screen.findByText('This link has expired.')).toBeInTheDocument();
    expect(screen.getByText('Confirmation failed')).toBeInTheDocument();
  });

  it('falls back to a default error message when the server sends none', async () => {
    verifyEmail.mockRejectedValue({});
    renderVerifyEmail('?token=abc123');

    expect(
      await screen.findByText('This confirmation link is invalid or has expired.'),
    ).toBeInTheDocument();
  });
});
