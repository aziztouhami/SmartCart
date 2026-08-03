import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import AdminLayout from './AdminLayout';

const mockNavigate = jest.fn();
jest.mock('react-router-dom', () => ({
  ...jest.requireActual('react-router-dom'),
  useNavigate: () => mockNavigate,
}));

function renderLayout(route = '/admin') {
  return render(
    <MemoryRouter
      initialEntries={[route]}
      future={{ v7_startTransition: true, v7_relativeSplatPath: true }}
    >
      <Routes>
        <Route path="/admin" element={<AdminLayout />}>
          <Route index element={<div data-testid="dashboard-page" />} />
          <Route path="products" element={<div data-testid="products-page" />} />
        </Route>
      </Routes>
    </MemoryRouter>,
  );
}

describe('AdminLayout', () => {
  beforeEach(() => mockNavigate.mockClear());

  it('renders the brand, admin user info and nav links', () => {
    renderLayout();
    expect(screen.getByAltText('SmartCart')).toBeInTheDocument();
    expect(screen.getByText('Admin Panel')).toBeInTheDocument();
    expect(screen.getByText('Admin')).toBeInTheDocument();
    expect(screen.getByText('admin@smartcart.com')).toBeInTheDocument();
    ['Dashboard', 'Categories', 'Products', 'Types', 'Orders', 'Brands', 'Promotions'].forEach(
      label => {
        expect(screen.getByText(label)).toBeInTheDocument();
      },
    );
  });

  it('renders the nested route content via the Outlet', () => {
    renderLayout('/admin');
    expect(screen.getByTestId('dashboard-page')).toBeInTheDocument();
  });

  it('renders a different nested page for a different route', () => {
    renderLayout('/admin/products');
    expect(screen.getByTestId('products-page')).toBeInTheDocument();
  });

  it('marks the Dashboard link active only on the exact /admin route', () => {
    renderLayout('/admin');
    expect(screen.getByText('Dashboard').closest('a')).toHaveClass('al-nav-item--active');
    expect(screen.getByText('Products').closest('a')).not.toHaveClass('al-nav-item--active');
  });

  it('marks the Products link active on /admin/products', () => {
    renderLayout('/admin/products');
    expect(screen.getByText('Products').closest('a')).toHaveClass('al-nav-item--active');
    expect(screen.getByText('Dashboard').closest('a')).not.toHaveClass('al-nav-item--active');
  });

  it('navigates to the storefront when "View Store" is clicked', async () => {
    const user = userEvent.setup();
    renderLayout();
    await user.click(screen.getByText('View Store'));
    expect(mockNavigate).toHaveBeenCalledWith('/');
  });

  it('navigates to login when the sign-out button is clicked', async () => {
    const user = userEvent.setup();
    renderLayout();
    await user.click(screen.getByTitle('Sign out'));
    expect(mockNavigate).toHaveBeenCalledWith('/login');
  });
});
