import React, { useState, useEffect, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Package, ZoomIn, X } from 'lucide-react';
import { productApi, reviewApi, interactionApi, guestEventApi, recommendationApi } from '../../services/cartService';
import { useCart } from '../../context/CartContext';
import { useFavorites } from '../../context/FavoriteContext';
import { isAuthenticated } from '../../services/authService';
import { formatPrice } from '../../utils/format';
import ProductCard from '../../components/ProductCard';
import { HeartIcon } from '../../components/ui';
import './ProductDetail.css';

function StockBadge({ stock }) {
  const { t } = useTranslation('productDetail');
  if (stock === 0)  return <span className="pd-stock pd-stock--out">{t('stock.out')}</span>;
  if (stock <= 10)  return <span className="pd-stock pd-stock--low">{t('stock.low', { count: stock })}</span>;
  return              <span className="pd-stock pd-stock--ok">{t('stock.ok')}</span>;
}

export default function ProductDetail() {
  const { t, i18n } = useTranslation('productDetail');
  const { id } = useParams();
  const navigate = useNavigate();
  const { addToCart, cartCount } = useCart();

  const { isFavorite, toggleFavorite } = useFavorites();

  const [product,    setProduct]    = useState(null);
  const [loading,    setLoading]    = useState(true);
  const [notFound,   setNotFound]   = useState(false);
  const [qty,        setQty]        = useState(1);
  const [added,      setAdded]      = useState(false);
  const [favLoading, setFavLoading] = useState(false);
  const [lightboxOpen, setLightboxOpen] = useState(false);

  const [reviews,     setReviews]     = useState([]);
  const [reviewsPage, setReviewsPage] = useState(1);
  const [reviewTotal, setReviewTotal] = useState(0);
  const [avgRating,   setAvgRating]   = useState(0);
  const [reviewsLoad, setReviewsLoad] = useState(false);
  const reviewLimit = 5;

  const [similarProducts,      setSimilarProducts]      = useState([]);
  const [complementaryProducts, setComplementaryProducts] = useState([]);

  const [activity, setActivity] = useState({ viewingNow: 0, inCarts: 0 });

  const loadActivity = useCallback(() => {
    productApi.activity(id)
      .then(res => setActivity({ viewingNow: res.data.viewingNow ?? 0, inCarts: res.data.inCarts ?? 0 }))
      .catch(() => {});
  }, [id]);

  useEffect(() => {
    setLoading(true);
    setNotFound(false);
    setProduct(null);
    setSimilarProducts([]);
    setComplementaryProducts([]);
    setActivity({ viewingNow: 0, inCarts: 0 });

    productApi.get(id)
      .then(res => setProduct(res.data))
      .catch(err => { if (err.response?.status === 404) setNotFound(true); })
      .finally(() => setLoading(false));

    // Feeds the session-based recommendation engine — logged-in views go
    // through the authenticated Interaction log, guest views are
    // attributed to this browser's session id instead. The first activity
    // read is chained after this resolves (rather than fired in parallel)
    // so it already counts your own view — otherwise it races the tracking
    // write and undercounts by one until the next poll.
    const trackView = isAuthenticated() ? interactionApi.track(id, 'view') : guestEventApi.track(id, 'view');
    trackView.catch(() => {}).finally(loadActivity);

    recommendationApi.forProduct(id)
      .then(res => {
        setSimilarProducts(res.data.similar ?? []);
        setComplementaryProducts(res.data.complementary ?? []);
      })
      .catch(() => {});
  }, [id, loadActivity]);

  // Keeps "viewing now" / "in carts" live without needing a manual refresh.
  useEffect(() => {
    const interval = setInterval(loadActivity, 8000);
    return () => clearInterval(interval);
  }, [loadActivity]);

  const loadReviews = useCallback((pg = 1) => {
    setReviewsLoad(true);
    reviewApi.list(id, pg, reviewLimit)
      .then(res => {
        setAvgRating(res.data.averageRating ?? 0);
        setReviews(res.data.reviews?.data ?? []);
        setReviewTotal(res.data.reviews?.total ?? 0);
        setReviewsPage(pg);
      })
      .catch(() => {})
      .finally(() => setReviewsLoad(false));
  }, [id]);

  useEffect(() => { loadReviews(1); }, [loadReviews]);

  const handleAdd = async () => {
    if (!product || product.stock === 0) return;
    await addToCart(product, qty);
    setAdded(true);
    loadActivity(); // reflect your own addition in "in N carts" right away, not on the next poll
    setTimeout(() => setAdded(false), 2000);
  };

  const handleFav = async () => {
    if (!isAuthenticated()) {
      navigate('/login', { state: { from: `/product/${id}` } });
      return;
    }
    setFavLoading(true);
    try { await toggleFavorite(product.id); } finally { setFavLoading(false); }
  };

  if (loading) {
    return (
      <div className="pd-loading">
        <div className="pd-spinner" />
      </div>
    );
  }

  if (notFound || !product) {
    return (
      <div className="pd-not-found">
        <h2>{t('notFound.title')}</h2>
        <button onClick={() => navigate('/')}>{t('notFound.backButton')}</button>
      </div>
    );
  }

  const firstImage = product.images?.[0] || null;
  const catParent  = product.category?.parent;

  return (
    <div className="pd-page">

      {/* Navbar */}
      <nav className="pd-nav">
        <div className="pd-nav-inner">
          <button className="pd-back" onClick={() => navigate(-1)}>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" width="16" height="16">
              <polyline points="15 18 9 12 15 6"/>
            </svg>
            {t('nav.back')}
          </button>
          <div className="pd-logo" onClick={() => navigate('/')}>
            <span className="pd-logo-icon">S</span>
            <span className="pd-logo-text">SmartCart</span>
          </div>
          <button className="pd-cart-btn" onClick={() => navigate('/cart')}>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" width="20" height="20">
              <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
              <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
            </svg>
            {cartCount > 0 && <span className="pd-cart-badge">{cartCount}</span>}
          </button>
        </div>
      </nav>

      <main className="pd-main">
        <div className="pd-container">

          {/* Breadcrumb */}
          <div className="pd-breadcrumb">
            <button onClick={() => navigate('/')}>{t('breadcrumb.home')}</button>
            {catParent && (
              <>
                <span>›</span>
                <button onClick={() => navigate(`/?cat=${catParent.id}`)}>{catParent.name}</button>
              </>
            )}
            <span>›</span>
            <button onClick={() => navigate(`/?cat=${product.category?.id}`)}>{product.category?.name}</button>
            <span>›</span>
            <span className="pd-breadcrumb-current">{product.name}</span>
          </div>

          {/* Two-column layout */}
          <div className="pd-layout">

            {/* Image */}
            <div className="pd-image-panel">
              <div
                className={`pd-image${firstImage ? ' pd-image--clickable' : ''}`}
                style={firstImage ? { background: '#f8fafc' } : { background: 'linear-gradient(135deg,#185FA5,#042C53)' }}
                onClick={() => firstImage && setLightboxOpen(true)}
              >
                {firstImage ? (
                  <>
                    <img src={firstImage} alt={product.name} className="pd-product-img" />
                    <span className="pd-image-zoom-hint"><ZoomIn size={16} /></span>
                  </>
                ) : (
                  <>
                    <span className="pd-image-emoji"><Package size={48} /></span>
                    <div className="pd-image-shine" />
                  </>
                )}
              </div>
            </div>

            {/* Info */}
            <div className="pd-info">
              <div className="pd-badge-row">
                {product.promotion && <span className="pd-tag pd-tag--promo">{t('badges.promotion')}</span>}
                {product.isNew && <span className="pd-tag pd-tag--new">{t('badges.new')}</span>}
                <span className="pd-category-badge">{product.category?.name}</span>
                {product.brand && (
                  <span className="pd-brand-badge">
                    {product.brand.image && <img src={product.brand.image} alt={product.brand.name} className="pd-brand-logo" />}
                    {product.brand.name}
                  </span>
                )}
              </div>
              <div className="pd-name-row">
                <h1 className="pd-name">{product.name}</h1>
                <button
                  className={`pd-fav-btn ${isFavorite(product.id) ? 'pd-fav-btn--active' : ''}`}
                  onClick={handleFav}
                  disabled={favLoading}
                  title={isFavorite(product.id) ? t('favorite.remove') : t('favorite.add')}
                >
                  <HeartIcon size={22} filled={isFavorite(product.id)} />
                  {isFavorite(product.id) ? t('favorite.saved') : t('favorite.save')}
                </button>
              </div>

              <div className="pd-price-row">
                {product.promotion ? (
                  <>
                    <span className="pd-price pd-price--new">{formatPrice(product.promotion.newPrice)}</span>
                    <span className="pd-currency">TND</span>
                    <span className="pd-price-old">{formatPrice(product.promotion.oldPrice)} TND</span>
                  </>
                ) : (
                  <>
                    <span className="pd-price">{formatPrice(product.price)}</span>
                    <span className="pd-currency">TND</span>
                  </>
                )}
              </div>

              {product.promotion && (
                <div className="pd-promo-meta">
                  <span className="pd-promo-pct">{t('promo.off', { percentage: Math.round(product.promotion.percentage) })}</span>
                  {product.promotion.endDate ? (
                    <span className="pd-promo-end">
                      {t('promo.ends', { date: new Date(product.promotion.endDate).toLocaleDateString(i18n.language === 'fr' ? 'fr-FR' : 'en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) })}
                    </span>
                  ) : (
                    <span className="pd-promo-end">{t('promo.limitedTime')}</span>
                  )}
                </div>
              )}

              {product.averageRating > 0 && (
                <div className="pd-rating-row">
                  <span className="pd-rating-star">&#9733;</span>
                  <span className="pd-rating-val">{product.averageRating}</span>
                  <span className="pd-rating-count">{t('rating.reviewCount', { count: product.reviewCount })}</span>
                </div>
              )}

              <StockBadge stock={product.stock} />

              {(activity.viewingNow > 0 || activity.inCarts > 0) && (
                <div className="pd-activity-row">
                  {activity.viewingNow > 0 && (
                    <span className="pd-activity-chip">
                      <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" strokeWidth="2"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
                      {t('activity.viewingNow', { count: activity.viewingNow })}
                    </span>
                  )}
                  {activity.inCarts > 0 && (
                    <span className="pd-activity-chip">
                      <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                      {t('activity.inCarts', { count: activity.inCarts })}
                    </span>
                  )}
                </div>
              )}

              {product.description && (
                <p className="pd-description">{product.description}</p>
              )}

              <div className="pd-divider" />

              {product.stock > 0 && (
                <div className="pd-actions">
                  <div className="pd-qty-wrap">
                    <span className="pd-qty-label">{t('actions.quantity')}</span>
                    <div className="pd-qty">
                      <button className="pd-qty-btn" onClick={() => setQty(q => Math.max(1, q - 1))} disabled={qty <= 1}>−</button>
                      <span className="pd-qty-val">{qty}</span>
                      <button className="pd-qty-btn" onClick={() => setQty(q => Math.min(product.stock, q + 1))} disabled={qty >= product.stock}>+</button>
                    </div>
                  </div>

                  <button className={`pd-btn-add ${added ? 'pd-btn-add--done' : ''}`} onClick={handleAdd}>
                    {added ? (
                      <>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" width="18" height="18"><polyline points="20 6 9 17 4 12"/></svg>
                        {t('actions.addedToCart')}
                      </>
                    ) : (
                      <>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" width="18" height="18">
                          <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                          <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                        </svg>
                        {t('actions.addToCart')}
                      </>
                    )}
                  </button>
                </div>
              )}

              <div className="pd-meta">
                {product.brand && (
                  <div className="pd-meta-row">
                    <span className="pd-meta-key">{t('meta.brand')}</span>
                    <span className="pd-meta-val pd-meta-val--brand">
                      {product.brand.image && <img src={product.brand.image} alt={product.brand.name} className="pd-brand-logo pd-brand-logo--sm" />}
                      {product.brand.name}
                    </span>
                  </div>
                )}
                <div className="pd-meta-row">
                  <span className="pd-meta-key">{t('meta.category')}</span>
                  <span className="pd-meta-val">
                    {catParent ? `${catParent.name} › ` : ''}{product.category?.name}
                  </span>
                </div>
                <div className="pd-meta-row">
                  <span className="pd-meta-key">{t('meta.availability')}</span>
                  <span className="pd-meta-val">{product.stock > 0 ? t('meta.unitsAvailable', { count: product.stock }) : t('meta.unavailable')}</span>
                </div>
              </div>
            </div>
          </div>

          {/* Technical sheet */}
          {product.specifications?.length > 0 && (
            <div className="pd-specs">
              <h2 className="pd-specs-title">{t('specs.title')}</h2>
              <div className="pd-specs-grid">
                {product.specifications.map(spec => (
                  <div className="pd-specs-row" key={spec.slug}>
                    <span className="pd-specs-key">{spec.name}</span>
                    <span className="pd-specs-val">{spec.display}</span>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Frequently bought together */}
          {complementaryProducts.length > 0 && (
            <div className="pd-related">
              <h2 className="pd-related-title">{t('relatedProducts.frequentlyBoughtTogether')}</h2>
              <div className="pd-related-grid">
                {complementaryProducts.map(p => <ProductCard key={p.id} product={p} />)}
              </div>
            </div>
          )}

          {/* Similar products */}
          {similarProducts.length > 0 && (
            <div className="pd-related">
              <h2 className="pd-related-title">{t('relatedProducts.youMayAlsoLike')}</h2>
              <div className="pd-related-grid">
                {similarProducts.map(p => <ProductCard key={p.id} product={p} />)}
              </div>
            </div>
          )}

          {/* Reviews section */}
          <div className="pd-reviews">
            <div className="pd-reviews-header">
              <h2 className="pd-reviews-title">{t('reviews.title')}</h2>
              {reviewTotal > 0 && (
                <div className="pd-reviews-summary">
                  <span className="pd-reviews-avg">{avgRating}%</span>
                  <span className="pd-reviews-count">
                    {t('reviews.count', { count: reviewTotal })}
                  </span>
                </div>
              )}
            </div>

            {reviewsLoad && (
              <div className="pd-reviews-loading">
                <div className="pd-spinner" style={{ width: 28, height: 28 }} />
              </div>
            )}

            {!reviewsLoad && reviews.length === 0 && (
              <div className="pd-reviews-empty">
                {t('reviews.empty')}
              </div>
            )}

            {!reviewsLoad && reviews.length > 0 && (
              <div className="pd-reviews-list">
                {reviews.map(r => {
                  const pct = r.rating;
                  const barColor =
                    pct >= 80 ? '#16a34a' :
                    pct >= 60 ? '#d97706' :
                    pct >= 40 ? '#ea580c' : '#dc2626';
                  return (
                    <div key={r.id} className="pd-review-card">
                      <div className="pd-review-head">
                        <span className="pd-review-author">{r.authorName}</span>
                        <span className="pd-review-date">
                          {new Date(r.createdAt).toLocaleDateString('fr-TN', {
                            day: '2-digit', month: 'short', year: 'numeric',
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

            {reviewTotal > reviewLimit && (
              <div className="pd-reviews-pager">
                <button
                  className="pd-reviews-page-btn"
                  disabled={reviewsPage === 1}
                  onClick={() => loadReviews(reviewsPage - 1)}
                >
                  {t('reviews.previous')}
                </button>
                <span className="pd-reviews-page-info">
                  {t('reviews.pageInfo', { current: reviewsPage, total: Math.ceil(reviewTotal / reviewLimit) })}
                </span>
                <button
                  className="pd-reviews-page-btn"
                  disabled={reviewsPage >= Math.ceil(reviewTotal / reviewLimit)}
                  onClick={() => loadReviews(reviewsPage + 1)}
                >
                  {t('reviews.next')}
                </button>
              </div>
            )}
          </div>

        </div>
      </main>

      {lightboxOpen && firstImage && (
        <div className="pd-lightbox-overlay" onClick={() => setLightboxOpen(false)}>
          <button className="pd-lightbox-close" onClick={() => setLightboxOpen(false)}>
            <X size={22} />
          </button>
          <img src={firstImage} alt={product.name} className="pd-lightbox-img" onClick={e => e.stopPropagation()} />
        </div>
      )}
    </div>
  );
}
