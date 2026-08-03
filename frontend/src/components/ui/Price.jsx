import React from 'react';
import { formatPrice } from '../../utils/format';
import './Price.css';

// Renders a single price, or a promo price + struck-through old price + % off chip.
export default function Price({ value, oldValue, percentage, currency = 'TND', className = '' }) {
  const hasDiscount = oldValue != null && Number(oldValue) > Number(value);
  const pct =
    percentage != null
      ? Math.round(percentage)
      : hasDiscount
        ? Math.round(((oldValue - value) / oldValue) * 100)
        : null;

  return (
    <div className={['ui-price-row', className].filter(Boolean).join(' ')}>
      <span className={`ui-price ${hasDiscount ? 'ui-price--discounted' : ''}`}>
        {formatPrice(value)} <span className="ui-price-currency">{currency}</span>
      </span>
      {hasDiscount && <span className="ui-price-old">{formatPrice(oldValue)}</span>}
      {hasDiscount && pct != null && <span className="ui-pct-chip">-{pct}%</span>}
    </div>
  );
}
