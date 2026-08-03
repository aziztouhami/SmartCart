import React, { useState, Suspense, lazy } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { ShoppingCart } from 'lucide-react';
import Navbar from '../../components/Navbar';
import { useCart } from '../../context/CartContext';
import { isAuthenticated, getUser, updateLocalUser } from '../../services/authService';
import { orderApi, addressApi } from '../../services/cartService';
import { formatPrice as fmt } from '../../utils/format';
import CartItemRow from './Cart/CartItemRow';
import CheckoutModal from './Cart/CheckoutModal';
import OrderConfirmModal from './Cart/OrderConfirmModal';
import './Cart.css';

const AddressMapModal = lazy(() => import('../profile/AddressMapModal'));

function isTunisianPhone(raw) {
  const cleaned = raw.replace(/[\s\-().]/g, '');
  return /^(\+216|00216)?[2-9]\d{7}$/.test(cleaned);
}

export default function Cart() {
  const { t } = useTranslation('cart');
  const navigate = useNavigate();
  const { items, removeFromCart, updateQty, clearCart, cartTotal } = useCart();
  const cartCount = items.reduce((s, i) => s + i.qty, 0);
  const loggedIn = isAuthenticated();

  const [checkoutOpen, setCheckoutOpen] = useState(false);
  const [addresses, setAddresses] = useState([]);
  const [addrLoading, setAddrLoading] = useState(false);
  const [selectedAddr, setSelectedAddr] = useState('new');
  const [newAddr, setNewAddr] = useState({ street: '', city: '', postalCode: '', country: '' });
  const [phone, setPhone] = useState(getUser()?.phone ?? '');
  const [phoneError, setPhoneError] = useState('');
  const [placing, setPlacing] = useState(false);
  const [checkoutError, setCheckoutError] = useState('');
  const [orderId, setOrderId] = useState(null);
  const [mapOpen, setMapOpen] = useState(false);
  const [confirmOpen, setConfirmOpen] = useState(false);
  const [pendingPayload, setPendingPayload] = useState(null);

  const openCheckout = async () => {
    if (!loggedIn) {
      navigate('/login', { state: { from: '/cart' } });
      return;
    }
    setCheckoutOpen(true);
    setCheckoutError('');
    setAddrLoading(true);
    try {
      const res = await addressApi.list();
      const addrs = res.data || [];
      setAddresses(addrs);
      const def = addrs.find(a => a.isDefault) || addrs[0];
      setSelectedAddr(def ? String(def.id) : 'new');
    } catch {
      setAddresses([]);
      setSelectedAddr('new');
    } finally {
      setAddrLoading(false);
    }
  };

  const handleMapSave = async addr => {
    setMapOpen(false);
    try {
      const res = await addressApi.create({
        label: addr.label || addr.city || t('checkout.myAddress'),
        street: addr.street,
        city: addr.city,
        postalCode: addr.postalCode,
        country: addr.country,
        isDefault: false,
        lat: addr.lat,
        lng: addr.lng,
      });
      const saved = res.data;
      setAddresses(prev => [...prev, saved]);
      setSelectedAddr(String(saved.id));
    } catch {
      setNewAddr({
        street: addr.street,
        city: addr.city,
        postalCode: addr.postalCode || '',
        country: addr.country,
      });
    }
  };

  /** Validates the form and opens the order-summary confirmation modal. */
  const placeOrder = () => {
    setCheckoutError('');
    setPhoneError('');

    const trimmedPhone = phone.trim();
    if (!trimmedPhone) {
      setPhoneError(t('checkout.errors.phoneRequired'));
      return;
    }
    if (!isTunisianPhone(trimmedPhone)) {
      setPhoneError(t('checkout.errors.phoneInvalid'));
      return;
    }

    let payload;
    if (selectedAddr === 'new') {
      if (!newAddr.street || !newAddr.city || !newAddr.country) {
        setCheckoutError(t('checkout.errors.addressRequired'));
        return;
      }
      payload = { ...newAddr, contactPhone: trimmedPhone };
    } else {
      payload = { addressId: Number(selectedAddr), contactPhone: trimmedPhone };
    }

    setPendingPayload(payload);
    setConfirmOpen(true);
  };

  /** Resolves the address to display in the confirmation summary. */
  const summaryAddress = () => {
    if (selectedAddr === 'new') return newAddr;
    const addr = addresses.find(a => String(a.id) === selectedAddr);
    return addr || newAddr;
  };

  /** Actually submits the order once the user confirms the summary. */
  const confirmPlaceOrder = async () => {
    setPlacing(true);
    try {
      const res = await orderApi.checkout(pendingPayload);
      // The backend already saved this phone as the user's default for next
      // time — just keep the local cached profile in sync with it.
      updateLocalUser({ phone: phone.trim() });
      setOrderId(res.data.id);
      clearCart();
      setConfirmOpen(false);
      setCheckoutOpen(false);
    } catch (err) {
      setConfirmOpen(false);
      setCheckoutError(err.response?.data?.error || t('checkout.errors.placeOrderFailed'));
    } finally {
      setPlacing(false);
    }
  };

  /* ── Success screen ──────────────────────────────────── */
  if (orderId) {
    return (
      <div className="cp-page">
        <Navbar />
        <div className="cp-success-wrap">
          <div className="cp-success-card">
            <div className="cp-success-icon">✓</div>
            <h2>{t('success.title')}</h2>
            <p>
              {t('success.orderReceivedPrefix')} <strong>#{orderId}</strong>{' '}
              {t('success.orderReceivedSuffix')}
            </p>
            <p className="cp-success-sub">{t('success.notifyNote')}</p>
            <div className="cp-success-actions">
              <button className="cp-btn-outline" onClick={() => navigate('/')}>
                {t('success.continueShopping')}
              </button>
              <button className="cp-btn-solid" onClick={() => navigate('/orders')}>
                {t('success.trackOrder')}
              </button>
            </div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="cp-page">
      <Navbar />

      <main className="cp-main">
        <div className="cp-container">
          {/* Header */}
          <div className="cp-header">
            <div>
              <h1 className="cp-title">{t('title')}</h1>
              {cartCount > 0 && (
                <span className="cp-count">{t('itemCount', { count: cartCount })}</span>
              )}
            </div>
            {items.length > 0 && (
              <button className="cp-btn-clear" onClick={clearCart}>
                {t('clearCart')}
              </button>
            )}
          </div>

          {/* Empty state */}
          {items.length === 0 && (
            <div className="cp-empty" data-testid="cart-empty">
              <div className="cp-empty-icon">
                <ShoppingCart size={32} />
              </div>
              <h2>{t('empty.title')}</h2>
              <p>{t('empty.message')}</p>
              <button
                className="cp-btn-solid"
                data-testid="cart-browse-products"
                onClick={() => navigate('/')}
              >
                {t('empty.browseProducts')}
              </button>
            </div>
          )}

          {/* Cart layout */}
          {items.length > 0 && (
            <div className="cp-layout">
              {/* Items list */}
              <div className="cp-items">
                {items.map(item => (
                  <CartItemRow
                    key={item.id}
                    item={item}
                    updateQty={updateQty}
                    removeFromCart={removeFromCart}
                  />
                ))}

                <div className="cp-back-row">
                  <button className="cp-btn-back" onClick={() => navigate('/')}>
                    ← {t('continueShopping')}
                  </button>
                </div>
              </div>

              {/* Order summary */}
              <div className="cp-summary">
                <h3 className="cp-summary-title">{t('summary.title')}</h3>
                <div className="cp-summary-rows">
                  <div className="cp-summary-row">
                    <span>{t('summary.items', { count: cartCount })}</span>
                    <span>{fmt(cartTotal)} TND</span>
                  </div>
                  <div className="cp-summary-row">
                    <span>{t('summary.shipping')}</span>
                    <span className="cp-free">{t('summary.free')}</span>
                  </div>
                </div>
                <div className="cp-summary-sep" />
                <div className="cp-summary-total">
                  <span>{t('summary.total')}</span>
                  <span>{fmt(cartTotal)} TND</span>
                </div>

                <button className="cp-btn-checkout" onClick={openCheckout}>
                  {loggedIn ? t('summary.proceedToCheckout') : t('summary.signInToCheckout')}
                </button>

                {!loggedIn && (
                  <p className="cp-auth-note">
                    <button className="cp-link" onClick={() => navigate('/login')}>
                      {t('summary.signIn')}
                    </button>{' '}
                    {t('summary.toPlaceOrder')}
                  </p>
                )}
              </div>
            </div>
          )}
        </div>
      </main>

      {/* ── Address map modal ──────────────────────────── */}
      {mapOpen && (
        <Suspense fallback={null}>
          <AddressMapModal onSave={handleMapSave} onClose={() => setMapOpen(false)} />
        </Suspense>
      )}

      {/* ── Checkout modal ─────────────────────────────── */}
      {checkoutOpen && (
        <CheckoutModal
          addresses={addresses}
          addrLoading={addrLoading}
          selectedAddr={selectedAddr}
          setSelectedAddr={setSelectedAddr}
          newAddr={newAddr}
          setNewAddr={setNewAddr}
          phone={phone}
          setPhone={setPhone}
          phoneError={phoneError}
          setPhoneError={setPhoneError}
          checkoutError={checkoutError}
          placing={placing}
          onClose={() => setCheckoutOpen(false)}
          onMapOpen={() => setMapOpen(true)}
          onPlaceOrder={placeOrder}
        />
      )}

      {/* ── Order summary confirmation modal ───────────── */}
      {confirmOpen && (
        <OrderConfirmModal
          items={items}
          cartTotal={cartTotal}
          address={summaryAddress()}
          phone={phone}
          checkoutError={checkoutError}
          placing={placing}
          onBack={() => setConfirmOpen(false)}
          onConfirm={confirmPlaceOrder}
        />
      )}
    </div>
  );
}
