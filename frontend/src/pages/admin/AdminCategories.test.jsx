import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { categoryApi, adminCategoryApi, adminAnalyticsApi } from '../../services/cartService';
import { uploadImage } from '../../services/uploadService';
import AdminCategories from './AdminCategories';

jest.mock('../../services/cartService', () => ({
  categoryApi: { list: jest.fn(), products: jest.fn() },
  adminCategoryApi: { create: jest.fn(), update: jest.fn(), remove: jest.fn() },
  adminAnalyticsApi: { analyzeCategory: jest.fn() },
}));

jest.mock('../../services/uploadService', () => ({
  uploadImage: jest.fn(),
}));

// jsdom doesn't implement createObjectURL; AdminCategories calls it as soon
// as a file is picked, to preview it before upload.
beforeAll(() => {
  global.URL.createObjectURL = jest.fn(() => 'blob:preview');
});

const tree = [
  {
    id: 1,
    name: 'Electronics',
    slug: 'electronics',
    image: null,
    seasonalMonths: [11, 12],
    children: [{ id: 10, name: 'Phones', slug: 'phones', image: null, seasonalMonths: [] }],
  },
];

describe('AdminCategories', () => {
  beforeEach(() => {
    categoryApi.list.mockResolvedValue({ data: tree });
    categoryApi.products.mockResolvedValue({ data: { category: { productCount: 3 } } });
  });

  it('shows a loading row then flattens the category tree into rows with counts', async () => {
    render(<AdminCategories />);
    expect(screen.getByText('Loading categories…')).toBeInTheDocument();

    expect(await screen.findByText('Electronics', { selector: '.ac-label' })).toBeInTheDocument();
    expect(screen.getByText('Phones')).toBeInTheDocument();
    expect(screen.getAllByText('3')).toHaveLength(2);
    expect(screen.getByText('2 total · 1 parent · 1 subcategories')).toBeInTheDocument();
  });

  it('shows the season badge for categories with seasonal months', async () => {
    render(<AdminCategories />);
    expect(await screen.findByText('Nov, Dec')).toBeInTheDocument();
  });

  it('shows the parent badge for subcategories', async () => {
    render(<AdminCategories />);
    await screen.findByText('Phones');
    expect(screen.getByText('Electronics', { selector: '.adm-parent-badge' })).toBeInTheDocument();
  });

  it('shows an empty state when nothing is found', async () => {
    categoryApi.list.mockResolvedValue({ data: [] });
    render(<AdminCategories />);
    expect(await screen.findByText('No categories found.')).toBeInTheDocument();
  });

  it('filters rows by the search box', async () => {
    const user = userEvent.setup();
    render(<AdminCategories />);
    await screen.findByText('Electronics', { selector: '.ac-label' });

    await user.type(screen.getByPlaceholderText('Search categories...'), 'phone');
    expect(screen.getByText('1 result')).toBeInTheDocument();
    expect(screen.queryByText('Electronics', { selector: '.ac-label' })).not.toBeInTheDocument();
    expect(screen.getByText('Phones')).toBeInTheDocument();
  });

  it('opens the add-category modal and enforces the required name', async () => {
    const user = userEvent.setup();
    render(<AdminCategories />);
    await screen.findByText('Electronics', { selector: '.ac-label' });

    await user.click(screen.getByText('Add Category'));
    expect(screen.getByText('Add New Category')).toBeInTheDocument();
    expect(screen.getByText('Add Category', { selector: '.adm-btn-save' })).toBeDisabled();
  });

  it('creates a new category with a selected parent and seasonal months', async () => {
    const user = userEvent.setup();
    adminCategoryApi.create.mockResolvedValue({});
    render(<AdminCategories />);
    await screen.findByText('Electronics', { selector: '.ac-label' });

    await user.click(screen.getByText('Add Category'));
    await user.type(screen.getByPlaceholderText('e.g. Smartphones'), 'Laptops');
    await user.selectOptions(screen.getByRole('combobox'), '1');
    await user.click(screen.getByText('Jan'));
    await user.click(screen.getByText('Add Category', { selector: '.adm-btn-save' }));

    await waitFor(() =>
      expect(adminCategoryApi.create).toHaveBeenCalledWith({
        name: 'Laptops',
        parentId: 1,
        image: null,
        seasonalMonths: [1],
      }),
    );
    expect(await screen.findByText('Category added successfully.')).toBeInTheDocument();
  });

  it('opens the edit modal pre-filled and excludes the category itself from the parent list', async () => {
    const user = userEvent.setup();
    render(<AdminCategories />);
    await screen.findByText('Electronics', { selector: '.ac-label' });

    const editButtons = screen.getAllByText('Edit');
    await user.click(editButtons[0]); // Electronics itself

    expect(screen.getByText('Edit Category')).toBeInTheDocument();
    expect(screen.getByPlaceholderText('e.g. Smartphones')).toHaveValue('Electronics');
    // Electronics shouldn't be able to be its own parent
    expect(screen.queryByRole('option', { name: 'Electronics' })).not.toBeInTheDocument();
  });

  it('uploads the image via uploadService and saves with the returned URL', async () => {
    const user = userEvent.setup();
    uploadImage.mockResolvedValue('/uploads/cat.png');
    adminCategoryApi.create.mockResolvedValue({});
    render(<AdminCategories />);
    await screen.findByText('Electronics', { selector: '.ac-label' });

    await user.click(screen.getByText('Add Category'));
    await user.type(screen.getByPlaceholderText('e.g. Smartphones'), 'Laptops');

    const file = new File(['bytes'], 'cat.png', { type: 'image/png' });
    const fileInput = document.querySelector('input[type="file"]');
    await user.upload(fileInput, file);

    await user.click(screen.getByText('Add Category', { selector: '.adm-btn-save' }));

    await waitFor(() =>
      expect(adminCategoryApi.create).toHaveBeenCalledWith(
        expect.objectContaining({ image: '/uploads/cat.png' }),
      ),
    );
  });

  it('falls back to no image and shows a toast when the image upload fails', async () => {
    const user = userEvent.setup();
    uploadImage.mockRejectedValue(new Error('upload failed'));
    // Held open so the intermediate "upload failed" toast is observable
    // before the later "added successfully" toast would replace it.
    let resolveCreate;
    adminCategoryApi.create.mockReturnValue(new Promise(resolve => (resolveCreate = resolve)));
    render(<AdminCategories />);
    await screen.findByText('Electronics', { selector: '.ac-label' });

    await user.click(screen.getByText('Add Category'));
    await user.type(screen.getByPlaceholderText('e.g. Smartphones'), 'Laptops');
    const file = new File(['bytes'], 'cat.png', { type: 'image/png' });
    await user.upload(document.querySelector('input[type="file"]'), file);
    await user.click(screen.getByText('Add Category', { selector: '.adm-btn-save' }));

    expect(
      await screen.findByText('Image upload failed. Saving without image.'),
    ).toBeInTheDocument();
    await waitFor(() =>
      expect(adminCategoryApi.create).toHaveBeenCalledWith(
        expect.objectContaining({ image: null }),
      ),
    );
    resolveCreate({});
  });

  it('deletes a category after confirming, warning about subcategories', async () => {
    const user = userEvent.setup();
    adminCategoryApi.remove.mockResolvedValue({});
    render(<AdminCategories />);
    await screen.findByText('Electronics', { selector: '.ac-label' });

    const deleteButtons = screen.getAllByText('Delete');
    await user.click(deleteButtons[0]);
    expect(screen.getByText(/This will also remove all subcategories/)).toBeInTheDocument();

    await user.click(screen.getByText('Delete', { selector: '.adm-btn-save' }));
    await waitFor(() => expect(adminCategoryApi.remove).toHaveBeenCalledWith(1));
    expect(await screen.findByText('Category deleted.')).toBeInTheDocument();
  });

  it('shows a delete error toast returned by the backend (e.g. category still has products)', async () => {
    const user = userEvent.setup();
    adminCategoryApi.remove.mockRejectedValue({
      response: { data: { error: 'Cannot delete: category still has products.' } },
    });
    render(<AdminCategories />);
    await screen.findByText('Electronics', { selector: '.ac-label' });

    await user.click(screen.getAllByText('Delete')[0]);
    await user.click(screen.getByText('Delete', { selector: '.adm-btn-save' }));

    expect(
      await screen.findByText('Cannot delete: category still has products.'),
    ).toBeInTheDocument();
  });

  it('runs an analysis for a category', async () => {
    const user = userEvent.setup();
    adminAnalyticsApi.analyzeCategory.mockResolvedValue({
      data: { healthScore: 60, anomalies: [] },
    });
    render(<AdminCategories />);
    await screen.findByText('Electronics', { selector: '.ac-label' });

    await user.click(screen.getAllByText('Analyze')[0]);
    expect(screen.getByText('AI Analysis — Electronics')).toBeInTheDocument();
    await waitFor(() => expect(adminAnalyticsApi.analyzeCategory).toHaveBeenCalledWith(1));
  });
});
