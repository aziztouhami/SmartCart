import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { adminProductApi, productTypeApi } from '../../../services/cartService';
import { uploadImage } from '../../../services/uploadService';
import ProductFormModal from './ProductFormModal';

jest.mock('../../../services/cartService', () => ({
  adminProductApi: { create: jest.fn(), update: jest.fn() },
  productTypeApi: { create: jest.fn(), addAttribute: jest.fn() },
}));

jest.mock('../../../services/uploadService', () => ({
  uploadImage: jest.fn(),
}));

beforeAll(() => {
  global.URL.createObjectURL = jest.fn(() => 'blob:preview');
});

function fieldControl(labelText) {
  return screen.getByText(labelText).closest('.adm-field').querySelector('input, select, textarea');
}

const leafCategories = [{ id: 3, name: 'Phones', parentName: 'Electronics' }];
const brands = [{ id: 2, name: 'Acme' }];
const smartphoneType = {
  id: 7,
  name: 'Smartphone',
  attributes: [{ slug: 'color', name: 'Color', dataType: 'text', unit: '', required: false }],
};

const existingProduct = {
  id: 1,
  name: 'Widget',
  description: 'A nice widget.',
  price: 19.99,
  stock: 5,
  category: { id: 3 },
  brand: { id: 2 },
  productType: { id: 7 },
  attributes: { color: 'Blue' },
  images: ['/widget.jpg'],
};

function renderModal(overrides = {}) {
  return render(
    <ProductFormModal
      mode="add"
      product={null}
      leafCategories={leafCategories}
      brands={brands}
      productTypes={[smartphoneType]}
      setProductTypes={jest.fn()}
      onClose={jest.fn()}
      onSaved={jest.fn()}
      showToast={jest.fn()}
      {...overrides}
    />,
  );
}

describe('ProductFormModal', () => {
  it('shows an empty form for add mode', () => {
    renderModal();
    expect(screen.getByText('Add New Product')).toBeInTheDocument();
    expect(fieldControl('Product Name *')).toHaveValue('');
    expect(screen.getByText('Add Product', { selector: '.adm-btn-save' })).toBeInTheDocument();
  });

  it('pre-fills the form from the product in edit mode, not stale data', () => {
    renderModal({ mode: 'edit', product: existingProduct });
    expect(screen.getByText('Edit Product')).toBeInTheDocument();
    expect(fieldControl('Product Name *')).toHaveValue('Widget');
    expect(fieldControl('Price (TND) *')).toHaveValue(19.99);
    expect(fieldControl('Stock Quantity *')).toHaveValue(5);
    expect(fieldControl('Category *')).toHaveValue('3');
    expect(fieldControl('Brand')).toHaveValue('2');
    expect(fieldControl('Product Type')).toHaveValue('7');
    expect(fieldControl('Color')).toHaveValue('Blue');
  });

  it('validates required fields', async () => {
    const user = userEvent.setup();
    renderModal();
    await user.click(screen.getByText('Add Product', { selector: '.adm-btn-save' }));

    expect(screen.getByText('Name is required.')).toBeInTheDocument();
    expect(screen.getByText('Please select a category.')).toBeInTheDocument();
    expect(screen.getByText('Enter a valid price.')).toBeInTheDocument();
    expect(screen.getByText('Enter a valid stock quantity.')).toBeInTheDocument();
    expect(adminProductApi.create).not.toHaveBeenCalled();
  });

  it('creates a product with the entered values', async () => {
    const user = userEvent.setup();
    const onSaved = jest.fn();
    adminProductApi.create.mockResolvedValue({});
    renderModal({ onSaved });

    await user.type(fieldControl('Product Name *'), 'New Gadget');
    await user.type(fieldControl('Price (TND) *'), '49.99');
    await user.type(fieldControl('Stock Quantity *'), '10');
    await user.selectOptions(fieldControl('Category *'), '3');
    await user.selectOptions(fieldControl('Brand'), '2');
    await user.click(screen.getByText('Add Product', { selector: '.adm-btn-save' }));

    await waitFor(() =>
      expect(adminProductApi.create).toHaveBeenCalledWith(
        expect.objectContaining({
          name: 'New Gadget',
          price: 49.99,
          stock: 10,
          categoryId: 3,
          brandId: 2,
          productTypeId: null,
          images: [],
        }),
      ),
    );
    expect(onSaved).toHaveBeenCalled();
  });

  it('shows a toast error when saving fails', async () => {
    const user = userEvent.setup();
    const showToast = jest.fn();
    adminProductApi.create.mockRejectedValue({ response: { data: { error: 'Duplicate name.' } } });
    renderModal({ showToast });

    await user.type(fieldControl('Product Name *'), 'X');
    await user.type(fieldControl('Price (TND) *'), '1');
    await user.type(fieldControl('Stock Quantity *'), '1');
    await user.selectOptions(fieldControl('Category *'), '3');
    await user.click(screen.getByText('Add Product', { selector: '.adm-btn-save' }));

    await waitFor(() => expect(showToast).toHaveBeenCalledWith('Duplicate name.', 'error'));
  });

  it('uploads a picked image and includes its URL in the payload', async () => {
    const user = userEvent.setup();
    uploadImage.mockResolvedValue('/uploads/gadget.png');
    adminProductApi.create.mockResolvedValue({});
    renderModal();

    await user.type(fieldControl('Product Name *'), 'X');
    await user.type(fieldControl('Price (TND) *'), '1');
    await user.type(fieldControl('Stock Quantity *'), '1');
    await user.selectOptions(fieldControl('Category *'), '3');

    const file = new File(['bytes'], 'gadget.png', { type: 'image/png' });
    await user.upload(document.querySelector('input[type="file"]'), file);
    await user.click(screen.getByText('Add Product', { selector: '.adm-btn-save' }));

    await waitFor(() =>
      expect(adminProductApi.create).toHaveBeenCalledWith(
        expect.objectContaining({ images: ['/uploads/gadget.png'] }),
      ),
    );
  });

  it('selects a product type and shows its feature inputs', async () => {
    const user = userEvent.setup();
    renderModal();

    await user.selectOptions(fieldControl('Product Type'), '7');
    expect(screen.getByText('Smartphone Features')).toBeInTheDocument();
    expect(fieldControl('Color')).toBeInTheDocument();
  });

  it('opens the inline "create new type" panel and creates a type', async () => {
    const user = userEvent.setup();
    const setProductTypes = jest.fn();
    const showToast = jest.fn();
    productTypeApi.create.mockResolvedValue({ data: { id: 8, name: 'Tablet', attributes: [] } });
    renderModal({ setProductTypes, showToast });

    await user.selectOptions(fieldControl('Product Type'), '__new__');
    expect(screen.getByText('New Type Name *')).toBeInTheDocument();

    await user.type(fieldControl('New Type Name *'), 'Tablet');
    await user.click(screen.getByText('Create Type'));

    await waitFor(() =>
      expect(productTypeApi.create).toHaveBeenCalledWith({ name: 'Tablet', attributes: [] }),
    );
    expect(setProductTypes).toHaveBeenCalled();
    expect(showToast).toHaveBeenCalledWith('Type "Tablet" created.');
    expect(screen.queryByText('New Type Name *')).not.toBeInTheDocument();
  });

  it('requires a name to create a new type', async () => {
    const user = userEvent.setup();
    renderModal();
    await user.selectOptions(fieldControl('Product Type'), '__new__');
    await user.click(screen.getByText('Create Type'));
    expect(screen.getByText('Type name is required.')).toBeInTheDocument();
    expect(productTypeApi.create).not.toHaveBeenCalled();
  });

  it('cancels out of the "create new type" panel', async () => {
    const user = userEvent.setup();
    renderModal();
    await user.selectOptions(fieldControl('Product Type'), '__new__');
    await user.type(fieldControl('New Type Name *'), 'Tablet');

    // The type-create panel's own Cancel button comes before the modal
    // footer's Cancel (which just calls onClose, an inert spy here).
    const cancelButtons = screen.getAllByText('Cancel');
    await user.click(cancelButtons[0]);
    expect(screen.queryByText('New Type Name *')).not.toBeInTheDocument();
  });

  it('adds a feature to an already-selected type', async () => {
    const user = userEvent.setup();
    const setProductTypes = jest.fn();
    const showToast = jest.fn();
    productTypeApi.addAttribute.mockResolvedValue({
      data: {
        ...smartphoneType,
        attributes: [...smartphoneType.attributes, { slug: 'ram', name: 'RAM' }],
      },
    });
    renderModal({ mode: 'edit', product: existingProduct, setProductTypes, showToast });

    await user.click(screen.getByText('+ Add a new feature to "Smartphone"'));
    await user.type(screen.getByPlaceholderText('Feature name (e.g. Color)'), 'RAM');
    await user.click(screen.getByText('Add Feature'));

    await waitFor(() =>
      expect(productTypeApi.addAttribute).toHaveBeenCalledWith(
        7,
        expect.objectContaining({ name: 'RAM' }),
      ),
    );
    expect(setProductTypes).toHaveBeenCalled();
    expect(showToast).toHaveBeenCalledWith('Feature added.');
  });

  it('closes via the close button and via the overlay', async () => {
    const user = userEvent.setup();
    const onClose = jest.fn();
    const { container } = renderModal({ onClose });

    await user.click(screen.getByText('✕'));
    await user.click(container.querySelector('.adm-overlay'));
    expect(onClose).toHaveBeenCalledTimes(2);
  });

  it('does not close when clicking inside the modal body', async () => {
    const user = userEvent.setup();
    const onClose = jest.fn();
    const { container } = renderModal({ onClose });
    await user.click(container.querySelector('.adm-modal'));
    expect(onClose).not.toHaveBeenCalled();
  });
});
