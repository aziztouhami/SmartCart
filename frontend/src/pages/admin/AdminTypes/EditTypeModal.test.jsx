import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { productTypeApi } from '../../../services/cartService';
import EditTypeModal from './EditTypeModal';

jest.mock('../../../services/cartService', () => ({
  productTypeApi: {
    rename: jest.fn(),
    addAttribute: jest.fn(),
    removeAttribute: jest.fn(),
    suggestAttributes: jest.fn(),
  },
}));

const type = {
  id: 1,
  name: 'Smartphone',
  attributes: [{ id: 10, name: 'Color', dataType: 'text', unit: '', required: true }],
};

function renderModal(overrides = {}) {
  return render(
    <EditTypeModal
      type={type}
      onClose={jest.fn()}
      onRenamed={jest.fn()}
      onTypeUpdated={jest.fn()}
      showToast={jest.fn()}
      {...overrides}
    />,
  );
}

describe('EditTypeModal', () => {
  it('pre-fills the name and lists existing features with their metadata', () => {
    renderModal();
    expect(screen.getByDisplayValue('Smartphone')).toBeInTheDocument();
    expect(screen.getByText('Color')).toBeInTheDocument();
    expect(screen.getByText('Text · required')).toBeInTheDocument();
  });

  it('shows a placeholder when the type has no features', () => {
    renderModal({ type: { ...type, attributes: [] } });
    expect(screen.getByText('No features defined yet.')).toBeInTheDocument();
  });

  it('requires a name to save', async () => {
    const user = userEvent.setup();
    renderModal();
    const nameInput = screen.getByDisplayValue('Smartphone');
    await user.clear(nameInput);
    await user.click(screen.getByText('Save Changes'));

    expect(screen.getByText('Type name is required.')).toBeInTheDocument();
    expect(productTypeApi.rename).not.toHaveBeenCalled();
  });

  it('closes without an API call when the name is unchanged', async () => {
    const user = userEvent.setup();
    const onClose = jest.fn();
    renderModal({ onClose });
    await user.click(screen.getByText('Save Changes'));

    expect(productTypeApi.rename).not.toHaveBeenCalled();
    expect(onClose).toHaveBeenCalled();
  });

  it('renames the type', async () => {
    const user = userEvent.setup();
    const onRenamed = jest.fn();
    const showToast = jest.fn();
    productTypeApi.rename.mockResolvedValue({});
    renderModal({ onRenamed, showToast });

    const nameInput = screen.getByDisplayValue('Smartphone');
    await user.clear(nameInput);
    await user.type(nameInput, 'Mobile Phone');
    await user.click(screen.getByText('Save Changes'));

    await waitFor(() =>
      expect(productTypeApi.rename).toHaveBeenCalledWith(1, { name: 'Mobile Phone' }),
    );
    expect(showToast).toHaveBeenCalledWith('Type renamed successfully.');
    expect(onRenamed).toHaveBeenCalled();
  });

  it('shows an error when renaming fails', async () => {
    const user = userEvent.setup();
    productTypeApi.rename.mockRejectedValue({
      response: { data: { error: 'Name already taken.' } },
    });
    renderModal();

    const nameInput = screen.getByDisplayValue('Smartphone');
    await user.clear(nameInput);
    await user.type(nameInput, 'Mobile Phone');
    await user.click(screen.getByText('Save Changes'));

    expect(await screen.findByText('Name already taken.')).toBeInTheDocument();
  });

  it('removes an existing feature', async () => {
    const user = userEvent.setup();
    const onTypeUpdated = jest.fn();
    const showToast = jest.fn();
    productTypeApi.removeAttribute.mockResolvedValue({ data: { ...type, attributes: [] } });
    renderModal({ onTypeUpdated, showToast });

    await user.click(screen.getByText('Remove'));

    await waitFor(() => expect(productTypeApi.removeAttribute).toHaveBeenCalledWith(1, 10));
    expect(onTypeUpdated).toHaveBeenCalledWith({ ...type, attributes: [] });
    expect(showToast).toHaveBeenCalledWith('Feature removed.');
    expect(await screen.findByText('No features defined yet.')).toBeInTheDocument();
  });

  it('shows an error toast when removing a feature fails', async () => {
    const user = userEvent.setup();
    const showToast = jest.fn();
    productTypeApi.removeAttribute.mockRejectedValue({
      response: { data: { error: 'Feature still used by products.' } },
    });
    renderModal({ showToast });

    await user.click(screen.getByText('Remove'));
    await waitFor(() =>
      expect(showToast).toHaveBeenCalledWith('Feature still used by products.', 'error'),
    );
  });

  it('opens the add-feature panel and adds a new feature to the type', async () => {
    const user = userEvent.setup();
    const onTypeUpdated = jest.fn();
    const showToast = jest.fn();
    const updatedType = { ...type, attributes: [...type.attributes, { id: 11, name: 'RAM' }] };
    productTypeApi.addAttribute.mockResolvedValue({ data: updatedType });
    renderModal({ onTypeUpdated, showToast });

    await user.click(screen.getByText('+ Add a new feature to "Smartphone"'));
    await user.type(screen.getByPlaceholderText('Feature name (e.g. Color)'), 'RAM');
    await user.click(screen.getByText('Add Feature'));

    await waitFor(() =>
      expect(productTypeApi.addAttribute).toHaveBeenCalledWith(
        1,
        expect.objectContaining({ name: 'RAM' }),
      ),
    );
    expect(onTypeUpdated).toHaveBeenCalledWith(updatedType);
    expect(showToast).toHaveBeenCalledWith('Feature added.');
    expect(screen.queryByPlaceholderText('Feature name (e.g. Color)')).not.toBeInTheDocument();
  });

  it('cancels the add-feature panel', async () => {
    const user = userEvent.setup();
    renderModal();
    await user.click(screen.getByText('+ Add a new feature to "Smartphone"'));
    // The feature panel's own Cancel comes before the modal footer's Cancel.
    await user.click(screen.getAllByText('Cancel')[0]);
    expect(screen.queryByPlaceholderText('Feature name (e.g. Color)')).not.toBeInTheDocument();
    expect(screen.getByText('+ Add a new feature to "Smartphone"')).toBeInTheDocument();
  });

  it('fetches AI suggestions for new features, excluding ones the type already has', async () => {
    const user = userEvent.setup();
    const showToast = jest.fn();
    productTypeApi.suggestAttributes.mockResolvedValue({
      data: {
        attributes: [
          { name: 'Color', dataType: 'text' }, // already exists — should be filtered out
          { name: 'RAM', dataType: 'number', unit: 'GB' },
        ],
      },
    });
    renderModal({ showToast });

    await user.click(screen.getByText('Suggest with AI'));

    expect(productTypeApi.suggestAttributes).toHaveBeenCalledWith('Smartphone', ['Color']);
    expect(await screen.findByDisplayValue('RAM')).toBeInTheDocument();
    expect(screen.queryByDisplayValue('Color')).not.toBeInTheDocument();
    expect(showToast).toHaveBeenCalledWith('1 new feature suggested — review before adding.');
  });

  it('lets the admin discard suggested features without adding them', async () => {
    const user = userEvent.setup();
    productTypeApi.suggestAttributes.mockResolvedValue({
      data: { attributes: [{ name: 'RAM', dataType: 'number' }] },
    });
    renderModal();

    await user.click(screen.getByText('Suggest with AI'));
    await screen.findByDisplayValue('RAM');

    await user.click(screen.getByText('Discard'));
    expect(screen.queryByDisplayValue('RAM')).not.toBeInTheDocument();
  });

  it('adds all suggested features in one action', async () => {
    const user = userEvent.setup();
    const onTypeUpdated = jest.fn();
    const showToast = jest.fn();
    productTypeApi.suggestAttributes.mockResolvedValue({
      data: {
        attributes: [
          { name: 'RAM', dataType: 'number', unit: 'GB' },
          { name: 'Storage', dataType: 'number', unit: 'GB' },
        ],
      },
    });
    productTypeApi.addAttribute
      .mockResolvedValueOnce({
        data: { ...type, attributes: [...type.attributes, { id: 11, name: 'RAM' }] },
      })
      .mockResolvedValueOnce({
        data: {
          ...type,
          attributes: [...type.attributes, { id: 11, name: 'RAM' }, { id: 12, name: 'Storage' }],
        },
      });
    renderModal({ onTypeUpdated, showToast });

    await user.click(screen.getByText('Suggest with AI'));
    await screen.findByDisplayValue('RAM');

    await user.click(screen.getByText('Add all (2)'));

    await waitFor(() => expect(productTypeApi.addAttribute).toHaveBeenCalledTimes(2));
    expect(showToast).toHaveBeenCalledWith('2 features added.');
    expect(screen.queryByDisplayValue('RAM')).not.toBeInTheDocument();
  });

  it('stops adding suggested features on the first failure and keeps the rest for retry', async () => {
    const user = userEvent.setup();
    const showToast = jest.fn();
    productTypeApi.suggestAttributes.mockResolvedValue({
      data: {
        attributes: [
          { name: 'RAM', dataType: 'number', unit: 'GB' },
          { name: 'Storage', dataType: 'number', unit: 'GB' },
        ],
      },
    });
    productTypeApi.addAttribute.mockRejectedValue({
      response: { data: { error: 'Feature name already used.' } },
    });
    renderModal({ showToast });

    await user.click(screen.getByText('Suggest with AI'));
    await screen.findByDisplayValue('RAM');

    await user.click(screen.getByText('Add all (2)'));

    await waitFor(() =>
      expect(showToast).toHaveBeenCalledWith('Feature name already used.', 'error'),
    );
    expect(productTypeApi.addAttribute).toHaveBeenCalledTimes(1);
    // Both rows remain since the very first call failed.
    expect(screen.getByDisplayValue('RAM')).toBeInTheDocument();
    expect(screen.getByDisplayValue('Storage')).toBeInTheDocument();
  });

  it('shows an inline error when suggestions fail', async () => {
    const user = userEvent.setup();
    productTypeApi.suggestAttributes.mockRejectedValue({
      response: { data: { error: 'AI service unavailable.' } },
    });
    renderModal();

    await user.click(screen.getByText('Suggest with AI'));
    expect(await screen.findByText('AI service unavailable.')).toBeInTheDocument();
  });

  it('closes via the close button and the overlay', async () => {
    const user = userEvent.setup();
    const onClose = jest.fn();
    const { container } = renderModal({ onClose });

    await user.click(screen.getByText('✕'));
    await user.click(container.querySelector('.adm-overlay'));
    expect(onClose).toHaveBeenCalledTimes(2);
  });
});
