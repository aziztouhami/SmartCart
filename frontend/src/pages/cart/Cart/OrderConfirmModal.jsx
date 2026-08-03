import React from 'react';
import { useTranslation } from 'react-i18next';
import { formatPrice as fmt } from '../../../utils/format';

export default function OrderConfirmModal({
  items,
  cartTotal,
  address,
  phone,
  checkoutError,
  placing,
  onBack,
  onConfirm,
}) {
  const { t } = useTranslation('cart');

  return (
    <div className="cp-overlay" onClick={() => !placing && onBack()}>
      <div className="cp-modal" onClick={e => e.stopPropagation()}>
        <div className="cp-modal-head">
          <h2>{t('confirm.title')}</h2>
          <button className="cp-modal-x" onClick={() => !placing && onBack()}>
            ✕
          </button>
        </div>

        <div className="cp-modal-body">
          <div className="cp-section">
            <p className="cp-section-label">{t('confirm.items')}</p>
            {items.map(item => (
              <div key={item.id} className="cp-summary-row">
                <span>
                  {item.name} × {item.qty}
                </span>
                <span>{fmt(item.price * item.qty)} TND</span>
              </div>
            ))}
          </div>

          <div className="cp-section">
            <p className="cp-section-label">{t('confirm.shippingTo')}</p>
            <p className="cp-addr-line">{address.street}</p>
            <p className="cp-addr-line">
              {address.city}
              {address.postalCode ? ` ${address.postalCode}` : ''}, {address.country}
            </p>
            <p className="cp-addr-line">{phone.trim()}</p>
          </div>

          <div className="cp-summary-sep" />
          <div className="cp-summary-total">
            <span>{t('summary.total')}</span>
            <span>{fmt(cartTotal)} TND</span>
          </div>

          {checkoutError && <div className="cp-error">{checkoutError}</div>}
        </div>

        <div className="cp-modal-foot">
          <button className="cp-btn-outline" onClick={onBack} disabled={placing}>
            {t('confirm.back')}
          </button>
          <button className="cp-btn-solid" onClick={onConfirm} disabled={placing}>
            {placing ? t('checkout.placingOrder') : t('confirm.confirmOrder')}
          </button>
        </div>
      </div>
    </div>
  );
}
