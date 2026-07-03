import React from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import Navbar from '../../components/Navbar';
import { useFavorites } from '../../context/FavoriteContext';
import { useCart } from '../../context/CartContext';
import { formatPrice as fmt } from '../../utils/format';
import './Favorites.css';

function HeartFilled() {
  return (
    <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" stroke="currentColor" strokeWidth="2">
      <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
    </svg>
  );
}

export default function Favorites() {
  const { t } = useTranslation('favorites');
  const navigate = useNavigate();
  const { items, loading, toggleFavorite } = useFavorites();
  const { addToCart } = useCart();

  return (
    <div className="fv-page">
      <Navbar />

      <main className="fv-main">
        <div className="fv-container">

          <div className="fv-header">
            <div className="fv-header-left">
              <h1 className="fv-title">{t('title')}</h1>
              {!loading && items.length > 0 && (
                <span className="fv-count">{t('savedCount', { count: items.length })}</span>
              )}
            </div>
          </div>

          {loading && (
            <div className="fv-loading">
              <div className="fv-spinner" />
            </div>
          )}

          {!loading && items.length === 0 && (
            <div className="fv-empty">
              <div className="fv-empty-icon">
                <svg viewBox="0 0 24 24" width="64" height="64" fill="none" stroke="#cbd5e1" strokeWidth="1.5">
                  <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                </svg>
              </div>
              <h2 className="fv-empty-title">{t('empty.title')}</h2>
              <p className="fv-empty-sub">{t('empty.message')}</p>
              <button className="fv-btn-browse" onClick={() => navigate('/')}>{t('empty.browseProducts')}</button>
            </div>
          )}

          {!loading && items.length > 0 && (
            <div className="fv-grid">
              {items.map(fav => (
                <div key={fav.id} className="fv-card">
                  {/* Image */}
                  <div
                    className="fv-card-img"
                    onClick={() => navigate(`/product/${fav.productId}`)}
                    style={fav.productImage ? { background: '#f8fafc' } : undefined}
                  >
                    {fav.productImage
                      ? <img src={fav.productImage} alt={fav.productName} />
                      : <span className="fv-card-initial">{fav.productName?.[0]?.toUpperCase()}</span>
                    }
                    {/* Remove button */}
                    <button
                      className="fv-card-remove"
                      onClick={e => { e.stopPropagation(); toggleFavorite(fav.productId); }}
                      title={t('removeFromFavorites')}
                    >
                      <HeartFilled />
                    </button>
                  </div>

                  {/* Body */}
                  <div className="fv-card-body" onClick={() => navigate(`/product/${fav.productId}`)}>
                    <div className="fv-card-meta">
                      {fav.productCategory && <span className="fv-card-badge">{fav.productCategory}</span>}
                      {fav.productBrand && <span className="fv-card-brand">{fav.productBrand}</span>}
                    </div>
                    <h3 className="fv-card-name">{fav.productName}</h3>
                    <div className="fv-card-footer">
                      <span className="fv-card-price">{fmt(fav.productPrice)} <span className="fv-currency">TND</span></span>
                      <span className={`fv-card-stock ${fav.productInStock ? 'fv-in-stock' : 'fv-out-stock'}`}>
                        {fav.productInStock ? t('inStock') : t('outOfStock')}
                      </span>
                    </div>
                  </div>

                  {/* Add to cart */}
                  <div className="fv-card-actions">
                    <button
                      className="fv-btn-cart"
                      disabled={!fav.productInStock}
                      onClick={() => addToCart({ id: fav.productId, name: fav.productName, price: fav.productPrice, image: fav.productImage, stock: null }, 1)}
                    >
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" width="15" height="15">
                        <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                      </svg>
                      {fav.productInStock ? t('addToCart') : t('unavailable')}
                    </button>
                  </div>
                </div>
              ))}
            </div>
          )}

        </div>
      </main>
    </div>
  );
}
