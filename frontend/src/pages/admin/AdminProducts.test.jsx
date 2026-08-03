import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {
  adminProductApi,
  adminAnalyticsApi,
  brandApi,
  productTypeApi,
} from '../../services/cartService';
import { fetchAllProducts } from '../../utils/fetchAllProducts';
import { useCategories } from '../../context/CategoryContext';
import AdminProducts from './AdminProducts';

jest.mock('../../services/cartService', () => ({
  adminProductApi: { remove: jest.fn() },
  adminAnalyticsApi: { analyzeProduct: jest.fn() },
  brandApi: { list: jest.fn() },
  productTypeApi: { list: jest.fn() },
}));

jest.mock('../../utils/fetchAllProducts', () => ({
  fetchAllProducts: jest.fn(),
}));

jest.mock('../../context/CategoryContext', () => ({ useCategories: jest.fn() }));

jest.mock(
  './AdminProducts/ProductsTable',
  () =>
    ({ products, loading, onEdit, onDeleteRequest, onAnalyze }) => (
      <div data-testid="products-table">
        <span data-testid="table-loading">{String(loading)}</span>
        {products.map(p => (
          <div key={p.id} data-testid="table-row">
            <span>{p.name}</span>
            <button onClick={() => onEdit(p)}>edit-{p.id}</button>
            <button onClick={() => onDeleteRequest(p.id)}>delete-{p.id}</button>
            <button onClick={() => onAnalyze(p)}>analyze-{p.id}</button>
          </div>
        ))}
      </div>
    ),
);

jest.mock('./AdminProducts/ProductFormModal', () => ({ mode, product, onClose, onSaved }) => (
  <div data-testid="product-form-modal">
    <span data-testid="modal-mode">{mode}</span>
    <span data-testid="modal-product">{product ? product.name : 'none'}</span>
    <button onClick={onClose}>close-modal</button>
    <button onClick={onSaved}>save-modal</button>
  </div>
));

const products = [
  { id: 1, name: 'Widget', category: { name: 'Widgets' }, brand: { name: 'Acme' }, stock: 5 },
  { id: 2, name: 'Gadget', category: { name: 'Gadgets' }, brand: { name: 'Zenith' }, stock: 0 },
];

describe('AdminProducts', () => {
  beforeEach(() => {
    fetchAllProducts.mockResolvedValue(products);
    brandApi.list.mockResolvedValue({ data: { data: [] } });
    productTypeApi.list.mockResolvedValue({ data: [] });
    useCategories.mockReturnValue({ leafCategories: [] });
  });

  it('loads products and renders them via ProductsTable', async () => {
    render(<AdminProducts />);
    expect(screen.getByTestId('table-loading')).toHaveTextContent('true');

    expect(await screen.findByText('Widget')).toBeInTheDocument();
    expect(screen.getByText('Gadget')).toBeInTheDocument();
  });

  it('shows a toast error when loading products fails', async () => {
    fetchAllProducts.mockRejectedValue(new Error('network error'));
    render(<AdminProducts />);
    expect(await screen.findByText('Failed to load products.')).toBeInTheDocument();
  });

  it('shows total/low-stock/out-of-stock counts', async () => {
    render(<AdminProducts />);
    await screen.findByText('Widget');
    expect(screen.getByText(/2 total/)).toBeInTheDocument();
    expect(screen.getByText(/1 low stock/)).toBeInTheDocument();
    expect(screen.getByText(/1 out of stock/)).toBeInTheDocument();
  });

  it('filters the table by the search box across name, category and brand', async () => {
    const user = userEvent.setup();
    render(<AdminProducts />);
    await screen.findByText('Widget');

    await user.type(
      screen.getByPlaceholderText('Search products, categories or brands...'),
      'acme',
    );
    expect(screen.getByText('Widget')).toBeInTheDocument();
    expect(screen.queryByText('Gadget')).not.toBeInTheDocument();
  });

  it('filters by stock status tabs', async () => {
    const user = userEvent.setup();
    render(<AdminProducts />);
    await screen.findByText('Widget');

    await user.click(screen.getByText('Out of Stock'));
    expect(screen.queryByText('Widget')).not.toBeInTheDocument();
    expect(screen.getByText('Gadget')).toBeInTheDocument();

    await user.click(screen.getByText('Low Stock'));
    expect(screen.getByText('Widget')).toBeInTheDocument();
    expect(screen.queryByText('Gadget')).not.toBeInTheDocument();
  });

  it('opens the add-product modal empty', async () => {
    const user = userEvent.setup();
    render(<AdminProducts />);
    await screen.findByText('Widget');

    await user.click(screen.getByText('Add Product'));
    expect(screen.getByTestId('modal-mode')).toHaveTextContent('add');
    expect(screen.getByTestId('modal-product')).toHaveTextContent('none');
  });

  it('opens the edit modal with the specific clicked product, not stale data', async () => {
    const user = userEvent.setup();
    render(<AdminProducts />);
    await screen.findByText('Widget');

    await user.click(screen.getByText('edit-2'));
    expect(screen.getByTestId('modal-mode')).toHaveTextContent('edit');
    expect(screen.getByTestId('modal-product')).toHaveTextContent('Gadget');
  });

  it('closes the modal and reloads the list when a product is saved', async () => {
    const user = userEvent.setup();
    render(<AdminProducts />);
    await screen.findByText('Widget');
    fetchAllProducts.mockClear();

    await user.click(screen.getByText('Add Product'));
    await user.click(screen.getByText('save-modal'));

    expect(screen.queryByTestId('product-form-modal')).not.toBeInTheDocument();
    await waitFor(() => expect(fetchAllProducts).toHaveBeenCalled());
  });

  it('closes the modal without reloading when cancelled', async () => {
    const user = userEvent.setup();
    render(<AdminProducts />);
    await screen.findByText('Widget');
    fetchAllProducts.mockClear();

    await user.click(screen.getByText('Add Product'));
    await user.click(screen.getByText('close-modal'));

    expect(screen.queryByTestId('product-form-modal')).not.toBeInTheDocument();
    expect(fetchAllProducts).not.toHaveBeenCalled();
  });

  it('deletes a product after confirming', async () => {
    const user = userEvent.setup();
    adminProductApi.remove.mockResolvedValue({});
    render(<AdminProducts />);
    await screen.findByText('Widget');

    await user.click(screen.getByText('delete-1'));
    expect(screen.getByText('Delete Product?')).toBeInTheDocument();

    await user.click(screen.getByText('Delete'));
    await waitFor(() => expect(adminProductApi.remove).toHaveBeenCalledWith(1));
    expect(await screen.findByText('Product deleted.')).toBeInTheDocument();
    expect(screen.queryByText('Widget')).not.toBeInTheDocument();
  });

  it('cancels a delete request without calling the API', async () => {
    const user = userEvent.setup();
    render(<AdminProducts />);
    await screen.findByText('Widget');

    await user.click(screen.getByText('delete-1'));
    await user.click(screen.getByText('Cancel'));
    expect(adminProductApi.remove).not.toHaveBeenCalled();
    expect(screen.getByText('Widget')).toBeInTheDocument();
  });

  it('runs an analysis for a product', async () => {
    const user = userEvent.setup();
    adminAnalyticsApi.analyzeProduct.mockResolvedValue({
      data: { healthScore: 90, anomalies: [] },
    });
    render(<AdminProducts />);
    await screen.findByText('Widget');

    await user.click(screen.getByText('analyze-1'));
    expect(screen.getByText('AI Analysis — Widget')).toBeInTheDocument();
    await waitFor(() => expect(adminAnalyticsApi.analyzeProduct).toHaveBeenCalledWith(1));
  });
});
