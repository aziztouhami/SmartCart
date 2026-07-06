import React, { useState, useEffect, lazy, Suspense } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Home as HomeIcon, Briefcase, MapPin } from 'lucide-react';
import { getUser, logout, updateLocalUser } from '../../services/authService';
import { addressApi, profileApi, brandApi } from '../../services/cartService';
import { useCart } from '../../context/CartContext';
import { useCategories } from '../../context/CategoryContext';
import './Profile.css';

const AddressMapModal = lazy(() => import('./AddressMapModal'));

const LABEL_ICON = { Home: HomeIcon, Work: Briefcase, Other: MapPin };

export default function Profile() {
  const { t } = useTranslation('profile');
  const navigate = useNavigate();
  const user     = getUser();
  const { resetCart } = useCart();

  const [toast, setToast]         = useState(null);
  const [addresses, setAddresses] = useState([]);
  const [loading, setLoading]     = useState(true);
  const [mapModal, setMapModal]   = useState(null); // null | { mode:'add' } | { mode:'edit', addr }

  const [form, setForm] = useState({
    firstName: user?.firstName ?? '',
    lastName:  user?.lastName  ?? '',
    email:     user?.email     ?? '',
    phone:     user?.phone     ?? '',
  });

  const [savingInfo, setSavingInfo] = useState(false);

  const [pwForm, setPwForm] = useState({ current: '', next: '', confirm: '' });
  const [pwErr,  setPwErr]  = useState('');
  const [pwSaving, setPwSaving] = useState(false);

  const [marketingOptIn, setMarketingOptIn] = useState(false);
  const [deleteModalOpen, setDeleteModalOpen] = useState(false);
  const [deleting, setDeleting] = useState(false);

  const { leafCategories } = useCategories();
  const [brands, setBrands] = useState([]);
  const [preferredCategoryIds, setPreferredCategoryIds] = useState([]);
  const [preferredBrandIds, setPreferredBrandIds] = useState([]);
  const [prefSaving, setPrefSaving] = useState(false);

  const showToast = (msg, type = 'success') => {
    setToast({ msg, type });
    setTimeout(() => setToast(null), 3000);
  };

  /* ── Load addresses from backend on mount ── */
  useEffect(() => {
    addressApi.list()
      .then(res => setAddresses(res.data))
      .catch(() => showToast(t('addresses.loadError'), 'error'))
      .finally(() => setLoading(false));
  }, [t]);

  /* ── Load full profile (marketingOptIn isn't in the cached login user) ── */
  useEffect(() => {
    profileApi.get()
      .then(res => {
        setMarketingOptIn(!!res.data.marketingOptIn);
        setPreferredCategoryIds(res.data.preferredCategoryIds || []);
        setPreferredBrandIds(res.data.preferredBrandIds || []);
      })
      .catch(() => {});
  }, []);

  /* ── Load brand options for the preferences picker ── */
  useEffect(() => {
    brandApi.list(1, 24)
      .then(res => setBrands(res.data.data || []))
      .catch(() => {});
  }, []);

  const togglePreference = async (list, setList, key, id) => {
    const next = list.includes(id) ? list.filter(x => x !== id) : [...list, id];
    setList(next);
    setPrefSaving(true);
    try {
      await profileApi.update({ [key]: next });
    } catch {
      setList(list);
      showToast(t('preferences.updateError'), 'error');
    } finally {
      setPrefSaving(false);
    }
  };

  const handleMarketingToggle = async (e) => {
    const next = e.target.checked;
    setMarketingOptIn(next);
    try {
      await profileApi.update({ marketingOptIn: next });
      showToast(next ? t('notifications.optInSuccess') : t('notifications.optOutSuccess'));
    } catch {
      setMarketingOptIn(!next);
      showToast(t('notifications.updateError'), 'error');
    }
  };

  const handleDeleteAccount = async () => {
    setDeleting(true);
    try {
      await profileApi.requestDeletion();
      logout();
      resetCart();
      navigate('/login', { state: { accountDeletionScheduled: true } });
    } catch {
      showToast(t('dangerZone.deleteError'), 'error');
      setDeleting(false);
    }
  };

  /* ── Profile form ── */
  const handleSave = async (e) => {
    e.preventDefault();
    setSavingInfo(true);
    try {
      const res = await profileApi.update({
        firstName: form.firstName,
        lastName:  form.lastName,
        email:     form.email,
        phone:     form.phone,
      });
      updateLocalUser({
        firstName: res.data.firstName,
        lastName:  res.data.lastName,
        email:     res.data.email,
        phone:     res.data.phone,
      });
      showToast(t('personalInfo.saveSuccess'));
    } catch (err) {
      showToast(err.response?.data?.error || t('personalInfo.saveError'), 'error');
    } finally {
      setSavingInfo(false);
    }
  };

  const handlePassword = async (e) => {
    e.preventDefault();
    setPwErr('');
    if (!pwForm.current)               { setPwErr(t('password.errors.currentRequired')); return; }
    if (pwForm.next.length < 8)        { setPwErr(t('password.errors.tooShort')); return; }
    if (pwForm.next !== pwForm.confirm) { setPwErr(t('password.errors.mismatch')); return; }

    setPwSaving(true);
    try {
      await profileApi.changePassword({ currentPassword: pwForm.current, newPassword: pwForm.next });
      showToast(t('password.updateSuccess'));
      setPwForm({ current: '', next: '', confirm: '' });
    } catch (err) {
      setPwErr(err.response?.data?.error || t('password.updateError'));
    } finally {
      setPwSaving(false);
    }
  };

  /* ── Address CRUD ── */
  const handleAddressSave = async (addr) => {
    try {
      if (mapModal.mode === 'add') {
        const res = await addressApi.create({
          label:      addr.label,
          street:     addr.street,
          city:       addr.city,
          postalCode: addr.postalCode,
          country:    addr.country,
          isDefault:  addr.isDefault,
          lat:        addr.lat ?? null,
          lng:        addr.lng ?? null,
        });
        setAddresses(prev =>
          addr.isDefault
            ? [...prev.map(a => ({ ...a, isDefault: false })), res.data]
            : [...prev, res.data]
        );
        showToast(t('addresses.addSuccess'));
      } else {
        const res = await addressApi.update(mapModal.addr.id, {
          label:      addr.label,
          street:     addr.street,
          city:       addr.city,
          postalCode: addr.postalCode,
          country:    addr.country,
          isDefault:  addr.isDefault,
          lat:        addr.lat ?? null,
          lng:        addr.lng ?? null,
        });
        setAddresses(prev =>
          prev.map(a =>
            a.id === mapModal.addr.id
              ? res.data
              : addr.isDefault ? { ...a, isDefault: false } : a
          )
        );
        showToast(t('addresses.updateSuccess'));
      }
    } catch {
      showToast(t('addresses.saveError'), 'error');
    }
    setMapModal(null);
  };

  const handleSetDefault = async (addr) => {
    try {
      await addressApi.update(addr.id, { isDefault: true });
      setAddresses(prev => prev.map(a => ({ ...a, isDefault: a.id === addr.id })));
      showToast(t('addresses.setDefaultSuccess'));
    } catch {
      showToast(t('addresses.setDefaultError'), 'error');
    }
  };

  const handleDeleteAddress = async (addr) => {
    try {
      await addressApi.remove(addr.id);
      setAddresses(prev => prev.filter(a => a.id !== addr.id));
      showToast(t('addresses.removeSuccess'), 'error');
    } catch {
      showToast(t('addresses.removeError'), 'error');
    }
  };

  const initials = `${user?.firstName?.[0] ?? ''}${user?.lastName?.[0] ?? ''}`.toUpperCase() || '?';

  return (
    <div className="pf-page">
      {toast && <div className={`pf-toast pf-toast--${toast.type}`}>{toast.msg}</div>}

      <nav className="pf-nav">
        <div className="pf-nav-inner">
          <button className="pf-back" onClick={() => navigate(-1)}>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" width="15" height="15"><polyline points="15 18 9 12 15 6"/></svg>
            {t('nav.back')}
          </button>
          <div className="pf-logo" onClick={() => navigate('/')}>
            <span className="pf-logo-icon">S</span>
            <span className="pf-logo-text">SmartCart</span>
          </div>
          <button className="pf-logout" onClick={() => { logout(); resetCart(); navigate('/login'); }}>{t('nav.logout')}</button>
        </div>
      </nav>

      <main className="pf-main">
        <div className="pf-container">

          {/* Header */}
          <div className="pf-header">
            <div className="pf-avatar">{initials}</div>
            <div>
              <h1 className="pf-title">{user?.firstName} {user?.lastName}</h1>
              <p className="pf-sub">{user?.email}</p>
              {user?.roles?.includes('ROLE_ADMIN') && <span className="pf-admin-badge">{t('header.admin')}</span>}
            </div>
          </div>

          {/* Info + Password */}
          <div className="pf-grid">
            <div className="pf-card">
              <h2 className="pf-card-title">{t('personalInfo.title')}</h2>
              <form className="pf-form" onSubmit={handleSave}>
                <div className="pf-row">
                  <div className="pf-field">
                    <label>{t('personalInfo.firstName')}</label>
                    <input value={form.firstName} onChange={e => setForm(f => ({ ...f, firstName: e.target.value }))} placeholder={t('personalInfo.firstNamePlaceholder')} />
                  </div>
                  <div className="pf-field">
                    <label>{t('personalInfo.lastName')}</label>
                    <input value={form.lastName} onChange={e => setForm(f => ({ ...f, lastName: e.target.value }))} placeholder={t('personalInfo.lastNamePlaceholder')} />
                  </div>
                </div>
                <div className="pf-field">
                  <label>{t('personalInfo.email')}</label>
                  <input type="email" value={form.email} onChange={e => setForm(f => ({ ...f, email: e.target.value }))} placeholder={t('personalInfo.emailPlaceholder')} />
                </div>
                <div className="pf-field">
                  <label>{t('personalInfo.phone')}</label>
                  <input type="tel" value={form.phone} onChange={e => setForm(f => ({ ...f, phone: e.target.value }))} placeholder={t('personalInfo.phonePlaceholder')} />
                </div>
                <button type="submit" className="pf-btn-save" disabled={savingInfo}>
                  {savingInfo ? t('personalInfo.saving') : t('personalInfo.save')}
                </button>
              </form>
            </div>

            <div className="pf-card">
              <h2 className="pf-card-title">{t('password.title')}</h2>
              <form className="pf-form" onSubmit={handlePassword}>
                {pwErr && <div className="pf-error">{pwErr}</div>}
                <div className="pf-field">
                  <label>{t('password.current')}</label>
                  <input type="password" value={pwForm.current} onChange={e => setPwForm(f => ({ ...f, current: e.target.value }))} placeholder="••••••••" />
                </div>
                <div className="pf-field">
                  <label>{t('password.new')}</label>
                  <input type="password" value={pwForm.next} onChange={e => setPwForm(f => ({ ...f, next: e.target.value }))} placeholder={t('password.newPlaceholder')} />
                </div>
                <div className="pf-field">
                  <label>{t('password.confirm')}</label>
                  <input type="password" value={pwForm.confirm} onChange={e => setPwForm(f => ({ ...f, confirm: e.target.value }))} placeholder="••••••••" />
                </div>
                <button type="submit" className="pf-btn-save" disabled={pwSaving}>
                  {pwSaving ? t('password.updating') : t('password.update')}
                </button>
              </form>
            </div>
          </div>

          {/* Notifications + Danger Zone */}
          <div className="pf-grid">
            <div className="pf-card">
              <h2 className="pf-card-title">{t('notifications.title')}</h2>
              <label className="pf-checkbox-row">
                <input
                  type="checkbox"
                  checked={marketingOptIn}
                  onChange={handleMarketingToggle}
                />
                <span>{t('notifications.marketingOptIn')}</span>
              </label>
            </div>

            <div className="pf-card pf-card--danger">
              <h2 className="pf-card-title">{t('dangerZone.title')}</h2>
              <p className="pf-danger-text">
                {t('dangerZone.text')}
              </p>
              <button className="pf-btn-danger" onClick={() => setDeleteModalOpen(true)}>
                {t('dangerZone.deleteButton')}
              </button>
            </div>
          </div>

          {/* Shopping preferences — seeds recommendations before any real activity exists */}
          <div className="pf-card pf-card--full">
            <h2 className="pf-card-title">{t('preferences.title')}</h2>
            <p className="pf-pref-hint">
              {t('preferences.hint')}
            </p>

            {leafCategories.length > 0 && (
              <div className="pf-pref-chips">
                {leafCategories.map(c => (
                  <button
                    type="button"
                    key={c.id}
                    className={`pf-pref-chip${preferredCategoryIds.includes(c.id) ? ' pf-pref-chip--active' : ''}`}
                    disabled={prefSaving}
                    onClick={() => togglePreference(preferredCategoryIds, setPreferredCategoryIds, 'preferredCategoryIds', c.id)}
                  >
                    {c.name}
                  </button>
                ))}
              </div>
            )}

            {brands.length > 0 && (
              <div className="pf-pref-chips">
                {brands.map(b => (
                  <button
                    type="button"
                    key={b.id}
                    className={`pf-pref-chip${preferredBrandIds.includes(b.id) ? ' pf-pref-chip--active' : ''}`}
                    disabled={prefSaving}
                    onClick={() => togglePreference(preferredBrandIds, setPreferredBrandIds, 'preferredBrandIds', b.id)}
                  >
                    {b.name}
                  </button>
                ))}
              </div>
            )}
          </div>

          {/* Addresses */}
          <div className="pf-card pf-card--full">
            <div className="pf-addresses-head">
              <h2 className="pf-card-title" style={{ margin: 0, border: 'none', padding: 0 }}>
                {t('addresses.title')}
              </h2>
              <button className="pf-btn-add-addr" onClick={() => setMapModal({ mode: 'add' })}>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" width="14" height="14">
                  <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                {t('addresses.addButton')}
              </button>
            </div>

            {loading ? (
              <div className="pf-addr-empty"><p>{t('addresses.loading')}</p></div>
            ) : addresses.length === 0 ? (
              <div className="pf-addr-empty">
                <div className="pf-addr-empty-icon"><MapPin size={28} /></div>
                <p>{t('addresses.empty')}</p>
                <button className="pf-btn-save" style={{ margin: '0 auto' }} onClick={() => setMapModal({ mode: 'add' })}>
                  {t('addresses.addFirstButton')}
                </button>
              </div>
            ) : (
              <div className="pf-addr-list">
                {addresses.map(addr => {
                  const LabelIcon = LABEL_ICON[addr.label] ?? MapPin;
                  return (
                  <div key={addr.id} className={`pf-addr-card ${addr.isDefault ? 'pf-addr-card--default' : ''}`}>
                    <div className="pf-addr-icon"><LabelIcon size={16} /></div>
                    <div className="pf-addr-body">
                      <div className="pf-addr-top">
                        <span className="pf-addr-label">{t(`addressModal.labels.${addr.label?.toLowerCase()}`, { defaultValue: addr.label })}</span>
                        {addr.isDefault && <span className="pf-addr-default-badge">{t('addresses.default')}</span>}
                      </div>
                      <p className="pf-addr-street">{addr.street}</p>
                      <p className="pf-addr-city">
                        {[addr.city, addr.postalCode, addr.country].filter(Boolean).join(', ')}
                      </p>
                      {addr.lat != null && addr.lng != null && (
                        <p className="pf-addr-coords">
                          <MapPin size={12} className="pf-inline-icon" /> {Number(addr.lat).toFixed(5)}, {Number(addr.lng).toFixed(5)}
                        </p>
                      )}
                    </div>
                    <div className="pf-addr-actions">
                      {!addr.isDefault && (
                        <button className="pf-addr-btn pf-addr-btn--default" onClick={() => handleSetDefault(addr)}>
                          {t('addresses.setDefaultButton')}
                        </button>
                      )}
                      <button className="pf-addr-btn pf-addr-btn--edit" onClick={() => setMapModal({ mode: 'edit', addr })}>
                        {t('addresses.editButton')}
                      </button>
                      <button className="pf-addr-btn pf-addr-btn--del" onClick={() => handleDeleteAddress(addr)}>
                        {t('addresses.deleteButton')}
                      </button>
                    </div>
                  </div>
                  );
                })}
              </div>
            )}
          </div>

        </div>
      </main>

      {/* Delete account confirm modal */}
      {deleteModalOpen && (
        <div className="pf-overlay" onClick={() => !deleting && setDeleteModalOpen(false)}>
          <div className="pf-confirm-modal" onClick={e => e.stopPropagation()}>
            <h3>{t('dangerZone.confirmModal.title')}</h3>
            <p>
              {t('dangerZone.confirmModal.textBefore')} <strong>{t('dangerZone.confirmModal.textBold')}</strong>.{' '}
              {t('dangerZone.confirmModal.textAfter')}
            </p>
            <div className="pf-confirm-actions">
              <button className="pf-btn-outline" onClick={() => setDeleteModalOpen(false)} disabled={deleting}>
                {t('dangerZone.confirmModal.cancel')}
              </button>
              <button className="pf-btn-danger" onClick={handleDeleteAccount} disabled={deleting}>
                {deleting ? t('dangerZone.confirmModal.scheduling') : t('dangerZone.confirmModal.confirmButton')}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Map modal — lazily loaded */}
      {mapModal && (
        <Suspense fallback={<div className="pf-map-loading">{t('addressModal.loadingMap')}</div>}>
          <AddressMapModal
            initial={mapModal.mode === 'edit' ? mapModal.addr : undefined}
            onSave={handleAddressSave}
            onClose={() => setMapModal(null)}
          />
        </Suspense>
      )}
    </div>
  );
}
