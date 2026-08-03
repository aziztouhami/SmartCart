import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import ProductsTable from './ProductsTable';

const product = {
  id: 1,
  name: 'Widget',
  description: 'A great widget that does many wonderful things for the household.',
  category: { id: 3, name: 'Widgets' },
  brand: { id: 2, name: 'Acme', image: '/acme.png' },
  price: 19.99,
  stock: 5,
  images: ['/widget.jpg'],
};

describe('ProductsTable', () => {
  it('shows a loading row', () => {
    render(
      <ProductsTable
        products={[]}
        loading
        onEdit={jest.fn()}
        onDeleteRequest={jest.fn()}
        onAnalyze={jest.fn()}
      />,
    );
    expect(screen.getByText('Loading products…')).toBeInTheDocument();
  });

  it('shows an empty state when there are no products', () => {
    render(
      <ProductsTable
        products={[]}
        loading={false}
        onEdit={jest.fn()}
        onDeleteRequest={jest.fn()}
        onAnalyze={jest.fn()}
      />,
    );
    expect(screen.getByText('No products match your search.')).toBeInTheDocument();
  });

  it('renders a product row with image, category, brand, price and truncated description', () => {
    render(
      <ProductsTable
        products={[product]}
        loading={false}
        onEdit={jest.fn()}
        onDeleteRequest={jest.fn()}
        onAnalyze={jest.fn()}
      />,
    );

    expect(screen.getByText('Widget')).toBeInTheDocument();
    expect(screen.getByAltText('Widget')).toHaveAttribute('src', '/widget.jpg');
    expect(screen.getByText('Widgets')).toBeInTheDocument();
    expect(screen.getByText('Acme')).toBeInTheDocument();
    expect(screen.getByText('19,990')).toBeInTheDocument();
    expect(screen.getByText(product.description.slice(0, 60) + '…')).toBeInTheDocument();
  });

  it('shows a placeholder image and dash brand for a product without either', () => {
    render(
      <ProductsTable
        products={[{ ...product, images: [], brand: null }]}
        loading={false}
        onEdit={jest.fn()}
        onDeleteRequest={jest.fn()}
        onAnalyze={jest.fn()}
      />,
    );
    expect(screen.queryByAltText('Widget')).not.toBeInTheDocument();
    expect(screen.getAllByText('—')).toHaveLength(2); // thumb placeholder + brand
  });

  it('shows the correct stock badge for out-of-stock/low-stock/in-stock', () => {
    const { rerender } = render(
      <ProductsTable
        products={[{ ...product, stock: 0 }]}
        loading={false}
        onEdit={jest.fn()}
        onDeleteRequest={jest.fn()}
        onAnalyze={jest.fn()}
      />,
    );
    expect(screen.getByText('Out of Stock')).toBeInTheDocument();

    rerender(
      <ProductsTable
        products={[{ ...product, stock: 10 }]}
        loading={false}
        onEdit={jest.fn()}
        onDeleteRequest={jest.fn()}
        onAnalyze={jest.fn()}
      />,
    );
    expect(screen.getByText('Low Stock')).toBeInTheDocument();

    rerender(
      <ProductsTable
        products={[{ ...product, stock: 100 }]}
        loading={false}
        onEdit={jest.fn()}
        onDeleteRequest={jest.fn()}
        onAnalyze={jest.fn()}
      />,
    );
    expect(screen.getByText('In Stock')).toBeInTheDocument();
  });

  it('calls onEdit, onDeleteRequest and onAnalyze with the right arguments', async () => {
    const user = userEvent.setup();
    const onEdit = jest.fn();
    const onDeleteRequest = jest.fn();
    const onAnalyze = jest.fn();
    render(
      <ProductsTable
        products={[product]}
        loading={false}
        onEdit={onEdit}
        onDeleteRequest={onDeleteRequest}
        onAnalyze={onAnalyze}
      />,
    );

    await user.click(screen.getByText('Edit'));
    expect(onEdit).toHaveBeenCalledWith(product);

    await user.click(screen.getByText('Delete'));
    expect(onDeleteRequest).toHaveBeenCalledWith(1);

    await user.click(screen.getByText('Analyze'));
    expect(onAnalyze).toHaveBeenCalledWith(product);
  });
});
