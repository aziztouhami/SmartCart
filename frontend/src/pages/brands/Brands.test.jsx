import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import '../../i18n';
import { brandApi } from '../../services/cartService';
import Brands from './Brands';

jest.mock('../../services/cartService', () => ({
  brandApi: { list: jest.fn() },
}));

jest.mock('../../components/Navbar', () => () => <div data-testid="navbar" />);

const mockNavigate = jest.fn();
jest.mock('react-router-dom', () => ({
  ...jest.requireActual('react-router-dom'),
  useNavigate: () => mockNavigate,
}));

function renderBrands() {
  return render(
    <MemoryRouter future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
      <Brands />
    </MemoryRouter>,
  );
}

describe('Brands page', () => {
  it('shows skeleton cards while loading', () => {
    brandApi.list.mockReturnValue(new Promise(() => {}));
    const { container } = renderBrands();
    expect(container.querySelectorAll('.bd-card--skeleton')).toHaveLength(12);
  });

  it('shows the empty state when there are no brands', async () => {
    brandApi.list.mockResolvedValue({ data: { data: [] } });
    renderBrands();
    expect(await screen.findByText('No brands found')).toBeInTheDocument();
  });

  it('renders brand cards with logo, name, description and product count', async () => {
    brandApi.list.mockResolvedValue({
      data: {
        data: [
          {
            id: 1,
            name: 'Apple',
            image: '/apple.png',
            description: 'Tech products',
            productCount: 12,
          },
          { id: 2, name: 'Zenith', description: '', productCount: 1 },
        ],
      },
    });
    renderBrands();

    expect(await screen.findByText('Apple')).toBeInTheDocument();
    expect(screen.getByAltText('Apple')).toHaveAttribute('src', '/apple.png');
    expect(screen.getByText('Tech products')).toBeInTheDocument();
    expect(screen.getByText('12 products')).toBeInTheDocument();
    expect(screen.getByText('1 product')).toBeInTheDocument();

    // Brand without an image shows its initial instead
    expect(screen.getByText('Z')).toBeInTheDocument();
  });

  it('shows the total brand count once loaded', async () => {
    brandApi.list.mockResolvedValue({
      data: { data: [{ id: 1, name: 'Apple', productCount: 1 }] },
    });
    renderBrands();
    expect(await screen.findByText('1 brand')).toBeInTheDocument();
  });

  it('navigates to a search for the brand name when a brand card is clicked', async () => {
    const user = userEvent.setup();
    brandApi.list.mockResolvedValue({
      data: { data: [{ id: 1, name: 'Apple', productCount: 1 }] },
    });
    renderBrands();

    await user.click(await screen.findByText('Apple'));
    expect(mockNavigate).toHaveBeenCalledWith('/?q=Apple');
  });

  it('fetches up to 100 brands', async () => {
    brandApi.list.mockResolvedValue({ data: { data: [] } });
    renderBrands();
    expect(brandApi.list).toHaveBeenCalledWith(1, 100);
  });
});
