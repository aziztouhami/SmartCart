import React from 'react';
import { render } from '@testing-library/react';
import { IconPlus, IconEdit, IconTrash, IconSearch, IconAnalyze } from './AdminIcons';

describe('AdminIcons', () => {
  it.each([
    ['IconPlus', IconPlus],
    ['IconEdit', IconEdit],
    ['IconTrash', IconTrash],
    ['IconSearch', IconSearch],
    ['IconAnalyze', IconAnalyze],
  ])('renders %s as an svg', (name, Icon) => {
    const { container } = render(<Icon />);
    expect(container.querySelector('svg')).toBeInTheDocument();
  });
});
