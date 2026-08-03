import React from 'react';
import { render, screen } from '@testing-library/react';
import { dashboardApi } from '../../services/cartService';
import AdminDashboard from './AdminDashboard';

jest.mock('../../services/cartService', () => ({
  dashboardApi: { get: jest.fn() },
}));

const baseData = {
  products: { total: 42, lowStockCount: 0, outOfStockCount: 0 },
  categories: { total: 6, top: [] },
  revenue: { total: 1234.5, monthly: [] },
  topSelling: [],
};

describe('AdminDashboard', () => {
  it('shows a loading message initially', () => {
    dashboardApi.get.mockReturnValue(new Promise(() => {}));
    render(<AdminDashboard />);
    expect(screen.getByText('Loading dashboard…')).toBeInTheDocument();
  });

  it('shows an error message when the request fails', async () => {
    dashboardApi.get.mockRejectedValue(new Error('network error'));
    render(<AdminDashboard />);
    expect(await screen.findByText('Failed to load dashboard data.')).toBeInTheDocument();
  });

  it('renders the KPI cards from the response', async () => {
    dashboardApi.get.mockResolvedValue({ data: baseData });
    render(<AdminDashboard />);

    expect(await screen.findByText('Total Products')).toBeInTheDocument();
    expect(screen.getByText('42')).toBeInTheDocument();
    expect(screen.getByText('Total Categories')).toBeInTheDocument();
    expect(screen.getByText('6')).toBeInTheDocument();
    expect(screen.getByText('0 / 0')).toBeInTheDocument();
    expect(screen.getByText('All stocked')).toBeInTheDocument();
    const revenueCard = screen.getByText('Total Revenue').closest('.db-kpi-card');
    expect(revenueCard.querySelector('.db-kpi-value')).toHaveTextContent(/234,500 TND/);
  });

  it('shows a "need attention" trend when there is low/out-of-stock inventory', async () => {
    dashboardApi.get.mockResolvedValue({
      data: { ...baseData, products: { total: 42, lowStockCount: 2, outOfStockCount: 1 } },
    });
    render(<AdminDashboard />);
    expect(await screen.findByText('3 need attention')).toBeInTheDocument();
    expect(screen.getByText('2 / 1')).toBeInTheDocument();
  });

  it('renders the monthly revenue bar chart', async () => {
    dashboardApi.get.mockResolvedValue({
      data: {
        ...baseData,
        revenue: { total: 5000, monthly: [{ month: 'Jan', year: 2026, value: 2500 }] },
      },
    });
    render(<AdminDashboard />);
    expect(await screen.findByText('Jan')).toBeInTheDocument();
    expect(screen.getByText('2.5k')).toBeInTheDocument();
  });

  it('shows a placeholder when there are no top categories', async () => {
    dashboardApi.get.mockResolvedValue({ data: baseData });
    render(<AdminDashboard />);
    expect(await screen.findByText('No categories yet.')).toBeInTheDocument();
  });

  it('renders top category bars', async () => {
    dashboardApi.get.mockResolvedValue({
      data: {
        ...baseData,
        categories: { total: 2, top: [{ id: 1, name: 'Electronics', productCount: 10 }] },
      },
    });
    render(<AdminDashboard />);
    expect(await screen.findByText('Electronics')).toBeInTheDocument();
    expect(screen.getByText('10')).toBeInTheDocument();
  });

  it('shows a placeholder when there are no top-selling products', async () => {
    dashboardApi.get.mockResolvedValue({ data: baseData });
    render(<AdminDashboard />);
    expect(await screen.findByText('No sales recorded yet.')).toBeInTheDocument();
  });

  it('renders top-selling product rows with the right stock status', async () => {
    dashboardApi.get.mockResolvedValue({
      data: {
        ...baseData,
        topSelling: [
          { id: 1, name: 'Out Item', price: 10, stock: 0, category: { name: 'Cat' } },
          { id: 2, name: 'Low Item', price: 20, stock: 5, category: { name: 'Cat' } },
          { id: 3, name: 'OK Item', price: 30, stock: 50, category: { name: 'Cat' } },
        ],
      },
    });
    render(<AdminDashboard />);

    expect(await screen.findByText('Out Item')).toBeInTheDocument();
    expect(screen.getByText('Out of Stock')).toBeInTheDocument();
    expect(screen.getByText('Low Stock')).toBeInTheDocument();
    expect(screen.getByText('In Stock')).toBeInTheDocument();
  });
});
