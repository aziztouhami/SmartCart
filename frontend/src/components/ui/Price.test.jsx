import React from 'react';
import { render, screen } from '@testing-library/react';
import Price from './Price';
import { formatPrice } from '../../utils/format';

describe('Price', () => {
  it('renders a plain price with the default TND currency', () => {
    render(<Price value={19.99} />);
    expect(screen.getByText(formatPrice(19.99))).toBeInTheDocument();
    expect(screen.getByText('TND')).toBeInTheDocument();
    expect(screen.queryByText(/-\d+%/)).not.toBeInTheDocument();
  });

  it('renders a custom currency', () => {
    render(<Price value={10} currency="USD" />);
    expect(screen.getByText('USD')).toBeInTheDocument();
  });

  it('does not show a discount when oldValue is not higher than value', () => {
    render(<Price value={10} oldValue={10} />);
    const priceEl = screen.getByText(formatPrice(10));
    expect(priceEl).not.toHaveClass('ui-price--discounted');
  });

  it('shows the struck-through old price and a computed percentage when discounted', () => {
    render(<Price value={80} oldValue={100} />);
    expect(screen.getByText(formatPrice(100))).toBeInTheDocument();
    expect(screen.getByText('-20%')).toBeInTheDocument();
    expect(screen.getByText(formatPrice(80))).toHaveClass('ui-price--discounted');
  });

  it('prefers an explicit percentage over the computed one', () => {
    render(<Price value={80} oldValue={100} percentage={25.6} />);
    expect(screen.getByText('-26%')).toBeInTheDocument();
  });

  it('applies an additional className to the wrapper', () => {
    const { container } = render(<Price value={10} className="custom-class" />);
    expect(container.querySelector('.ui-price-row')).toHaveClass('custom-class');
  });
});
