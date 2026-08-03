import React, { useState, useEffect, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Package } from 'lucide-react';
import Navbar from '../../components/Navbar';
import { orderApi, reviewApi } from '../../services/cartService';
import { formatPrice as fmt } from '../../utils/format';
import './Orders.css';

const STATUS_STEPS = ['pending', 'confirmed', 'shipped', 'delivered'];

const STATUS_IDX = { pending: 0, confirmed: 1, shipped: 2, delivered: 3 };

function fmtDate(iso) {
  return new Date(iso).toLocaleDateString('fr-TN', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  });
}

function StatusBadge({ status }) {
  const { t } = useTranslation('orders');
  return <span className={`ord-badge ord-badge--${status}`}>{t(`status.${status}`, status)}</span>;
}

function Timeline({ status }) {
  const { t } = useTranslation('orders');
  if (status === 'cancelled') return null;
  const cur = STATUS_IDX[status] ?? 0;
  return (
    <div className="ord-timeline">
      {STATUS_STEPS.map((key, i) => (
        <React.Fragment key={key}>
          <div
            className={`ord-step ${i <= cur ? 'ord-step--on' : ''} ${i === cur ? 'ord-step--cur' : ''}`}
          >
            <div className="ord-dot">
              {i < cur && (
                <svg
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="3.5"
                  width="10"
                  height="10"
                >
                  <polyline points="20 6 9 17 4 12" />
                </svg>
              )}
              {i === cur && <div className="ord-dot-pulse" />}
            </div>
            <span className="ord-step-lbl">{t(`timeline.${key}`)}</span>
          </div>
          {i < STATUS_STEPS.length - 1 && (
            <div className={`ord-line ${i < cur ? 'ord-line--on' : ''}`} />
          )}
        </React.Fragment>
      ))}
    </div>
  );
}

/* ── Review modal ──────────────────────────────────────── */
function ReviewModal({ item, existing, onClose, onSaved }) {
  const { t } = useTranslation('orders');
  const [rating, setRating] = useState(existing?.rating ?? 80);
  const [comment, setComment] = useState(existing?.comment ?? '');
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');

  const handleSubmit = async e => {
    e.preventDefault();
    setSaving(true);
    setError('');
    try {
      await reviewApi.create(item.productId, { rating, comment });
      onSaved();
    } catch (err) {
      setError(err.response?.data?.error || t('review.errors.submitFailed'));
    } finally {
      setSaving(false);
    }
  };

  const ratingColor =
    rating >= 80 ? '#16a34a' : rating >= 60 ? '#d97706' : rating >= 40 ? '#ea580c' : '#dc2626';

  return (
    <div className="ord-modal-overlay" onClick={onClose}>
      <div className="ord-modal" onClick={e => e.stopPropagation()}>
        <div className="ord-modal-head">
          <h3 className="ord-modal-title">{t('review.title')}</h3>
          <button className="ord-modal-close" onClick={onClose}>
            ×
          </button>
        </div>
        <p className="ord-modal-product">{item.productName}</p>

        <form onSubmit={handleSubmit}>
          <div className="ord-rating-wrap">
            <div className="ord-rating-display" style={{ color: ratingColor }}>
              {rating}
              <span className="ord-rating-pct">%</span>
            </div>
            <input
              type="range"
              min="1"
              max="100"
              value={rating}
              onChange={e => setRating(Number(e.target.value))}
              className="ord-rating-slider"
              style={{ '--thumb-color': ratingColor, '--val': `${rating}%` }}
            />
            <div className="ord-rating-labels">
              <span>{t('review.ratingLabels.poor')}</span>
              <span>{t('review.ratingLabels.average')}</span>
              <span>{t('review.ratingLabels.good')}</span>
              <span>{t('review.ratingLabels.excellent')}</span>
            </div>
          </div>

          <div className="ord-comment-wrap">
            <label className="ord-comment-label">{t('review.commentLabel')}</label>
            <textarea
              className="ord-comment"
              rows={4}
              placeholder={t('review.commentPlaceholder')}
              value={comment}
              onChange={e => setComment(e.target.value)}
              maxLength={2000}
            />
          </div>

          {error && <div className="ord-modal-error">{error}</div>}

          <div className="ord-modal-foot">
            <button type="button" className="ord-modal-cancel" onClick={onClose}>
              {t('review.cancel')}
            </button>
            <button type="submit" className="ord-modal-submit" disabled={saving}>
              {saving ? t('review.submitting') : t('review.submit')}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

/* ── Order detail panel ────────────────────────────────── */
function OrderDetailPanel({ orderId, orderStatus, myReviewedIds, onReview }) {
  const { t } = useTranslation('orders');
  const [detail, setDetail] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    orderApi
      .getOrder(orderId)
      .then(res => setDetail(res.data))
      .catch(() => {})
      .finally(() => setLoading(false));
  }, [orderId]);

  if (loading) return <div className="ord-detail-load">{t('detail.loading')}</div>;
  if (!detail) return null;

  const addr = detail.shippingAddress;
  const delivered = orderStatus === 'delivered';

  return (
    <div className="ord-detail">
      <div className="ord-detail-items">
        {detail.items.map(item => (
          <div key={item.id} className="ord-detail-row">
            <span className="ord-detail-name">{item.productName}</span>
            <span className="ord-detail-qty">× {item.quantity}</span>
            <span className="ord-detail-sub">{fmt(item.subtotal)} TND</span>
            {delivered &&
              (myReviewedIds.has(item.productId) ? (
                <span className="ord-reviewed-badge">✓ {t('detail.reviewed')}</span>
              ) : (
                <button className="ord-review-btn" onClick={() => onReview(item)}>
                  {t('detail.rate')}
                </button>
              ))}
          </div>
        ))}
      </div>
      {addr && (
        <div className="ord-detail-addr">
          <strong>{t('detail.deliveredTo')}:</strong> {addr.street}, {addr.city}
          {addr.postalCode ? ` ${addr.postalCode}` : ''}, {addr.country}
        </div>
      )}
    </div>
  );
}

/* ── Cancel-order confirm modal ────────────────────────── */
function CancelOrderModal({ order, onClose, onConfirm, cancelling, error }) {
  const { t } = useTranslation('orders');
  return (
    <div className="ord-modal-overlay" onClick={() => !cancelling && onClose()}>
      <div className="ord-modal" onClick={e => e.stopPropagation()}>
        <div className="ord-modal-head">
          <h3 className="ord-modal-title">{t('cancelModal.title', { id: order.id })}</h3>
          <button className="ord-modal-close" onClick={onClose}>
            ×
          </button>
        </div>
        <p className="ord-modal-product">{t('cancelModal.warning')}</p>
        {error && <div className="ord-modal-error">{error}</div>}
        <div className="ord-modal-foot">
          <button
            type="button"
            className="ord-modal-cancel"
            onClick={onClose}
            disabled={cancelling}
          >
            {t('cancelModal.keepOrder')}
          </button>
          <button
            type="button"
            className="ord-modal-submit"
            onClick={onConfirm}
            disabled={cancelling}
          >
            {cancelling ? t('cancelModal.cancelling') : t('cancelModal.cancelOrder')}
          </button>
        </div>
      </div>
    </div>
  );
}

/* ── Main component ────────────────────────────────────── */
export default function Orders() {
  const { t } = useTranslation('orders');
  const navigate = useNavigate();
  const [orders, setOrders] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [expanded, setExpanded] = useState(null);
  const [reviewTarget, setReviewTarget] = useState(null);
  const [myReviewedIds, setMyReviewedIds] = useState(new Set());
  const [cancelTarget, setCancelTarget] = useState(null);
  const [cancelling, setCancelling] = useState(false);
  const [cancelError, setCancelError] = useState('');
  const limit = 10;

  const loadMyReviews = useCallback(() => {
    reviewApi
      .myReviews()
      .then(res => setMyReviewedIds(new Set((res.data || []).map(r => r.productId))))
      .catch(() => {});
  }, []);

  useEffect(() => {
    loadMyReviews();
  }, [loadMyReviews]);

  useEffect(() => {
    setLoading(true);
    orderApi
      .getOrders(page, limit)
      .then(res => {
        setOrders(res.data.data || []);
        setTotal(res.data.total || 0);
      })
      .catch(() => setError(t('errors.loadFailed')))
      .finally(() => setLoading(false));
  }, [page, t]);

  const totalPages = Math.ceil(total / limit);
  const toggle = id => setExpanded(p => (p === id ? null : id));

  const handleReviewSaved = () => {
    setReviewTarget(null);
    loadMyReviews();
  };

  const handleCancelConfirm = async () => {
    setCancelling(true);
    setCancelError('');
    try {
      await orderApi.cancel(cancelTarget.id);
      setOrders(prev =>
        prev.map(o => (o.id === cancelTarget.id ? { ...o, status: 'cancelled' } : o)),
      );
      setCancelTarget(null);
    } catch (err) {
      setCancelError(err.response?.data?.error || t('errors.cancelFailed'));
    } finally {
      setCancelling(false);
    }
  };

  return (
    <div className="ord-page">
      <Navbar />
      <main className="ord-main">
        <div className="ord-container">
          <div className="ord-header">
            <div>
              <h1 className="ord-title">{t('title')}</h1>
              <p className="ord-sub">{t('subtitle')}</p>
            </div>
            <button className="ord-btn-shop" onClick={() => navigate('/')}>
              {t('continueShopping')}
            </button>
          </div>

          {loading && (
            <div className="ord-spinner-wrap">
              <div className="ord-spinner" />
              <span>{t('loading')}</span>
            </div>
          )}

          {!loading && error && <div className="ord-error">{error}</div>}

          {!loading && !error && orders.length === 0 && (
            <div className="ord-empty">
              <div className="ord-empty-icon">
                <Package size={32} />
              </div>
              <h2>{t('empty.title')}</h2>
              <p>{t('empty.message')}</p>
              <button className="ord-btn-primary" onClick={() => navigate('/')}>
                {t('empty.shopNow')}
              </button>
            </div>
          )}

          {!loading && orders.length > 0 && (
            <>
              <div className="ord-list">
                {orders.map(order => (
                  <div key={order.id} className="ord-card">
                    <div className="ord-card-head">
                      <div className="ord-card-meta">
                        <span className="ord-card-id">
                          {t('card.orderNumber', { id: order.id })}
                        </span>
                        <span className="ord-card-date">{fmtDate(order.createdAt)}</span>
                      </div>
                      <StatusBadge status={order.status} />
                    </div>

                    {order.status === 'pending' && (
                      <div className="ord-card-actions">
                        <button
                          className="ord-btn-cancel"
                          onClick={() => {
                            setCancelError('');
                            setCancelTarget(order);
                          }}
                        >
                          {t('card.cancelOrder')}
                        </button>
                      </div>
                    )}

                    <div className="ord-card-timeline">
                      {order.status === 'cancelled' ? (
                        <p className="ord-cancelled">{t('card.cancelledNotice')}</p>
                      ) : (
                        <Timeline status={order.status} />
                      )}
                    </div>

                    <div className="ord-card-foot">
                      <div className="ord-card-totals">
                        <span className="ord-items-count">
                          {t('card.itemCount', { count: order.itemCount })}
                        </span>
                        <span className="ord-amount">{fmt(order.totalAmount)} TND</span>
                      </div>
                      <button className="ord-btn-detail" onClick={() => toggle(order.id)}>
                        {expanded === order.id
                          ? `${t('card.hideDetails')} ▲`
                          : `${t('card.viewDetails')} ▼`}
                      </button>
                    </div>

                    {expanded === order.id && (
                      <OrderDetailPanel
                        orderId={order.id}
                        orderStatus={order.status}
                        myReviewedIds={myReviewedIds}
                        onReview={item => setReviewTarget(item)}
                      />
                    )}
                  </div>
                ))}
              </div>

              {totalPages > 1 && (
                <div className="ord-pager">
                  <button
                    className="ord-page-btn"
                    disabled={page === 1}
                    onClick={() => setPage(p => p - 1)}
                  >
                    ← {t('pagination.previous')}
                  </button>
                  <span className="ord-page-info">
                    {t('pagination.pageOf', { page, totalPages })}
                  </span>
                  <button
                    className="ord-page-btn"
                    disabled={page === totalPages}
                    onClick={() => setPage(p => p + 1)}
                  >
                    {t('pagination.next')} →
                  </button>
                </div>
              )}
            </>
          )}
        </div>
      </main>

      {reviewTarget && (
        <ReviewModal
          item={reviewTarget}
          existing={null}
          onClose={() => setReviewTarget(null)}
          onSaved={handleReviewSaved}
        />
      )}

      {cancelTarget && (
        <CancelOrderModal
          order={cancelTarget}
          onClose={() => setCancelTarget(null)}
          onConfirm={handleCancelConfirm}
          cancelling={cancelling}
          error={cancelError}
        />
      )}
    </div>
  );
}
