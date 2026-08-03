import React from 'react';
import { render, screen } from '@testing-library/react';
import Badge from './Badge';

describe('Badge', () => {
  it('renders its children', () => {
    render(<Badge>Promotion</Badge>);
    expect(screen.getByText('Promotion')).toBeInTheDocument();
  });

  it('applies default tone/variant/size classes', () => {
    render(<Badge>Default</Badge>);
    expect(screen.getByText('Default')).toHaveClass('ui-badge--neutral-soft', 'ui-badge--sm');
  });

  it('applies the requested tone, variant, and size', () => {
    render(
      <Badge tone="danger" variant="solid" size="lg">
        Sale
      </Badge>,
    );
    expect(screen.getByText('Sale')).toHaveClass('ui-badge--danger-solid', 'ui-badge--lg');
  });

  it('renders an optional icon alongside the label', () => {
    render(<Badge icon={<span data-testid="icon" />}>Nike</Badge>);
    expect(screen.getByTestId('icon')).toBeInTheDocument();
    expect(screen.getByText('Nike')).toBeInTheDocument();
  });

  it('merges a custom className with the generated ones', () => {
    render(<Badge className="extra">Tagged</Badge>);
    expect(screen.getByText('Tagged')).toHaveClass('ui-badge', 'extra');
  });
});
