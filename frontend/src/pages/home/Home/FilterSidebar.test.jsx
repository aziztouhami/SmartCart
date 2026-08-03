import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import '../../../i18n';
import FilterSidebar from './FilterSidebar';
import { EMPTY_FACETS, EMPTY_FILTERS } from './constants';

describe('FilterSidebar', () => {
  const baseFacets = {
    brands: [{ id: 1, name: 'Apple', count: 5 }],
    productTypes: [
      { id: 10, name: 'Phones', count: 3 },
      { id: 11, name: 'Laptops', count: 2 },
    ],
    priceRange: { min: 10, max: 999 },
    attributes: [
      {
        slug: 'color',
        name: 'Color',
        unit: '',
        productTypeIds: [10],
        values: [{ value: 'red', count: 2 }],
        valuesByType: { 10: [{ value: 'red', count: 2 }] },
      },
    ],
  };

  it('shows a loading message instead of the filter fields while facetsLoading', () => {
    render(
      <FilterSidebar
        facets={EMPTY_FACETS}
        facetsLoading
        filters={EMPTY_FILTERS}
        onChange={jest.fn()}
        sortValue="createdAt-desc"
        onSortChange={jest.fn()}
      />,
    );
    expect(screen.getByText('Loading filters…')).toBeInTheDocument();
    expect(screen.queryByText('Brand')).not.toBeInTheDocument();
  });

  it('renders brand/type/price/stock fields once facets are loaded', () => {
    render(
      <FilterSidebar
        facets={baseFacets}
        facetsLoading={false}
        filters={EMPTY_FILTERS}
        onChange={jest.fn()}
        sortValue="createdAt-desc"
        onSortChange={jest.fn()}
      />,
    );
    expect(screen.getByText('Brand')).toBeInTheDocument();
    expect(screen.getByText('Type')).toBeInTheDocument();
    expect(screen.getByText('Price (TND)')).toBeInTheDocument();
    expect(screen.getByText('In stock only')).toBeInTheDocument();
  });

  it('does not show a reset button when no filters are active', () => {
    render(
      <FilterSidebar
        facets={baseFacets}
        facetsLoading={false}
        filters={EMPTY_FILTERS}
        onChange={jest.fn()}
        sortValue="createdAt-desc"
        onSortChange={jest.fn()}
      />,
    );
    expect(screen.queryByText(/Reset/)).not.toBeInTheDocument();
  });

  it('shows a reset button with the active count and clears filters + sort on click', async () => {
    const user = userEvent.setup();
    const onChange = jest.fn();
    const onSortChange = jest.fn();
    render(
      <FilterSidebar
        facets={baseFacets}
        facetsLoading={false}
        filters={{ ...EMPTY_FILTERS, brand: '1', inStock: true }}
        onChange={onChange}
        sortValue="price-asc"
        onSortChange={onSortChange}
      />,
    );
    const resetButton = screen.getByText('Reset (2)');
    await user.click(resetButton);
    expect(onChange).toHaveBeenCalledWith(EMPTY_FILTERS);
    expect(onSortChange).toHaveBeenCalledWith('createdAt-desc');
  });

  it('only shows attributes relevant to the selected type', () => {
    const { rerender } = render(
      <FilterSidebar
        facets={baseFacets}
        facetsLoading={false}
        filters={EMPTY_FILTERS}
        onChange={jest.fn()}
        sortValue="createdAt-desc"
        onSortChange={jest.fn()}
      />,
    );
    expect(screen.getByText('Color')).toBeInTheDocument();

    rerender(
      <FilterSidebar
        facets={baseFacets}
        facetsLoading={false}
        filters={{ ...EMPTY_FILTERS, type: '11' }}
        onChange={jest.fn()}
        sortValue="createdAt-desc"
        onSortChange={jest.fn()}
      />,
    );
    expect(screen.queryByText('Color')).not.toBeInTheDocument();
  });

  it('calls onChange with the attribute slug/value when an attribute select changes', async () => {
    const user = userEvent.setup();
    const onChange = jest.fn();
    render(
      <FilterSidebar
        facets={baseFacets}
        facetsLoading={false}
        filters={EMPTY_FILTERS}
        onChange={onChange}
        sortValue="createdAt-desc"
        onSortChange={jest.fn()}
      />,
    );
    await user.selectOptions(screen.getByDisplayValue('Any'), 'red');
    expect(onChange).toHaveBeenCalledWith({ ...EMPTY_FILTERS, attrs: { color: 'red' } });
  });

  it('calls onSortChange when the sort dropdown changes', async () => {
    const user = userEvent.setup();
    const onSortChange = jest.fn();
    render(
      <FilterSidebar
        facets={baseFacets}
        facetsLoading={false}
        filters={EMPTY_FILTERS}
        onChange={jest.fn()}
        sortValue="createdAt-desc"
        onSortChange={onSortChange}
      />,
    );
    await user.selectOptions(screen.getByDisplayValue('Newest first'), 'price-asc');
    expect(onSortChange).toHaveBeenCalledWith('price-asc');
  });
});
