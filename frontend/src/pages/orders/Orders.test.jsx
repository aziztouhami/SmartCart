import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import '../../i18n';
import { orderApi, reviewApi } from '../../services/cartService';
import Orders from './Orders';

jest.mock('../../services/cartService', () => ({
  orderApi: { getOrders: jest.fn(), getOrder: jest.fn(), cancel: jest.fn() },
  reviewApi: { myReviews: jest.fn(), create: jest.fn() },
}));

jest.mock('../../components/Navbar', () => () => <div data-testid="navbar" />);

function renderOrders() {
  return render(
    <MemoryRouter future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
      <Routes>
        <Route path="/" element={<Orders />} />
      </Routes>
    </MemoryRouter>,
  );
}

const pendingOrder = {
  id: 101,
  status: 'pending',
  createdAt: '2026-01-10T00:00:00Z',
  itemCount: 2,
  totalAmount: 59.5,
};

const deliveredOrder = {
  id: 102,
  status: 'delivered',
  createdAt: '2026-01-05T00:00:00Z',
  itemCount: 1,
  totalAmount: 19.99,
};

describe('Orders page', () => {
  beforeEach(() => {
    reviewApi.myReviews.mockResolvedValue({ data: [] });
    orderApi.getOrders.mockResolvedValue({ data: { data: [], total: 0 } });
  });

  it('shows a loading state then the empty state when there are no orders', async () => {
    renderOrders();
    expect(await screen.findByText('No orders yet')).toBeInTheDocument();
  });

  it('shows an error message when loading orders fails', async () => {
    orderApi.getOrders.mockRejectedValue(new Error('network error'));
    renderOrders();
    expect(await screen.findByText('Failed to load orders.')).toBeInTheDocument();
  });

  it('renders order cards with id, date, status and total', async () => {
    orderApi.getOrders.mockResolvedValue({ data: { data: [pendingOrder], total: 1 } });
    renderOrders();

    expect(await screen.findByText('Order #101')).toBeInTheDocument();
    expect(screen.getByText('Pending')).toBeInTheDocument();
    expect(screen.getByText('59,500 TND')).toBeInTheDocument();
    expect(screen.getByText('2 items')).toBeInTheDocument();
  });

  it('shows a cancel button only for pending orders', async () => {
    orderApi.getOrders.mockResolvedValue({
      data: { data: [pendingOrder, deliveredOrder], total: 2 },
    });
    renderOrders();
    await screen.findByText('Order #101');
    expect(screen.getAllByText('Cancel Order')).toHaveLength(1);
  });

  it('shows a cancelled notice instead of the timeline for a cancelled order', async () => {
    orderApi.getOrders.mockResolvedValue({
      data: { data: [{ ...pendingOrder, status: 'cancelled' }], total: 1 },
    });
    renderOrders();
    expect(await screen.findByText('This order was cancelled.')).toBeInTheDocument();
  });

  it('opens the cancel confirmation modal, and closing it keeps the order pending', async () => {
    const user = userEvent.setup();
    orderApi.getOrders.mockResolvedValue({ data: { data: [pendingOrder], total: 1 } });
    renderOrders();
    await screen.findByText('Order #101');

    await user.click(screen.getByText('Cancel Order'));
    expect(screen.getByText('Cancel order #101?')).toBeInTheDocument();

    await user.click(screen.getByText('Keep Order'));
    expect(screen.queryByText('Cancel order #101?')).not.toBeInTheDocument();
    expect(orderApi.cancel).not.toHaveBeenCalled();
  });

  it('confirms cancellation, calls the API and updates the badge to Cancelled', async () => {
    const user = userEvent.setup();
    orderApi.getOrders.mockResolvedValue({ data: { data: [pendingOrder], total: 1 } });
    orderApi.cancel.mockResolvedValue({});
    renderOrders();
    await screen.findByText('Order #101');

    await user.click(screen.getByText('Cancel Order'));
    const confirmButtons = screen.getAllByText('Cancel Order');
    await user.click(confirmButtons[confirmButtons.length - 1]);

    await waitFor(() => expect(orderApi.cancel).toHaveBeenCalledWith(101));
    expect(await screen.findByText('Cancelled')).toBeInTheDocument();
  });

  it('shows an error in the cancel modal when cancellation fails', async () => {
    const user = userEvent.setup();
    orderApi.getOrders.mockResolvedValue({ data: { data: [pendingOrder], total: 1 } });
    orderApi.cancel.mockRejectedValue({ response: { data: { error: 'Too late to cancel.' } } });
    renderOrders();
    await screen.findByText('Order #101');

    await user.click(screen.getByText('Cancel Order'));
    const confirmButtons = screen.getAllByText('Cancel Order');
    await user.click(confirmButtons[confirmButtons.length - 1]);

    expect(await screen.findByText('Too late to cancel.')).toBeInTheDocument();
  });

  it('expands an order to show its detail panel with items', async () => {
    const user = userEvent.setup();
    orderApi.getOrders.mockResolvedValue({ data: { data: [deliveredOrder], total: 1 } });
    orderApi.getOrder.mockResolvedValue({
      data: {
        items: [{ id: 1, productId: 5, productName: 'Widget', quantity: 2, subtotal: 19.99 }],
        shippingAddress: {
          street: '1 Main St',
          city: 'Tunis',
          postalCode: '1000',
          country: 'Tunisia',
        },
      },
    });
    renderOrders();
    await screen.findByText('Order #102');

    await user.click(screen.getByText('View Details ▼'));

    expect(await screen.findByText('Widget')).toBeInTheDocument();
    expect(screen.getByText('× 2')).toBeInTheDocument();
    expect(screen.getByText(/1 Main St, Tunis 1000, Tunisia/)).toBeInTheDocument();
    expect(orderApi.getOrder).toHaveBeenCalledWith(102);
  });

  it('collapses the detail panel when clicked again', async () => {
    const user = userEvent.setup();
    orderApi.getOrders.mockResolvedValue({ data: { data: [deliveredOrder], total: 1 } });
    orderApi.getOrder.mockResolvedValue({
      data: {
        items: [{ id: 1, productId: 5, productName: 'Widget', quantity: 1, subtotal: 19.99 }],
      },
    });
    renderOrders();
    await screen.findByText('Order #102');

    await user.click(screen.getByText('View Details ▼'));
    await screen.findByText('Widget');
    await user.click(screen.getByText('Hide Details ▲'));

    expect(screen.queryByText('Widget')).not.toBeInTheDocument();
  });

  it('shows a Rate button for unreviewed items on a delivered order, and a Reviewed badge for reviewed ones', async () => {
    const user = userEvent.setup();
    reviewApi.myReviews.mockResolvedValue({ data: [{ productId: 5 }] });
    orderApi.getOrders.mockResolvedValue({ data: { data: [deliveredOrder], total: 1 } });
    orderApi.getOrder.mockResolvedValue({
      data: {
        items: [
          { id: 1, productId: 5, productName: 'Widget', quantity: 1, subtotal: 19.99 },
          { id: 2, productId: 6, productName: 'Gadget', quantity: 1, subtotal: 9.99 },
        ],
      },
    });
    renderOrders();
    await screen.findByText('Order #102');
    await user.click(screen.getByText('View Details ▼'));

    expect(await screen.findByText('✓ Reviewed')).toBeInTheDocument();
    expect(screen.getByText('Rate')).toBeInTheDocument();
  });

  it('opens the review modal, submits a review and shows the item as reviewed', async () => {
    const user = userEvent.setup();
    orderApi.getOrders.mockResolvedValue({ data: { data: [deliveredOrder], total: 1 } });
    orderApi.getOrder.mockResolvedValue({
      data: {
        items: [{ id: 1, productId: 5, productName: 'Widget', quantity: 1, subtotal: 19.99 }],
      },
    });
    reviewApi.create.mockResolvedValue({});
    renderOrders();
    await screen.findByText('Order #102');
    await user.click(screen.getByText('View Details ▼'));
    await user.click(await screen.findByText('Rate'));

    expect(screen.getByText('Rate this product')).toBeInTheDocument();
    expect(screen.getByText('Widget', { selector: '.ord-modal-product' })).toBeInTheDocument();

    reviewApi.myReviews.mockResolvedValue({ data: [{ productId: 5 }] });
    await user.click(screen.getByText('Submit Review'));

    await waitFor(() =>
      expect(reviewApi.create).toHaveBeenCalledWith(5, { rating: 80, comment: '' }),
    );
    await waitFor(() => expect(screen.queryByText('Rate this product')).not.toBeInTheDocument());
  });

  it('shows an error in the review modal when submission fails', async () => {
    const user = userEvent.setup();
    orderApi.getOrders.mockResolvedValue({ data: { data: [deliveredOrder], total: 1 } });
    orderApi.getOrder.mockResolvedValue({
      data: {
        items: [{ id: 1, productId: 5, productName: 'Widget', quantity: 1, subtotal: 19.99 }],
      },
    });
    reviewApi.create.mockRejectedValue({ response: { data: { error: 'Already reviewed.' } } });
    renderOrders();
    await screen.findByText('Order #102');
    await user.click(screen.getByText('View Details ▼'));
    await user.click(await screen.findByText('Rate'));
    await user.click(screen.getByText('Submit Review'));

    expect(await screen.findByText('Already reviewed.')).toBeInTheDocument();
  });

  it('closes the review modal via cancel without submitting', async () => {
    const user = userEvent.setup();
    orderApi.getOrders.mockResolvedValue({ data: { data: [deliveredOrder], total: 1 } });
    orderApi.getOrder.mockResolvedValue({
      data: {
        items: [{ id: 1, productId: 5, productName: 'Widget', quantity: 1, subtotal: 19.99 }],
      },
    });
    renderOrders();
    await screen.findByText('Order #102');
    await user.click(screen.getByText('View Details ▼'));
    await user.click(await screen.findByText('Rate'));

    await user.click(screen.getByText('Cancel'));
    expect(screen.queryByText('Rate this product')).not.toBeInTheDocument();
    expect(reviewApi.create).not.toHaveBeenCalled();
  });

  it('paginates between pages of orders', async () => {
    const user = userEvent.setup();
    orderApi.getOrders.mockImplementation(page =>
      Promise.resolve({
        data: { data: [{ ...pendingOrder, id: 100 + page }], total: 25 },
      }),
    );
    renderOrders();

    expect(await screen.findByText('Order #101')).toBeInTheDocument();
    expect(screen.getByText('Page 1 of 3')).toBeInTheDocument();
    expect(screen.getByText(/Previous/)).toBeDisabled();

    await user.click(screen.getByText(/Next/));
    expect(await screen.findByText('Order #102')).toBeInTheDocument();
    expect(orderApi.getOrders).toHaveBeenCalledWith(2, 10);
  });
});
