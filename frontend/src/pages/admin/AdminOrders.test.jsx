import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { adminOrderApi } from '../../services/cartService';
import AdminOrders from './AdminOrders';

jest.mock('../../services/cartService', () => ({
  adminOrderApi: { getOrders: jest.fn(), getOrder: jest.fn(), updateStatus: jest.fn() },
}));

const pendingOrder = {
  id: 101,
  status: 'pending',
  createdAt: '2026-01-10T10:00:00Z',
  itemCount: 2,
  totalAmount: 59.5,
  userFirstName: 'Ada',
  userLastName: 'Lovelace',
  userEmail: 'ada@example.com',
};

describe('AdminOrders', () => {
  beforeEach(() => {
    adminOrderApi.getOrders.mockResolvedValue({ data: { data: [], total: 0 } });
  });

  it('shows a loading message then the empty state', async () => {
    render(<AdminOrders />);
    expect(screen.getByText('Loading orders…')).toBeInTheDocument();
    expect(await screen.findByText('No orders found for this filter.')).toBeInTheDocument();
  });

  it('shows an error toast when loading fails', async () => {
    adminOrderApi.getOrders.mockRejectedValue(new Error('network error'));
    render(<AdminOrders />);
    expect(await screen.findByText('Failed to load orders.')).toBeInTheDocument();
  });

  it('renders order rows with customer, status and total', async () => {
    adminOrderApi.getOrders.mockResolvedValue({ data: { data: [pendingOrder], total: 1 } });
    render(<AdminOrders />);

    expect(await screen.findByText('#101')).toBeInTheDocument();
    expect(screen.getByText('Ada Lovelace')).toBeInTheDocument();
    expect(screen.getByText('ada@example.com')).toBeInTheDocument();
    expect(screen.getByText('En attente', { selector: '.ao-badge' })).toBeInTheDocument();
    expect(screen.getByText('59,500 TND')).toBeInTheDocument();
    expect(screen.getByText('1 order total')).toBeInTheDocument();
  });

  it('shows an em-dash for guest orders with no name', async () => {
    adminOrderApi.getOrders.mockResolvedValue({
      data: {
        data: [{ ...pendingOrder, userFirstName: null, userLastName: null, userEmail: null }],
        total: 1,
      },
    });
    render(<AdminOrders />);
    await screen.findByText('#101');
    expect(screen.getByText('—', { selector: '.ao-email' })).toBeInTheDocument();
  });

  it('reloads with the status query param when a filter tab is clicked', async () => {
    const user = userEvent.setup();
    render(<AdminOrders />);
    await screen.findByText('No orders found for this filter.');
    adminOrderApi.getOrders.mockClear();

    await user.click(screen.getByText('Confirmées'));
    await waitFor(() => expect(adminOrderApi.getOrders).toHaveBeenCalledWith('confirmed', 1, 20));
  });

  it('shows a status dropdown with only the valid next transitions', async () => {
    adminOrderApi.getOrders.mockResolvedValue({ data: { data: [pendingOrder], total: 1 } });
    render(<AdminOrders />);
    await screen.findByText('#101');

    const select = screen.getByRole('combobox');
    expect(screen.getByRole('option', { name: 'Confirmée' })).toBeInTheDocument();
    expect(screen.getByRole('option', { name: 'Annulée' })).toBeInTheDocument();
    expect(select).toBeInTheDocument();
  });

  it('shows a dash instead of a dropdown for a final-state order', async () => {
    adminOrderApi.getOrders.mockResolvedValue({
      data: { data: [{ ...pendingOrder, status: 'delivered' }], total: 1 },
    });
    render(<AdminOrders />);
    await screen.findByText('#101');
    expect(screen.queryByRole('combobox')).not.toBeInTheDocument();
  });

  it('updates the order status and shows a confirmation toast', async () => {
    adminOrderApi.getOrders.mockResolvedValue({ data: { data: [pendingOrder], total: 1 } });
    adminOrderApi.updateStatus.mockResolvedValue({});
    const user = userEvent.setup();
    render(<AdminOrders />);
    await screen.findByText('#101');

    await user.selectOptions(screen.getByRole('combobox'), 'confirmed');

    await waitFor(() => expect(adminOrderApi.updateStatus).toHaveBeenCalledWith(101, 'confirmed'));
    expect(await screen.findByText('Order #101 → Confirmée')).toBeInTheDocument();
    expect(screen.getByText('Confirmée', { selector: '.ao-badge' })).toBeInTheDocument();
  });

  it('shows an error toast when the status update fails', async () => {
    adminOrderApi.getOrders.mockResolvedValue({ data: { data: [pendingOrder], total: 1 } });
    adminOrderApi.updateStatus.mockRejectedValue({
      response: { data: { error: 'Cannot skip a status.' } },
    });
    const user = userEvent.setup();
    render(<AdminOrders />);
    await screen.findByText('#101');

    await user.selectOptions(screen.getByRole('combobox'), 'confirmed');
    expect(await screen.findByText('Cannot skip a status.')).toBeInTheDocument();
  });

  it('expands an order to show its line items and shipping address, fetched once', async () => {
    adminOrderApi.getOrders.mockResolvedValue({ data: { data: [pendingOrder], total: 1 } });
    adminOrderApi.getOrder.mockResolvedValue({
      data: {
        items: [{ id: 1, productName: 'Widget', quantity: 2, unitPrice: 10, subtotal: 20 }],
        shippingAddress: {
          street: '1 Main St',
          city: 'Tunis',
          postalCode: '1000',
          country: 'Tunisia',
        },
      },
    });
    const user = userEvent.setup();
    render(<AdminOrders />);
    await screen.findByText('#101');

    await user.click(screen.getByText('▼'));
    expect(await screen.findByText('Widget')).toBeInTheDocument();
    expect(screen.getByText(/1 Main St, Tunis 1000, Tunisia/)).toBeInTheDocument();
    expect(adminOrderApi.getOrder).toHaveBeenCalledTimes(1);

    // collapse then re-expand shouldn't refetch (cached)
    await user.click(screen.getByText('▲'));
    await user.click(screen.getByText('▼'));
    expect(adminOrderApi.getOrder).toHaveBeenCalledTimes(1);
  });

  it('paginates and disables Previous on the first page', async () => {
    const user = userEvent.setup();
    adminOrderApi.getOrders.mockImplementation((status, page) =>
      Promise.resolve({ data: { data: [{ ...pendingOrder, id: 100 + page }], total: 45 } }),
    );
    render(<AdminOrders />);

    expect(await screen.findByText('#101')).toBeInTheDocument();
    expect(screen.getByText('Page 1 of 3')).toBeInTheDocument();
    expect(screen.getByText(/Previous/)).toBeDisabled();

    await user.click(screen.getByText(/Next/));
    expect(await screen.findByText('#102')).toBeInTheDocument();
    expect(adminOrderApi.getOrders).toHaveBeenCalledWith(null, 2, 20);
  });
});
