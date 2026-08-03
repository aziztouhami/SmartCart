import React, { useState, useCallback, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { reviewApi } from '../../../services/cartService';
import './ReviewsSection.css';

const REVIEW_LIMIT = 5;

export default function ReviewsSection({ productId }) {
  const { t } = useTranslation('productDetail');

  const [reviews, setReviews] = useState([]);
  const [reviewsPage, setReviewsPage] = useState(1);
  const [reviewTotal, setReviewTotal] = useState(0);
  const [avgRating, setAvgRating] = useState(0);
  const [reviewsLoad, setReviewsLoad] = useState(false);

  const loadReviews = useCallback(
    (pg = 1) => {
      setReviewsLoad(true);
      reviewApi
        .list(productId, pg, REVIEW_LIMIT)
        .then(res => {
          setAvgRating(res.data.averageRating ?? 0);
          setReviews(res.data.reviews?.data ?? []);
          setReviewTotal(res.data.reviews?.total ?? 0);
          setReviewsPage(pg);
        })
        .catch(() => {})
        .finally(() => setReviewsLoad(false));
    },
    [productId],
  );

  useEffect(() => {
    loadReviews(1);
  }, [loadReviews]);

  return (
    <div className="pd-reviews">
      <div className="pd-reviews-header">
        <h2 className="pd-reviews-title">{t('reviews.title')}</h2>
        {reviewTotal > 0 && (
          <div className="pd-reviews-summary">
            <span className="pd-reviews-avg">{avgRating}%</span>
            <span className="pd-reviews-count">{t('reviews.count', { count: reviewTotal })}</span>
          </div>
        )}
      </div>

      {reviewsLoad && (
        <div className="pd-reviews-loading">
          <div className="pd-spinner" style={{ width: 28, height: 28 }} />
        </div>
      )}

      {!reviewsLoad && reviews.length === 0 && (
        <div className="pd-reviews-empty">{t('reviews.empty')}</div>
      )}

      {!reviewsLoad && reviews.length > 0 && (
        <div className="pd-reviews-list">
          {reviews.map(r => {
            const pct = r.rating;
            const barColor =
              pct >= 80 ? '#16a34a' : pct >= 60 ? '#d97706' : pct >= 40 ? '#ea580c' : '#dc2626';
            return (
              <div key={r.id} className="pd-review-card">
                <div className="pd-review-head">
                  <span className="pd-review-author">{r.authorName}</span>
                  <span className="pd-review-date">
                    {new Date(r.createdAt).toLocaleDateString('fr-TN', {
                      day: '2-digit',
                      month: 'short',
                      year: 'numeric',
                    })}
                  </span>
                </div>
                <div className="pd-review-score-row">
                  <div className="pd-review-bar-wrap">
                    <div
                      className="pd-review-bar"
                      style={{ width: `${pct}%`, background: barColor }}
                    />
                  </div>
                  <span className="pd-review-score" style={{ color: barColor }}>
                    {pct}%
                  </span>
                </div>
                {r.comment && <p className="pd-review-comment">{r.comment}</p>}
              </div>
            );
          })}
        </div>
      )}

      {reviewTotal > REVIEW_LIMIT && (
        <div className="pd-reviews-pager">
          <button
            className="pd-reviews-page-btn"
            disabled={reviewsPage === 1}
            onClick={() => loadReviews(reviewsPage - 1)}
          >
            {t('reviews.previous')}
          </button>
          <span className="pd-reviews-page-info">
            {t('reviews.pageInfo', {
              current: reviewsPage,
              total: Math.ceil(reviewTotal / REVIEW_LIMIT),
            })}
          </span>
          <button
            className="pd-reviews-page-btn"
            disabled={reviewsPage >= Math.ceil(reviewTotal / REVIEW_LIMIT)}
            onClick={() => loadReviews(reviewsPage + 1)}
          >
            {t('reviews.next')}
          </button>
        </div>
      )}
    </div>
  );
}
