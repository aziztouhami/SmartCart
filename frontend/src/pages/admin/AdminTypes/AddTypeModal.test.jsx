import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { productTypeApi } from '../../../services/cartService';
import AddTypeModal from './AddTypeModal';

jest.mock('../../../services/cartService', () => ({
  productTypeApi: { create: jest.fn(), suggestAttributes: jest.fn() },
}));

function renderModal(overrides = {}) {
  return render(
    <AddTypeModal onClose={jest.fn()} onCreated={jest.fn()} showToast={jest.fn()} {...overrides} />,
  );
}

describe('AddTypeModal', () => {
  it('renders empty with no features yet', () => {
    renderModal();
    expect(screen.getByText('Add New Type')).toBeInTheDocument();
    expect(screen.getByPlaceholderText('e.g. Smartphone')).toHaveValue('');
    expect(
      screen.getByText('No features yet — suggest with AI or add one manually.'),
    ).toBeInTheDocument();
  });

  it('disables the AI-suggest button until a name is entered', () => {
    renderModal();
    expect(screen.getByText('Suggest with AI')).toBeDisabled();
  });

  it('requires a name to create', async () => {
    const user = userEvent.setup();
    renderModal();
    await user.click(screen.getByText('Create Type'));
    expect(screen.getByText('Type name is required.')).toBeInTheDocument();
    expect(productTypeApi.create).not.toHaveBeenCalled();
  });

  it('adds a manual feature row and creates the type with it', async () => {
    const user = userEvent.setup();
    const onCreated = jest.fn();
    const showToast = jest.fn();
    productTypeApi.create.mockResolvedValue({});
    renderModal({ onCreated, showToast });

    await user.type(screen.getByPlaceholderText('e.g. Smartphone'), 'Tablet');
    await user.click(screen.getByText('+ Add feature'));
    await user.type(screen.getByPlaceholderText('Feature name (e.g. Color)'), 'Screen Size');
    await user.click(screen.getByText('Create Type'));

    await waitFor(() =>
      expect(productTypeApi.create).toHaveBeenCalledWith({
        name: 'Tablet',
        attributes: [
          { name: 'Screen Size', dataType: 'text', unit: null, options: null, required: false },
        ],
      }),
    );
    expect(showToast).toHaveBeenCalledWith('Type created successfully.');
    expect(onCreated).toHaveBeenCalled();
  });

  it('shows a save error and does not close on failure', async () => {
    const user = userEvent.setup();
    const onCreated = jest.fn();
    productTypeApi.create.mockRejectedValue({
      response: { data: { error: 'Type name already exists.' } },
    });
    renderModal({ onCreated });

    await user.type(screen.getByPlaceholderText('e.g. Smartphone'), 'Tablet');
    await user.click(screen.getByText('Create Type'));

    expect(await screen.findByText('Type name already exists.')).toBeInTheDocument();
    expect(onCreated).not.toHaveBeenCalled();
  });

  it('fetches AI-suggested features and merges them in for review', async () => {
    const user = userEvent.setup();
    const showToast = jest.fn();
    productTypeApi.suggestAttributes.mockResolvedValue({
      data: {
        attributes: [
          { name: 'RAM', dataType: 'number', unit: 'GB', required: false },
          { name: 'Color', dataType: 'select', options: ['Black', 'White'], required: true },
        ],
      },
    });
    renderModal({ showToast });

    await user.type(screen.getByPlaceholderText('e.g. Smartphone'), 'Smartphone');
    await user.click(screen.getByText('Suggest with AI'));

    expect(await screen.findByDisplayValue('RAM')).toBeInTheDocument();
    expect(screen.getByDisplayValue('Color')).toBeInTheDocument();
    expect(productTypeApi.suggestAttributes).toHaveBeenCalledWith('Smartphone');
    expect(showToast).toHaveBeenCalledWith('2 features suggested — review before creating.');
  });

  it('shows an error toast when there are no suggestions', async () => {
    const user = userEvent.setup();
    const showToast = jest.fn();
    productTypeApi.suggestAttributes.mockResolvedValue({ data: { attributes: [] } });
    renderModal({ showToast });

    await user.type(screen.getByPlaceholderText('e.g. Smartphone'), 'Smartphone');
    await user.click(screen.getByText('Suggest with AI'));

    await waitFor(() =>
      expect(showToast).toHaveBeenCalledWith(
        'No suggestions found — add features manually.',
        'error',
      ),
    );
  });

  it('shows an inline error when the AI suggestion call fails', async () => {
    const user = userEvent.setup();
    productTypeApi.suggestAttributes.mockRejectedValue({
      response: { data: { error: 'AI service unavailable.' } },
    });
    renderModal();

    await user.type(screen.getByPlaceholderText('e.g. Smartphone'), 'Smartphone');
    await user.click(screen.getByText('Suggest with AI'));

    expect(await screen.findByText('AI service unavailable.')).toBeInTheDocument();
  });

  it('removes a feature row', async () => {
    const user = userEvent.setup();
    renderModal();
    await user.click(screen.getByText('+ Add feature'));
    expect(screen.getByPlaceholderText('Feature name (e.g. Color)')).toBeInTheDocument();

    await user.click(screen.getByTitle('Remove feature'));
    expect(screen.queryByPlaceholderText('Feature name (e.g. Color)')).not.toBeInTheDocument();
  });

  it('closes via the close button and via the overlay', async () => {
    const user = userEvent.setup();
    const onClose = jest.fn();
    const { container } = renderModal({ onClose });

    await user.click(screen.getByText('✕'));
    await user.click(container.querySelector('.adm-overlay'));
    expect(onClose).toHaveBeenCalledTimes(2);
  });
});
