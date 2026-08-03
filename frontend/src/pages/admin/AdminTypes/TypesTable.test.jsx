import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import TypesTable from './TypesTable';

const type = {
  id: 1,
  name: 'Smartphone',
  slug: 'smartphone',
  attributes: [
    { id: 10, name: 'Color', unit: '', required: true },
    { id: 11, name: 'RAM', unit: 'GB', required: false },
  ],
};

describe('TypesTable', () => {
  it('shows a loading row', () => {
    render(
      <TypesTable
        types={[]}
        loading
        onEdit={jest.fn()}
        onDeleteRequest={jest.fn()}
        onAnalyze={jest.fn()}
      />,
    );
    expect(screen.getByText('Loading types…')).toBeInTheDocument();
  });

  it('shows an empty state', () => {
    render(
      <TypesTable
        types={[]}
        loading={false}
        onEdit={jest.fn()}
        onDeleteRequest={jest.fn()}
        onAnalyze={jest.fn()}
      />,
    );
    expect(
      screen.getByText(
        'No product types yet. Click "Add Type" to create one (e.g. Smartphone, Smart Watch).',
      ),
    ).toBeInTheDocument();
  });

  it('renders a type row with its slug and feature chips, marking required ones', () => {
    render(
      <TypesTable
        types={[type]}
        loading={false}
        onEdit={jest.fn()}
        onDeleteRequest={jest.fn()}
        onAnalyze={jest.fn()}
      />,
    );

    expect(screen.getByText('Smartphone')).toBeInTheDocument();
    expect(screen.getByText('smartphone')).toBeInTheDocument();
    expect(screen.getByText('Color')).toHaveClass('aty-chip--required');
    expect(screen.getByText('RAM (GB)')).not.toHaveClass('aty-chip--required');
  });

  it('shows a placeholder chip when a type has no features', () => {
    render(
      <TypesTable
        types={[{ ...type, attributes: [] }]}
        loading={false}
        onEdit={jest.fn()}
        onDeleteRequest={jest.fn()}
        onAnalyze={jest.fn()}
      />,
    );
    expect(screen.getByText('No features defined')).toBeInTheDocument();
  });

  it('calls onEdit, onDeleteRequest and onAnalyze', async () => {
    const user = userEvent.setup();
    const onEdit = jest.fn();
    const onDeleteRequest = jest.fn();
    const onAnalyze = jest.fn();
    render(
      <TypesTable
        types={[type]}
        loading={false}
        onEdit={onEdit}
        onDeleteRequest={onDeleteRequest}
        onAnalyze={onAnalyze}
      />,
    );

    await user.click(screen.getByText('Edit'));
    expect(onEdit).toHaveBeenCalledWith(type);

    await user.click(screen.getByText('Delete'));
    expect(onDeleteRequest).toHaveBeenCalledWith(1);

    await user.click(screen.getByText('Analyze'));
    expect(onAnalyze).toHaveBeenCalledWith(type);
  });
});
