import React from 'react';
import { render } from '@testing-library/react';
import HeartIcon from './HeartIcon';

describe('HeartIcon', () => {
  it('renders unfilled by default at size 16', () => {
    const { container } = render(<HeartIcon />);
    const svg = container.querySelector('svg');
    expect(svg).toHaveAttribute('fill', 'none');
    expect(svg).toHaveAttribute('width', '16');
    expect(svg).toHaveAttribute('height', '16');
  });

  it('fills with currentColor when filled', () => {
    const { container } = render(<HeartIcon filled />);
    expect(container.querySelector('svg')).toHaveAttribute('fill', 'currentColor');
  });

  it('applies a custom size and stroke', () => {
    const { container } = render(<HeartIcon size={40} stroke="#ff0000" strokeWidth={3} />);
    const svg = container.querySelector('svg');
    expect(svg).toHaveAttribute('width', '40');
    expect(svg).toHaveAttribute('height', '40');
    expect(svg).toHaveAttribute('stroke', '#ff0000');
    expect(svg).toHaveAttribute('stroke-width', '3');
  });

  it('forwards arbitrary props', () => {
    const { container } = render(<HeartIcon data-testid="heart" />);
    expect(container.querySelector('[data-testid="heart"]')).toBeInTheDocument();
  });
});
