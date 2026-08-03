import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useCart } from '../context/CartContext';
import { useFavorites } from '../context/FavoriteContext';
import { isAuthenticated } from '../services/authService';
import { CATEGORY_ICONS, DEFAULT_CATEGORY_ICON } from '../constants/categoryIcons';
import Badge from './ui/Badge';
import IconButton from './ui/IconButton';
import Button from './ui/Button';
import Price from './ui/Price';
import Skeleton from './ui/Skeleton';
import HeartIcon from './ui/HeartIcon';
import './ProductCard.css';

// ── Category gradient map (keyed by PARENT category name) ─────────────────────
export const CATEGORY_META = {
  Electronics: { gradient: 'linear-gradient(135deg, #0f3460 0%, #16498e 100%)' },
  Fashion: { gradient: 'linear-gradient(135deg, #7b1a5c 0%, #c9184a 100%)' },
  'Home & Garden': { gradient: 'linear-gradient(135deg, #1b4332 0%, #2d6a4f 100%)' },
  'Beauty & Health': { gradient: 'linear-gradient(135deg, #3d0066 0%, #7b2fbe 100%)' },
  'Sports & Outdoors': { gradient: 'linear-gradient(135deg, #c44b00 0%, #e85d04 100%)' },
  Books: { gradient: 'linear-gradient(135deg, #005f73 0%, #0a9396 100%)' },
  'Gaming & Toys': { gradient: 'linear-gradient(135deg, #6a0572 0%, #ab18bd 100%)' },
  Automotive: { gradient: 'linear-gradient(135deg, #1c1c1c 0%, #424242 100%)' },
  'Food & Beverages': { gradient: 'linear-gradient(135deg, #7b3f00 0%, #c97d4e 100%)' },
  'Pet Supplies': { gradient: 'linear-gradient(135deg, #1a5276 0%, #2471a3 100%)' },
};

export default function ProductCard({ product }) {
  const { t, i18n } = useTranslation('product');
  const navigate = useNavigate();
  const { addToCart } = useCart();
  const { isFavorite, toggleFavorite } = useFavorites();
  const [added, setAdded] = useState(false);
  const [favLoading, setFavLoading] = useState(false);

  const parentName = product.category?.parent?.name ?? product.category?.name ?? '';
  const meta = CATEGORY_META[parentName] || {
    gradient: 'linear-gradient(135deg, #185FA5, #042C53)',
  };
  const CategoryIcon = CATEGORY_ICONS[parentName] || DEFAULT_CATEGORY_ICON;

  const handleAddToCart = e => {
    e.stopPropagation();
    addToCart(product, 1);
    setAdded(true);
    setTimeout(() => setAdded(false), 1500);
  };

  const handleFav = async e => {
    e.stopPropagation();
    if (!isAuthenticated()) {
      navigate('/login', { state: { from: '/' } });
      return;
    }
    setFavLoading(true);
    try {
      await toggleFavorite(product.id);
    } finally {
      setFavLoading(false);
    }
  };

  const faved = isFavorite(product.id);
  const image = product.images?.[0];
  const promo = product.promotion;

  return (
    <div
      className="pc-card"
      data-testid="product-card"
      onClick={() => navigate(`/product/${product.id}`)}
    >
      <div
        className={`pc-image ${image ? 'pc-image--photo' : ''}`}
        style={!image ? { background: meta.gradient } : undefined}
      >
        {(promo || product.isNew) && (
          <div className="pc-tags">
            {promo && (
              <Badge tone="danger" variant="solid" size="sm">
                {t('promotion')}
              </Badge>
            )}
            {product.isNew && (
              <Badge tone="success" variant="solid" size="sm">
                {t('new')}
              </Badge>
            )}
          </div>
        )}
        {image ? (
          <img src={image} alt={product.name} className="pc-img" />
        ) : (
          <span className="pc-emoji">
            <CategoryIcon size={32} />
          </span>
        )}
        <div className="pc-shine" />
        <IconButton
          className="pc-fav"
          active={faved}
          onClick={handleFav}
          disabled={favLoading}
          title={faved ? t('removeFromFavorites') : t('addToFavorites')}
        >
          <HeartIcon size={16} filled={faved} strokeWidth={2.2} />
        </IconButton>
      </div>

      <div className="pc-body">
        <div className="pc-badges">
          <Badge tone="primary" variant="soft">
            {product.category?.name}
          </Badge>
          {product.brand && (
            <Badge
              tone="neutral"
              variant="soft"
              icon={
                product.brand.image && (
                  <img
                    src={product.brand.image}
                    alt={product.brand.name}
                    className="pc-brand-logo"
                  />
                )
              }
            >
              {product.brand.name}
            </Badge>
          )}
        </div>

        <h3 className="pc-name">{product.name}</h3>

        <Price
          value={promo ? promo.newPrice : product.price}
          oldValue={promo ? promo.oldPrice : null}
          percentage={promo?.percentage}
        />

        {/* Always rendered (even for non-promo cards) so every card reserves
            the same line of vertical space here — otherwise cards without an
            end date are shorter and the Add to Cart button below lands at a
            different height from row to row. */}
        <span className={`pc-end-date${promo?.endDate ? '' : ' pc-end-date--hidden'}`}>
          {promo?.endDate
            ? t('ends', {
                date: new Date(promo.endDate).toLocaleDateString(
                  i18n.language === 'fr' ? 'fr-FR' : 'en-GB',
                  { day: '2-digit', month: 'short' },
                ),
              })
            : ' '}
        </span>

        <Button
          variant={added ? 'success' : 'primary'}
          size="sm"
          fullWidth
          className="pc-add-btn"
          data-testid="product-add-to-cart"
          onClick={handleAddToCart}
          disabled={!product.inStock}
        >
          {added ? (
            <>
              <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="2.5"
                width="14"
                height="14"
              >
                <polyline points="20 6 9 17 4 12" />
              </svg>
              {t('added')}
            </>
          ) : (
            <>
              <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="2.2"
                width="14"
                height="14"
              >
                <circle cx="9" cy="21" r="1" />
                <circle cx="20" cy="21" r="1" />
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
              </svg>
              {product.inStock ? t('addToCart') : t('outOfStock')}
            </>
          )}
        </Button>
      </div>
    </div>
  );
}

export function SkeletonCard() {
  return (
    <div className="pc-card pc-card--skeleton">
      <Skeleton className="pc-skeleton--img" height={200} radius={0} />
      <div className="pc-body">
        <Skeleton width={80} height={18} radius={100} />
        <Skeleton height={14} />
        <Skeleton height={14} width="70%" />
        <Skeleton height={38} radius={12} className="pc-skeleton--btn" />
      </div>
    </div>
  );
}
