import React from 'react';
import { render } from '@testing-library/react';
import Skeleton from './Skeleton';

describe('Skeleton', () => {
  it('renders with the given width/height and a default 6px radius', () => {
    const { container } = render(<Skeleton width={100} height={20} />);
    const el = container.querySelector('.ui-skeleton');
    expect(el).toHaveStyle({ width: '100px', height: '20px', borderRadius: '6px' });
  });

  it('applies a custom radius', () => {
    const { container } = render(<Skeleton radius={50} />);
    expect(container.querySelector('.ui-skeleton')).toHaveStyle({ borderRadius: '50px' });
  });

  it('merges an additional className', () => {
    const { container } = render(<Skeleton className="custom" />);
    expect(container.querySelector('.ui-skeleton')).toHaveClass('custom');
  });

  it('lets the style prop override the computed style', () => {
    const { container } = render(<Skeleton width={10} style={{ width: 999 }} />);
    expect(container.querySelector('.ui-skeleton')).toHaveStyle({ width: '999px' });
  });

  it('forwards arbitrary props', () => {
    const { container } = render(<Skeleton data-testid="sk" />);
    expect(container.querySelector('[data-testid="sk"]')).toBeInTheDocument();
  });
});
