import React from 'react';
import { useTranslation } from 'react-i18next';
import { MapPin } from 'lucide-react';
import './CheckoutModal.css';

export default function CheckoutModal({
  addresses,
  addrLoading,
  selectedAddr,
  setSelectedAddr,
  newAddr,
  setNewAddr,
  phone,
  setPhone,
  phoneError,
  setPhoneError,
  checkoutError,
  placing,
  onClose,
  onMapOpen,
  onPlaceOrder,
}) {
  const { t } = useTranslation('cart');

  return (
    <div className="cp-overlay" onClick={onClose}>
      <div className="cp-modal" onClick={e => e.stopPropagation()}>
        <div className="cp-modal-head">
          <h2>{t('checkout.shippingAddress')}</h2>
          <button className="cp-modal-x" onClick={onClose}>
            ✕
          </button>
        </div>

        <div className="cp-modal-body">
          {addrLoading ? (
            <p className="cp-modal-loading">{t('checkout.loadingAddresses')}</p>
          ) : (
            <>
              {addresses.length > 0 && (
                <div className="cp-section">
                  <p className="cp-section-label">{t('checkout.savedAddresses')}</p>
                  <div className="cp-addr-list">
                    {addresses.map(addr => (
                      <label
                        key={addr.id}
                        className={`cp-addr-card ${selectedAddr === String(addr.id) ? 'cp-addr-card--on' : ''}`}
                      >
                        <input
                          type="radio"
                          name="addr"
                          value={String(addr.id)}
                          checked={selectedAddr === String(addr.id)}
                          onChange={() => setSelectedAddr(String(addr.id))}
                        />
                        <div className="cp-addr-body">
                          <div className="cp-addr-row">
                            <span className="cp-addr-label">{addr.label}</span>
                            {addr.isDefault && (
                              <span className="cp-addr-default">{t('checkout.default')}</span>
                            )}
                          </div>
                          <span className="cp-addr-line">{addr.street}</span>
                          <span className="cp-addr-line">
                            {addr.city}
                            {addr.postalCode ? ` ${addr.postalCode}` : ''}, {addr.country}
                          </span>
                        </div>
                      </label>
                    ))}

                    <label
                      className={`cp-addr-card cp-addr-card--new ${selectedAddr === 'new' ? 'cp-addr-card--on' : ''}`}
                    >
                      <input
                        type="radio"
                        name="addr"
                        value="new"
                        checked={selectedAddr === 'new'}
                        onChange={() => setSelectedAddr('new')}
                      />
                      <div className="cp-addr-body">
                        <span className="cp-addr-label">+ {t('checkout.enterNewAddress')}</span>
                      </div>
                    </label>
                  </div>
                </div>
              )}

              {(selectedAddr === 'new' || addresses.length === 0) && (
                <div className="cp-section">
                  {addresses.length === 0 && (
                    <p className="cp-section-label">{t('checkout.shippingAddress')}</p>
                  )}
                  <button type="button" className="cp-btn-map" onClick={onMapOpen}>
                    <MapPin size={14} /> {t('checkout.pickOnMap')}
                  </button>
                  <div className="cp-field">
                    <label>{t('checkout.fields.street')} *</label>
                    <input
                      type="text"
                      placeholder={t('checkout.fields.streetPlaceholder')}
                      value={newAddr.street}
                      onChange={e => setNewAddr(p => ({ ...p, street: e.target.value }))}
                    />
                  </div>
                  <div className="cp-field-row">
                    <div className="cp-field">
                      <label>{t('checkout.fields.city')} *</label>
                      <input
                        type="text"
                        placeholder={t('checkout.fields.cityPlaceholder')}
                        value={newAddr.city}
                        onChange={e => setNewAddr(p => ({ ...p, city: e.target.value }))}
                      />
                    </div>
                    <div className="cp-field">
                      <label>{t('checkout.fields.postalCode')}</label>
                      <input
                        type="text"
                        placeholder={t('checkout.fields.postalCodePlaceholder')}
                        value={newAddr.postalCode}
                        onChange={e => setNewAddr(p => ({ ...p, postalCode: e.target.value }))}
                      />
                    </div>
                  </div>
                  <div className="cp-field">
                    <label>{t('checkout.fields.country')} *</label>
                    <input
                      type="text"
                      placeholder={t('checkout.fields.countryPlaceholder')}
                      value={newAddr.country}
                      onChange={e => setNewAddr(p => ({ ...p, country: e.target.value }))}
                    />
                  </div>
                </div>
              )}

              <div className="cp-section">
                <p className="cp-section-label">{t('checkout.contactPhone')} *</p>
                <div className="cp-field">
                  <input
                    type="tel"
                    placeholder={t('checkout.fields.phonePlaceholder')}
                    value={phone}
                    onChange={e => {
                      setPhone(e.target.value);
                      setPhoneError('');
                    }}
                  />
                  {phoneError && <span className="cp-field-error">{phoneError}</span>}
                </div>
              </div>

              {checkoutError && <div className="cp-error">{checkoutError}</div>}
            </>
          )}
        </div>

        <div className="cp-modal-foot">
          <button className="cp-btn-outline" onClick={onClose}>
            {t('checkout.cancel')}
          </button>
          <button className="cp-btn-solid" onClick={onPlaceOrder} disabled={placing || addrLoading}>
            {placing ? t('checkout.placingOrder') : t('checkout.placeOrder')}
          </button>
        </div>
      </div>
    </div>
  );
}
