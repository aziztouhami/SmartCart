import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { brandApi, adminBrandApi, adminAnalyticsApi } from '../../services/cartService';
import AdminBrands from './AdminBrands';

jest.mock('../../services/cartService', () => ({
  brandApi: { list: jest.fn() },
  adminBrandApi: {
    create: jest.fn(),
    update: jest.fn(),
    remove: jest.fn(),
    uploadImage: jest.fn(),
  },
  adminAnalyticsApi: { analyzeBrand: jest.fn() },
}));

const brand1 = {
  id: 1,
  name: 'Apple',
  description: 'Tech brand',
  image: null,
  joinedAt: '2026-01-05T00:00:00Z',
  productCount: 5,
  soldCount: 20,
  revenue: 199.99,
  avgRating: 4.5,
};

describe('AdminBrands', () => {
  beforeEach(() => {
    brandApi.list.mockResolvedValue({ data: { data: [] } });
  });

  it('shows a loading row then the empty state', async () => {
    render(<AdminBrands />);
    expect(screen.getByText('Loading brands…')).toBeInTheDocument();
    expect(
      await screen.findByText('No brands yet. Click "Add Brand" to create one.'),
    ).toBeInTheDocument();
  });

  it('renders a brand row with its stats', async () => {
    brandApi.list.mockResolvedValue({ data: { data: [brand1] } });
    render(<AdminBrands />);

    expect(await screen.findByText('Apple')).toBeInTheDocument();
    expect(screen.getByText('Tech brand')).toBeInTheDocument();
    expect(screen.getByText('5')).toBeInTheDocument();
    expect(screen.getByText('20')).toBeInTheDocument();
    expect(screen.getByText('★ 4.5')).toBeInTheDocument();
    expect(screen.getByText('1 brand total')).toBeInTheDocument();
  });

  it('shows the plural brand count', async () => {
    brandApi.list.mockResolvedValue({
      data: { data: [brand1, { ...brand1, id: 2, name: 'Zenith' }] },
    });
    render(<AdminBrands />);
    expect(await screen.findByText('2 brands total')).toBeInTheDocument();
  });

  it('opens the add-brand modal empty and validates the required name', async () => {
    const user = userEvent.setup();
    render(<AdminBrands />);
    await screen.findByText('No brands yet. Click "Add Brand" to create one.');

    await user.click(screen.getByText('Add Brand'));
    expect(screen.getByText('Add New Brand')).toBeInTheDocument();
    expect(screen.getByPlaceholderText('e.g. Nike')).toHaveValue('');

    await user.click(screen.getByText('Add Brand', { selector: '.adm-btn-save' }));
    expect(screen.getByText('Name is required.')).toBeInTheDocument();
    expect(adminBrandApi.create).not.toHaveBeenCalled();
  });

  it('creates a new brand and shows a success toast', async () => {
    const user = userEvent.setup();
    adminBrandApi.create.mockResolvedValue({
      data: { id: 9, name: 'Sony', image: null, description: null },
    });
    render(<AdminBrands />);
    await screen.findByText('No brands yet. Click "Add Brand" to create one.');

    await user.click(screen.getByText('Add Brand'));
    await user.type(screen.getByPlaceholderText('e.g. Nike'), 'Sony');
    await user.click(screen.getByText('Add Brand', { selector: '.adm-btn-save' }));

    await waitFor(() =>
      expect(adminBrandApi.create).toHaveBeenCalledWith({
        name: 'Sony',
        image: null,
        description: null,
      }),
    );
    expect(await screen.findByText('Brand added successfully.')).toBeInTheDocument();
    expect(screen.getByText('Sony')).toBeInTheDocument();
  });

  it('shows a save error toast when creation fails', async () => {
    const user = userEvent.setup();
    adminBrandApi.create.mockRejectedValue({
      response: { data: { error: 'Duplicate brand name.' } },
    });
    render(<AdminBrands />);
    await screen.findByText('No brands yet. Click "Add Brand" to create one.');

    await user.click(screen.getByText('Add Brand'));
    await user.type(screen.getByPlaceholderText('e.g. Nike'), 'Sony');
    await user.click(screen.getByText('Add Brand', { selector: '.adm-btn-save' }));

    expect(await screen.findByText('Duplicate brand name.')).toBeInTheDocument();
  });

  it('opens the edit modal pre-filled with the brand data, not stale data from elsewhere', async () => {
    brandApi.list.mockResolvedValue({
      data: { data: [brand1, { ...brand1, id: 2, name: 'Zenith', description: 'Other desc' }] },
    });
    const user = userEvent.setup();
    render(<AdminBrands />);
    await screen.findByText('Zenith');

    const editButtons = screen.getAllByText('Edit');
    await user.click(editButtons[1]); // Zenith is the 2nd row

    expect(screen.getByText('Edit Brand')).toBeInTheDocument();
    expect(screen.getByPlaceholderText('e.g. Nike')).toHaveValue('Zenith');
    expect(screen.getByPlaceholderText('Brief description of the brand…')).toHaveValue(
      'Other desc',
    );
  });

  it('saves brand edits', async () => {
    brandApi.list.mockResolvedValue({ data: { data: [brand1] } });
    adminBrandApi.update.mockResolvedValue({ data: { name: 'Apple Inc.' } });
    const user = userEvent.setup();
    render(<AdminBrands />);
    await screen.findByText('Apple');

    await user.click(screen.getByText('Edit'));
    const nameInput = screen.getByPlaceholderText('e.g. Nike');
    await user.clear(nameInput);
    await user.type(nameInput, 'Apple Inc.');
    await user.click(screen.getByText('Save Changes'));

    await waitFor(() =>
      expect(adminBrandApi.update).toHaveBeenCalledWith(
        1,
        expect.objectContaining({ name: 'Apple Inc.' }),
      ),
    );
    expect(await screen.findByText('Brand updated successfully.')).toBeInTheDocument();
  });

  it('cancels out of the confirm-delete dialog without deleting', async () => {
    brandApi.list.mockResolvedValue({ data: { data: [brand1] } });
    const user = userEvent.setup();
    render(<AdminBrands />);
    await screen.findByText('Apple');

    await user.click(screen.getByText('Delete'));
    expect(screen.getByText('Delete Brand?')).toBeInTheDocument();

    await user.click(screen.getByText('Cancel', { selector: '.adm-btn-cancel' }));
    expect(adminBrandApi.remove).not.toHaveBeenCalled();
    expect(screen.getByText('Apple')).toBeInTheDocument();
  });

  it('deletes a brand after confirming', async () => {
    brandApi.list.mockResolvedValue({ data: { data: [brand1] } });
    adminBrandApi.remove.mockResolvedValue({});
    const user = userEvent.setup();
    render(<AdminBrands />);
    await screen.findByText('Apple');

    await user.click(screen.getByText('Delete'));
    await user.click(screen.getByText('Delete', { selector: '.adm-btn-save' }));

    await waitFor(() => expect(adminBrandApi.remove).toHaveBeenCalledWith(1));
    expect(await screen.findByText('Brand deleted.')).toBeInTheDocument();
    expect(screen.queryByText('Apple')).not.toBeInTheDocument();
  });

  it('runs an analysis and shows the AI report', async () => {
    brandApi.list.mockResolvedValue({ data: { data: [brand1] } });
    adminAnalyticsApi.analyzeBrand.mockResolvedValue({
      data: { healthScore: 80, summary: 'Healthy brand.', anomalies: [] },
    });
    const user = userEvent.setup();
    render(<AdminBrands />);
    await screen.findByText('Apple');

    await user.click(screen.getByText('Analyze'));
    expect(screen.getByText('AI Analysis — Apple')).toBeInTheDocument();

    expect(await screen.findByText('Healthy brand.')).toBeInTheDocument();
    expect(adminAnalyticsApi.analyzeBrand).toHaveBeenCalledWith(1);
  });
});
