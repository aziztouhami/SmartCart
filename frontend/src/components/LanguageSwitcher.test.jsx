import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import i18n from '../i18n';
import LanguageSwitcher from './LanguageSwitcher';

describe('LanguageSwitcher', () => {
  beforeEach(async () => {
    await i18n.changeLanguage('en');
  });

  it('renders EN and FR buttons', () => {
    render(<LanguageSwitcher />);
    expect(screen.getByText('EN')).toBeInTheDocument();
    expect(screen.getByText('FR')).toBeInTheDocument();
  });

  it('marks English active by default', () => {
    render(<LanguageSwitcher />);
    expect(screen.getByText('EN')).toHaveClass('h-lang-btn--active');
    expect(screen.getByText('FR')).not.toHaveClass('h-lang-btn--active');
  });

  it('switches to French and marks it active', async () => {
    const user = userEvent.setup();
    render(<LanguageSwitcher />);

    await user.click(screen.getByText('FR'));

    expect(i18n.language).toBe('fr');
    expect(screen.getByText('FR')).toHaveClass('h-lang-btn--active');
    expect(screen.getByText('EN')).not.toHaveClass('h-lang-btn--active');
  });

  it('has an accessible group label', () => {
    render(<LanguageSwitcher />);
    expect(screen.getByRole('group', { name: 'Language' })).toBeInTheDocument();
  });
});
