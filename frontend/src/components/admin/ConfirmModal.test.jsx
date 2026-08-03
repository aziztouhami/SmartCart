import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import ConfirmModal from './ConfirmModal';

describe('ConfirmModal', () => {
  it('renders the title and message', () => {
    render(
      <ConfirmModal
        title="Delete item?"
        message="This cannot be undone."
        onConfirm={jest.fn()}
        onCancel={jest.fn()}
      />,
    );
    expect(screen.getByText('Delete item?')).toBeInTheDocument();
    expect(screen.getByText('This cannot be undone.')).toBeInTheDocument();
  });

  it('defaults to a "Delete" confirm label styled as danger', () => {
    render(<ConfirmModal title="t" message="m" onConfirm={jest.fn()} onCancel={jest.fn()} />);
    const confirmButton = screen.getByText('Delete');
    expect(confirmButton).toHaveClass('ac-btn-danger');
  });

  it('uses a custom confirm label and drops the danger class when danger=false', () => {
    render(
      <ConfirmModal
        title="t"
        message="m"
        confirmLabel="Archive"
        danger={false}
        onConfirm={jest.fn()}
        onCancel={jest.fn()}
      />,
    );
    const confirmButton = screen.getByText('Archive');
    expect(confirmButton).not.toHaveClass('ac-btn-danger');
  });

  it('calls onConfirm when the confirm button is clicked', async () => {
    const user = userEvent.setup();
    const onConfirm = jest.fn();
    render(<ConfirmModal title="t" message="m" onConfirm={onConfirm} onCancel={jest.fn()} />);
    await user.click(screen.getByText('Delete'));
    expect(onConfirm).toHaveBeenCalled();
  });

  it('calls onCancel via the Cancel button, the close button, and the overlay', async () => {
    const user = userEvent.setup();
    const onCancel = jest.fn();
    const { container } = render(
      <ConfirmModal title="t" message="m" onConfirm={jest.fn()} onCancel={onCancel} />,
    );

    await user.click(screen.getByText('Cancel'));
    await user.click(screen.getByText('✕'));
    await user.click(container.querySelector('.adm-overlay'));

    expect(onCancel).toHaveBeenCalledTimes(3);
  });

  it('does not call onCancel when clicking inside the modal body', async () => {
    const user = userEvent.setup();
    const onCancel = jest.fn();
    const { container } = render(
      <ConfirmModal title="t" message="m" onConfirm={jest.fn()} onCancel={onCancel} />,
    );
    await user.click(container.querySelector('.adm-modal'));
    expect(onCancel).not.toHaveBeenCalled();
  });
});
