import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { productTypeApi, adminAnalyticsApi } from '../../services/cartService';
import AdminTypes from './AdminTypes';

jest.mock('../../services/cartService', () => ({
  productTypeApi: { list: jest.fn(), remove: jest.fn() },
  adminAnalyticsApi: { analyzeProductType: jest.fn() },
}));

jest.mock(
  './AdminTypes/TypesTable',
  () =>
    ({ types, loading, onEdit, onDeleteRequest, onAnalyze }) => (
      <div data-testid="types-table">
        <span data-testid="table-loading">{String(loading)}</span>
        {types.map(t => (
          <div key={t.id} data-testid="table-row">
            <span>{t.name}</span>
            <button onClick={() => onEdit(t)}>edit-{t.id}</button>
            <button onClick={() => onDeleteRequest(t.id)}>delete-{t.id}</button>
            <button onClick={() => onAnalyze(t)}>analyze-{t.id}</button>
          </div>
        ))}
      </div>
    ),
);

jest.mock('./AdminTypes/AddTypeModal', () => ({ onClose, onCreated }) => (
  <div data-testid="add-type-modal">
    <button onClick={onClose}>close-add</button>
    <button onClick={onCreated}>save-add</button>
  </div>
));

jest.mock('./AdminTypes/EditTypeModal', () => ({ type, onClose, onRenamed }) => (
  <div data-testid="edit-type-modal">
    <span data-testid="edit-target">{type.name}</span>
    <button onClick={onClose}>close-edit</button>
    <button onClick={onRenamed}>save-edit</button>
  </div>
));

const types = [
  { id: 1, name: 'Smartphone', attributes: [{ id: 10 }, { id: 11 }] },
  { id: 2, name: 'Laptop', attributes: [{ id: 12 }] },
];

describe('AdminTypes', () => {
  beforeEach(() => {
    productTypeApi.list.mockResolvedValue({ data: types });
  });

  it('loads types and renders them via TypesTable', async () => {
    render(<AdminTypes />);
    expect(screen.getByTestId('table-loading')).toHaveTextContent('true');

    expect(await screen.findByText('Smartphone')).toBeInTheDocument();
    expect(screen.getByText('Laptop')).toBeInTheDocument();
  });

  it('shows a toast error when loading fails', async () => {
    productTypeApi.list.mockRejectedValue(new Error('network error'));
    render(<AdminTypes />);
    expect(await screen.findByText('Failed to load product types.')).toBeInTheDocument();
  });

  it('shows the type count and total feature count', async () => {
    render(<AdminTypes />);
    await screen.findByText('Smartphone');
    expect(screen.getByText(/2 product types/)).toBeInTheDocument();
    expect(screen.getByText(/3 features total/)).toBeInTheDocument();
  });

  it('filters the table by the search box', async () => {
    const user = userEvent.setup();
    render(<AdminTypes />);
    await screen.findByText('Smartphone');

    await user.type(screen.getByPlaceholderText('Search types...'), 'lap');
    expect(screen.queryByText('Smartphone')).not.toBeInTheDocument();
    expect(screen.getByText('Laptop')).toBeInTheDocument();
  });

  it('opens the add-type modal and reloads on creation', async () => {
    const user = userEvent.setup();
    render(<AdminTypes />);
    await screen.findByText('Smartphone');
    productTypeApi.list.mockClear();

    await user.click(screen.getByText('Add Type'));
    expect(screen.getByTestId('add-type-modal')).toBeInTheDocument();

    await user.click(screen.getByText('save-add'));
    expect(screen.queryByTestId('add-type-modal')).not.toBeInTheDocument();
    await waitFor(() => expect(productTypeApi.list).toHaveBeenCalled());
  });

  it('closes the add-type modal without reloading when cancelled', async () => {
    const user = userEvent.setup();
    render(<AdminTypes />);
    await screen.findByText('Smartphone');
    productTypeApi.list.mockClear();

    await user.click(screen.getByText('Add Type'));
    await user.click(screen.getByText('close-add'));
    expect(screen.queryByTestId('add-type-modal')).not.toBeInTheDocument();
    expect(productTypeApi.list).not.toHaveBeenCalled();
  });

  it('opens the edit modal for the specific clicked type, not stale data', async () => {
    const user = userEvent.setup();
    render(<AdminTypes />);
    await screen.findByText('Smartphone');

    await user.click(screen.getByText('edit-2'));
    expect(screen.getByTestId('edit-target')).toHaveTextContent('Laptop');
  });

  it('reloads after a rename is saved from the edit modal', async () => {
    const user = userEvent.setup();
    render(<AdminTypes />);
    await screen.findByText('Smartphone');
    productTypeApi.list.mockClear();

    await user.click(screen.getByText('edit-1'));
    await user.click(screen.getByText('save-edit'));
    expect(screen.queryByTestId('edit-type-modal')).not.toBeInTheDocument();
    await waitFor(() => expect(productTypeApi.list).toHaveBeenCalled());
  });

  it('deletes a type after confirming', async () => {
    const user = userEvent.setup();
    productTypeApi.remove.mockResolvedValue({});
    render(<AdminTypes />);
    await screen.findByText('Smartphone');

    await user.click(screen.getByText('delete-1'));
    expect(screen.getByText('Delete Type?')).toBeInTheDocument();

    await user.click(screen.getByText('Delete'));
    await waitFor(() => expect(productTypeApi.remove).toHaveBeenCalledWith(1));
    expect(await screen.findByText('Type deleted.')).toBeInTheDocument();
    expect(screen.queryByText('Smartphone')).not.toBeInTheDocument();
  });

  it('shows a backend delete error (e.g. type still in use)', async () => {
    const user = userEvent.setup();
    productTypeApi.remove.mockRejectedValue({
      response: { data: { error: 'Type still used by products.' } },
    });
    render(<AdminTypes />);
    await screen.findByText('Smartphone');

    await user.click(screen.getByText('delete-1'));
    await user.click(screen.getByText('Delete'));

    expect(await screen.findByText('Type still used by products.')).toBeInTheDocument();
    expect(screen.getByText('Smartphone')).toBeInTheDocument();
  });

  it('runs an analysis for a type', async () => {
    const user = userEvent.setup();
    adminAnalyticsApi.analyzeProductType.mockResolvedValue({
      data: { healthScore: 75, anomalies: [] },
    });
    render(<AdminTypes />);
    await screen.findByText('Smartphone');

    await user.click(screen.getByText('analyze-1'));
    expect(screen.getByText('AI Analysis — Smartphone')).toBeInTheDocument();
    await waitFor(() => expect(adminAnalyticsApi.analyzeProductType).toHaveBeenCalledWith(1));
  });
});
