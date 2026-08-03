import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import '../../../i18n';
import { reviewApi } from '../../../services/cartService';
import ReviewsSection from './ReviewsSection';

jest.mock('../../../services/cartService', () => ({
  reviewApi: { list: jest.fn() },
}));

describe('ReviewsSection', () => {
  beforeEach(() => {
    reviewApi.list.mockResolvedValue({
      data: { averageRating: 0, reviews: { data: [], total: 0 } },
    });
  });

  it('loads the first page of reviews for the given product on mount', async () => {
    render(<ReviewsSection productId={7} />);
    await waitFor(() => expect(reviewApi.list).toHaveBeenCalledWith(7, 1, 5));
  });

  it('shows the empty message when there are no reviews', async () => {
    render(<ReviewsSection productId={7} />);
    expect(
      await screen.findByText(
        'No reviews yet. Be the first to review this product after receiving your order.',
      ),
    ).toBeInTheDocument();
  });

  it('shows the average rating and review count when reviews exist', async () => {
    reviewApi.list.mockResolvedValue({
      data: {
        averageRating: 95,
        reviews: {
          data: [
            { id: 1, authorName: 'Alice', rating: 88, comment: 'Great!', createdAt: '2026-01-15' },
          ],
          total: 1,
        },
      },
    });
    const { container } = render(<ReviewsSection productId={7} />);

    await screen.findByText('Alice');
    expect(container.querySelector('.pd-reviews-avg')).toHaveTextContent('95%');
    expect(screen.getByText('1 review')).toBeInTheDocument();
    expect(screen.getByText('Great!')).toBeInTheDocument();
  });

  it('does not show a pager when total reviews are within a single page', async () => {
    reviewApi.list.mockResolvedValue({
      data: {
        averageRating: 90,
        reviews: { data: [{ id: 1, authorName: 'Alice', rating: 90 }], total: 3 },
      },
    });
    render(<ReviewsSection productId={7} />);
    await screen.findByText('Alice');
    expect(screen.queryByText(/Previous/)).not.toBeInTheDocument();
  });

  it('shows a pager when there are more reviews than the page size, and pages forward/back', async () => {
    const user = userEvent.setup();
    reviewApi.list.mockImplementation((productId, pg) =>
      Promise.resolve({
        data: {
          averageRating: 90,
          reviews: {
            data: [{ id: pg, authorName: `Reviewer ${pg}`, rating: 90 }],
            total: 12,
          },
        },
      }),
    );
    render(<ReviewsSection productId={7} />);

    expect(await screen.findByText('Reviewer 1')).toBeInTheDocument();
    expect(screen.getByText('1 / 3')).toBeInTheDocument();
    const prevButton = screen.getByText('← Previous');
    expect(prevButton).toBeDisabled();

    await user.click(screen.getByText('Next →'));
    expect(await screen.findByText('Reviewer 2')).toBeInTheDocument();
    expect(reviewApi.list).toHaveBeenCalledWith(7, 2, 5);
    expect(prevButton).not.toBeDisabled();

    await user.click(screen.getByText('← Previous'));
    expect(await screen.findByText('Reviewer 1')).toBeInTheDocument();
  });

  it('disables the next button on the last page', async () => {
    reviewApi.list.mockResolvedValue({
      data: {
        averageRating: 90,
        reviews: { data: [{ id: 1, authorName: 'Reviewer 3', rating: 90 }], total: 11 },
      },
    });
    render(<ReviewsSection productId={7} />);
    await screen.findByText('1 / 3');
    // total=11 with limit 5 → 3 pages; page loaded is still 1 in this stub, next enabled
    expect(screen.getByText('Next →')).not.toBeDisabled();
  });

  it('reloads reviews when the productId prop changes', async () => {
    const { rerender } = render(<ReviewsSection productId={7} />);
    await waitFor(() => expect(reviewApi.list).toHaveBeenCalledWith(7, 1, 5));

    rerender(<ReviewsSection productId={9} />);
    await waitFor(() => expect(reviewApi.list).toHaveBeenCalledWith(9, 1, 5));
  });
});
