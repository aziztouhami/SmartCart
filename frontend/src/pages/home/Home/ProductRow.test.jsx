import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { Wand2 } from 'lucide-react';
import '../../../i18n';
import ProductRow from './ProductRow';

jest.mock('../../../components/ProductCard', () => ({
  __esModule: true,
  default: ({ product }) => <div data-testid="product-card">{product.name}</div>,
  SkeletonCard: () => <div data-testid="skeleton-card" />,
}));

describe('ProductRow', () => {
  const products = [
    { id: 1, name: 'Product One' },
    { id: 2, name: 'Product Two' },
  ];

  it('renders nothing when not loading and there are no products', () => {
    const { container } = render(
      <ProductRow icon={Wand2} title="Recommended" products={[]} loading={false} />,
    );
    expect(container).toBeEmptyDOMElement();
  });

  it('renders skeleton cards while loading, even with no products yet', () => {
    render(<ProductRow icon={Wand2} title="Recommended" products={[]} loading />);
    expect(screen.getAllByTestId('skeleton-card')).toHaveLength(6);
  });

  it('renders the given skeletonCount while loading', () => {
    render(<ProductRow icon={Wand2} title="Recommended" products={[]} loading skeletonCount={3} />);
    expect(screen.getAllByTestId('skeleton-card')).toHaveLength(3);
  });

  it('renders product cards once loaded', () => {
    render(<ProductRow icon={Wand2} title="Recommended" products={products} loading={false} />);
    expect(screen.getAllByTestId('product-card')).toHaveLength(2);
    expect(screen.getByText('Product One')).toBeInTheDocument();
    expect(screen.getByText('Product Two')).toBeInTheDocument();
  });

  it('renders the title and subtitle', () => {
    render(
      <ProductRow
        icon={Wand2}
        title="Recommended for you"
        subtitle="Based on your activity"
        products={products}
        loading={false}
      />,
    );
    expect(screen.getByText('Recommended for you')).toBeInTheDocument();
    expect(screen.getByText('Based on your activity')).toBeInTheDocument();
  });

  it('shows a "View All" button only when viewAllTo is provided, and calls onViewAll when clicked', async () => {
    const user = userEvent.setup();
    const onViewAll = jest.fn();
    render(
      <ProductRow
        icon={Wand2}
        title="Promotions"
        products={products}
        loading={false}
        viewAllTo="/promotions"
        onViewAll={onViewAll}
      />,
    );
    const button = screen.getByText('View All');
    await user.click(button);
    expect(onViewAll).toHaveBeenCalled();
  });

  it('does not show a "View All" button when viewAllTo is absent', () => {
    render(<ProductRow icon={Wand2} title="Recommended" products={products} loading={false} />);
    expect(screen.queryByText('View All')).not.toBeInTheDocument();
  });
});
