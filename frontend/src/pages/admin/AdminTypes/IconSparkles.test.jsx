import React from 'react';
import { render } from '@testing-library/react';
import IconSparkles from './IconSparkles';

describe('IconSparkles', () => {
  it('renders an svg icon', () => {
    const { container } = render(<IconSparkles />);
    expect(container.querySelector('svg')).toBeInTheDocument();
  });
});
