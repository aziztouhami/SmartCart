import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import ImageUpload from './ImageUpload';

function makeFile(name = 'photo.png', type = 'image/png') {
  return new File(['fake-bytes'], name, { type });
}

describe('ImageUpload', () => {
  it('shows the drop zone when there is no preview', () => {
    render(<ImageUpload preview={null} onFile={jest.fn()} onClear={jest.fn()} />);
    expect(screen.getByText('Click or drag an image here')).toBeInTheDocument();
    expect(screen.queryByAltText('Preview')).not.toBeInTheDocument();
  });

  it('shows the preview image and remove button when a preview is given', () => {
    render(<ImageUpload preview="/uploads/photo.png" onFile={jest.fn()} onClear={jest.fn()} />);
    expect(screen.getByAltText('Preview')).toHaveAttribute('src', '/uploads/photo.png');
    expect(screen.getByText('✕ Remove')).toBeInTheDocument();
  });

  it('calls onClear when the remove button is clicked', async () => {
    const user = userEvent.setup();
    const onClear = jest.fn();
    render(<ImageUpload preview="/uploads/photo.png" onFile={jest.fn()} onClear={onClear} />);

    await user.click(screen.getByText('✕ Remove'));
    expect(onClear).toHaveBeenCalled();
  });

  it('calls onFile when a file is selected via the hidden input', async () => {
    const user = userEvent.setup();
    const onFile = jest.fn();
    const { container } = render(
      <ImageUpload preview={null} onFile={onFile} onClear={jest.fn()} />,
    );

    const input = container.querySelector('input[type="file"]');
    const file = makeFile();
    await user.upload(input, file);

    expect(onFile).toHaveBeenCalledWith(file);
  });

  it('accepts an image file dropped onto the drop zone', () => {
    const onFile = jest.fn();
    const { container } = render(
      <ImageUpload preview={null} onFile={onFile} onClear={jest.fn()} />,
    );
    const dropZone = container.querySelector('.imu-zone');
    const file = makeFile();

    const dropEvent = new Event('drop', { bubbles: true, cancelable: true });
    Object.defineProperty(dropEvent, 'dataTransfer', { value: { files: [file] } });
    dropZone.dispatchEvent(dropEvent);

    expect(onFile).toHaveBeenCalledWith(file);
  });

  it('ignores a dropped non-image file', () => {
    const onFile = jest.fn();
    const { container } = render(
      <ImageUpload preview={null} onFile={onFile} onClear={jest.fn()} />,
    );
    const dropZone = container.querySelector('.imu-zone');
    const file = makeFile('doc.pdf', 'application/pdf');

    const dropEvent = new Event('drop', { bubbles: true, cancelable: true });
    Object.defineProperty(dropEvent, 'dataTransfer', { value: { files: [file] } });
    dropZone.dispatchEvent(dropEvent);

    expect(onFile).not.toHaveBeenCalled();
  });
});
