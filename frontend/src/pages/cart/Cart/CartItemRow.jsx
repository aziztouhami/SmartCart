import React from 'react';
import { useTranslation } from 'react-i18next';
import { formatPrice as fmt } from '../../../utils/format';
import './CartItemRow.css';

export default function CartItemRow({ item, updateQty, removeFromCart }) {
  const { t } = useTranslation('cart');

  return (
    <div className="cp-item">
      <div className="cp-item-thumb">
        {item.image ? (
          <img src={item.image} alt={item.name} />
        ) : (
          <span className="cp-item-initial">{item.name?.[0]?.toUpperCase()}</span>
        )}
      </div>

      <div className="cp-item-info">
        <h3 className="cp-item-name">{item.name}</h3>
        <p className="cp-item-price">{t('item.pricePerUnit', { price: fmt(item.price) })}</p>
        {item.stock != null && item.stock <= 5 && (
          <p className="cp-item-warn">{t('item.lowStock', { count: item.stock })}</p>
        )}
      </div>

      <div className="cp-qty">
        <button
          className="cp-qty-btn"
          onClick={() =>
            item.qty > 1 ? updateQty(item.id, item.qty - 1) : removeFromCart(item.id)
          }
        >
          −
        </button>
        <span className="cp-qty-val">{item.qty}</span>
        <button
          className="cp-qty-btn"
          onClick={() => updateQty(item.id, item.qty + 1)}
          disabled={item.stock != null && item.qty >= item.stock}
        >
          +
        </button>
      </div>

      <span className="cp-item-sub">{fmt(item.price * item.qty)} TND</span>

      <button
        className="cp-item-del"
        onClick={() => removeFromCart(item.id)}
        title={t('item.remove')}
      >
        <svg
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="2"
          width="16"
          height="16"
        >
          <polyline points="3 6 5 6 21 6" />
          <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
          <path d="M10 11v6M14 11v6" />
          <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2" />
        </svg>
      </button>
    </div>
  );
}
