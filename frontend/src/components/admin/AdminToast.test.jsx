import React from 'react';
import { render, screen } from '@testing-library/react';
import AdminToast from './AdminToast';

describe('AdminToast', () => {
  it('renders the message', () => {
    render(<AdminToast msg="Saved successfully." />);
    expect(screen.getByText('Saved successfully.')).toBeInTheDocument();
  });

  it('defaults to the success style', () => {
    render(<AdminToast msg="Saved." />);
    expect(screen.getByText('Saved.')).toHaveClass('ac-toast--success');
  });

  it('applies the given type as a modifier class', () => {
    render(<AdminToast msg="Something broke." type="error" />);
    expect(screen.getByText('Something broke.')).toHaveClass('ac-toast--error');
  });
});
