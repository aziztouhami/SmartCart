import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { adminPromotionApi, brandApi } from '../../services/cartService';
import { fetchAllProducts } from '../../utils/fetchAllProducts';
import AdminPromotions from './AdminPromotions';

jest.mock('../../services/cartService', () => ({
  adminPromotionApi: { list: jest.fn(), create: jest.fn(), end: jest.fn(), remove: jest.fn() },
  brandApi: { list: jest.fn() },
}));

jest.mock('../../utils/fetchAllProducts', () => ({
  fetchAllProducts: jest.fn(),
}));

// The modal's <label> elements aren't programmatically associated with their
// <input>/<select> (no htmlFor/id, no nesting), so getByLabelText can't find
// them — grab the sibling control from the shared .adm-field wrapper instead.
function fieldControl(labelText) {
  return screen.getByText(labelText).closest('.adm-field').querySelector('input, select');
}

const product = { id: 5, name: 'Widget', price: 100 };

const productPromo = {
  id: 1,
  type: 'product',
  status: 'active',
  discountType: 'percentage',
  percentage: 20,
  fixedPrice: null,
  product: { id: 5, name: 'Widget', price: 100 },
  startDate: '2026-01-01T00:00:00Z',
  endDate: null,
};

describe('AdminPromotions', () => {
  beforeEach(() => {
    adminPromotionApi.list.mockResolvedValue({ data: { data: [] } });
    fetchAllProducts.mockResolvedValue([product]);
    brandApi.list.mockResolvedValue({ data: { data: [] } });
  });

  it('shows a loading row then the empty state', async () => {
    render(<AdminPromotions />);
    expect(screen.getByText('Loading promotions…')).toBeInTheDocument();
    expect(
      await screen.findByText('No promotions yet. Click "Add Promotion" to create one.'),
    ).toBeInTheDocument();
  });

  it('renders a product promotion row with target, discount and old/new prices', async () => {
    adminPromotionApi.list.mockResolvedValue({ data: { data: [productPromo] } });
    render(<AdminPromotions />);

    expect(await screen.findByText('Widget')).toBeInTheDocument();
    expect(screen.getByText('20% off')).toBeInTheDocument();
    expect(screen.getByText('Active')).toBeInTheDocument();
    expect(screen.getByText('No end date')).toBeInTheDocument();
    expect(screen.getByText('1 total · 1 active')).toBeInTheDocument();
  });

  it('shows the "varies per product" note for brand and store-wide promotions', async () => {
    adminPromotionApi.list.mockResolvedValue({
      data: {
        data: [
          { ...productPromo, id: 2, type: 'brand', brand: { name: 'Acme' } },
          { ...productPromo, id: 3, type: 'all' },
        ],
      },
    });
    render(<AdminPromotions />);

    expect(await screen.findByText('Acme (all products)')).toBeInTheDocument();
    expect(screen.getByText('All Products (store-wide)')).toBeInTheDocument();
    expect(screen.getAllByText('varies per product')).toHaveLength(2);
  });

  it('opens the add-promotion modal and validates a missing product', async () => {
    const user = userEvent.setup();
    render(<AdminPromotions />);
    await screen.findByText('No promotions yet. Click "Add Promotion" to create one.');

    await user.click(screen.getByText('Add Promotion'));
    expect(screen.getByText('Apply Promotion To *')).toBeInTheDocument();

    await user.click(screen.getByText('Create Promotion'));
    expect(screen.getByText('Select a product.')).toBeInTheDocument();
    expect(adminPromotionApi.create).not.toHaveBeenCalled();
  });

  it('validates the percentage range', async () => {
    const user = userEvent.setup();
    render(<AdminPromotions />);
    await screen.findByText('No promotions yet. Click "Add Promotion" to create one.');

    await user.click(screen.getByText('Add Promotion'));
    await user.selectOptions(fieldControl('Product *'), '5');
    await user.type(fieldControl('Percentage (%) *'), '150');
    await user.click(screen.getByText('Create Promotion'));

    expect(screen.getByText('Enter a percentage between 1 and 99.')).toBeInTheDocument();
  });

  it('shows a live price preview once product and percentage are set', async () => {
    const user = userEvent.setup();
    render(<AdminPromotions />);
    await screen.findByText('No promotions yet. Click "Add Promotion" to create one.');

    await user.click(screen.getByText('Add Promotion'));
    await user.selectOptions(fieldControl('Product *'), '5');
    await user.type(fieldControl('Percentage (%) *'), '20');

    expect(await screen.findByText('80,000 TND')).toBeInTheDocument();
  });

  it('validates a fixed price must be lower than the current price', async () => {
    const user = userEvent.setup();
    render(<AdminPromotions />);
    await screen.findByText('No promotions yet. Click "Add Promotion" to create one.');

    await user.click(screen.getByText('Add Promotion'));
    await user.selectOptions(fieldControl('Product *'), '5');
    await user.selectOptions(fieldControl('Discount Type *'), 'fixed');
    await user.type(fieldControl('New Price (TND) *'), '150');
    await user.click(screen.getByText('Create Promotion'));

    expect(screen.getByText('New price must be lower than the current price.')).toBeInTheDocument();
  });

  it('creates a product promotion and reloads the list', async () => {
    const user = userEvent.setup();
    adminPromotionApi.create.mockResolvedValue({});
    render(<AdminPromotions />);
    await screen.findByText('No promotions yet. Click "Add Promotion" to create one.');

    await user.click(screen.getByText('Add Promotion'));
    await user.selectOptions(fieldControl('Product *'), '5');
    await user.type(fieldControl('Percentage (%) *'), '20');
    await user.click(screen.getByText('Create Promotion'));

    await waitFor(() =>
      expect(adminPromotionApi.create).toHaveBeenCalledWith(
        expect.objectContaining({
          type: 'product',
          productId: 5,
          discountType: 'percentage',
          percentage: 20,
        }),
      ),
    );
    expect(await screen.findByText('Promotion created successfully.')).toBeInTheDocument();
  });

  it('restricts brand promotions to percentage-only discounts', async () => {
    brandApi.list.mockResolvedValue({ data: { data: [{ id: 9, name: 'Acme' }] } });
    const user = userEvent.setup();
    render(<AdminPromotions />);
    await screen.findByText('No promotions yet. Click "Add Promotion" to create one.');

    await user.click(screen.getByText('Add Promotion'));
    await user.selectOptions(fieldControl('Apply Promotion To *'), 'brand');

    expect(fieldControl('Brand *')).toBeInTheDocument();
    expect(fieldControl('Discount Type *')).toBeDisabled();
    expect(
      screen.getByText('Brand and store-wide promotions can only use a percentage.'),
    ).toBeInTheDocument();
  });

  it('shows a save error toast when creation fails', async () => {
    const user = userEvent.setup();
    adminPromotionApi.create.mockRejectedValue({
      response: { data: { error: 'Product already on promotion.' } },
    });
    render(<AdminPromotions />);
    await screen.findByText('No promotions yet. Click "Add Promotion" to create one.');

    await user.click(screen.getByText('Add Promotion'));
    await user.selectOptions(fieldControl('Product *'), '5');
    await user.type(fieldControl('Percentage (%) *'), '20');
    await user.click(screen.getByText('Create Promotion'));

    expect(await screen.findByText('Product already on promotion.')).toBeInTheDocument();
  });

  it('ends a promotion after confirming', async () => {
    adminPromotionApi.list.mockResolvedValue({ data: { data: [productPromo] } });
    adminPromotionApi.end.mockResolvedValue({});
    const user = userEvent.setup();
    render(<AdminPromotions />);
    await screen.findByText('Widget');

    await user.click(screen.getByText('End Now'));
    expect(screen.getByText('End Promotion Now?')).toBeInTheDocument();

    await user.click(screen.getByText('End Now', { selector: '.adm-btn-save' }));
    await waitFor(() => expect(adminPromotionApi.end).toHaveBeenCalledWith(1));
    expect(await screen.findByText('Promotion ended.')).toBeInTheDocument();
  });

  it('does not show "End Now" for an already-ended promotion', async () => {
    adminPromotionApi.list.mockResolvedValue({
      data: { data: [{ ...productPromo, status: 'ended' }] },
    });
    render(<AdminPromotions />);
    await screen.findByText('Widget');
    expect(screen.queryByText('End Now')).not.toBeInTheDocument();
    expect(screen.getByText('Ended')).toBeInTheDocument();
  });

  it('deletes a promotion after confirming', async () => {
    adminPromotionApi.list.mockResolvedValue({ data: { data: [productPromo] } });
    adminPromotionApi.remove.mockResolvedValue({});
    const user = userEvent.setup();
    render(<AdminPromotions />);
    await screen.findByText('Widget');

    await user.click(screen.getByText('Delete'));
    await user.click(screen.getByText('Delete', { selector: '.adm-btn-save' }));

    await waitFor(() => expect(adminPromotionApi.remove).toHaveBeenCalledWith(1));
    expect(await screen.findByText('Promotion deleted.')).toBeInTheDocument();
    expect(screen.queryByText('Widget')).not.toBeInTheDocument();
  });
});
