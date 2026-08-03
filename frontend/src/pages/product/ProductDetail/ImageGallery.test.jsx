import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import '../../../i18n';
import ImageGallery from './ImageGallery';

describe('ImageGallery', () => {
  it('shows a placeholder icon and no thumbnails when there are no images', () => {
    render(<ImageGallery images={[]} productName="Widget" />);
    expect(screen.queryByRole('img')).not.toBeInTheDocument();
  });

  it('renders the first image as active with no nav arrows for a single image', () => {
    render(<ImageGallery images={['/img1.jpg']} productName="Widget" />);
    expect(screen.getByAltText('Widget')).toHaveAttribute('src', '/img1.jpg');
    expect(screen.queryByLabelText('Previous image')).not.toBeInTheDocument();
    expect(screen.queryByLabelText('Next image')).not.toBeInTheDocument();
  });

  it('shows nav arrows and thumbnails for multiple images, and switches on next/prev', async () => {
    const user = userEvent.setup();
    render(<ImageGallery images={['/img1.jpg', '/img2.jpg', '/img3.jpg']} productName="Widget" />);

    expect(screen.getByAltText('Widget')).toHaveAttribute('src', '/img1.jpg');

    await user.click(screen.getByLabelText('Next image'));
    expect(screen.getByAltText('Widget')).toHaveAttribute('src', '/img2.jpg');

    await user.click(screen.getByLabelText('Previous image'));
    expect(screen.getByAltText('Widget')).toHaveAttribute('src', '/img1.jpg');
  });

  it('wraps around from the last image to the first when clicking next', async () => {
    const user = userEvent.setup();
    render(<ImageGallery images={['/img1.jpg', '/img2.jpg']} productName="Widget" />);

    await user.click(screen.getByLabelText('Next image'));
    expect(screen.getByAltText('Widget')).toHaveAttribute('src', '/img2.jpg');
    await user.click(screen.getByLabelText('Next image'));
    expect(screen.getByAltText('Widget')).toHaveAttribute('src', '/img1.jpg');
  });

  it('switches the active image when a thumbnail is clicked', async () => {
    const user = userEvent.setup();
    render(<ImageGallery images={['/img1.jpg', '/img2.jpg']} productName="Widget" />);

    const thumbnails = screen.getAllByRole('button', { name: /Widget \d/ });
    await user.click(thumbnails[1]);
    expect(screen.getByAltText('Widget')).toHaveAttribute('src', '/img2.jpg');
  });

  it('opens a lightbox with the active image when the main image is clicked, and closes it via the close button', async () => {
    const user = userEvent.setup();
    const { container } = render(<ImageGallery images={['/img1.jpg']} productName="Widget" />);

    await user.click(screen.getByAltText('Widget'));
    expect(screen.getAllByAltText('Widget')).toHaveLength(2); // main + lightbox copy
    expect(container.querySelector('.pd-lightbox-overlay')).toBeInTheDocument();

    await user.click(container.querySelector('.pd-lightbox-close'));
    expect(container.querySelector('.pd-lightbox-overlay')).not.toBeInTheDocument();
  });
});
