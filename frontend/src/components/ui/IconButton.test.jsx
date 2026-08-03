import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import IconButton from './IconButton';

describe('IconButton', () => {
  it('renders its children', () => {
    render(<IconButton>★</IconButton>);
    expect(screen.getByText('★')).toBeInTheDocument();
  });

  it('defaults to an inactive, 32px button', () => {
    render(<IconButton>x</IconButton>);
    const button = screen.getByRole('button');
    expect(button).not.toHaveClass('ui-icon-btn--active');
    expect(button).toHaveStyle({ width: '32px', height: '32px' });
  });

  it('applies the active class when active', () => {
    render(<IconButton active>x</IconButton>);
    expect(screen.getByRole('button')).toHaveClass('ui-icon-btn--active');
  });

  it('applies a custom size', () => {
    render(<IconButton size={20}>x</IconButton>);
    expect(screen.getByRole('button')).toHaveStyle({ width: '20px', height: '20px' });
  });

  it('merges in an additional className', () => {
    render(<IconButton className="custom">x</IconButton>);
    expect(screen.getByRole('button')).toHaveClass('ui-icon-btn', 'custom');
  });

  it('forwards arbitrary props like onClick and disabled', async () => {
    const user = userEvent.setup();
    const onClick = jest.fn();
    render(
      <IconButton onClick={onClick} title="Remove">
        x
      </IconButton>,
    );
    await user.click(screen.getByTitle('Remove'));
    expect(onClick).toHaveBeenCalled();
  });
});
