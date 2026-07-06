import React, { useState, useEffect } from 'react';
import { adminPromotionApi, brandApi } from '../../services/cartService';
import { fetchAllProducts } from '../../utils/fetchAllProducts';
import { formatPrice } from '../../utils/format';
import { IconPlus } from '../../components/admin/AdminIcons';
import './AdminPromotions.css';

const EMPTY_FORM = {
  type: 'product',
  productId: '',
  brandId: '',
  discountType: 'percentage',
  percentage: '',
  fixedPrice: '',
  startDate: '',
  endDate: '',
  noEndDate: true,
};

function toLocalInputValue(date) {
  const pad = n => String(n).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function fmtDate(iso) {
  if (!iso) return '—';
  return new Date(iso).toLocaleString('fr-TN', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function StatusBadge({ status }) {
  const map = {
    active:    { label: 'Active',    cls: 'pr-badge--active' },
    scheduled: { label: 'Scheduled', cls: 'pr-badge--scheduled' },
    ended:     { label: 'Ended',     cls: 'pr-badge--ended' },
  };
  const { label, cls } = map[status] || map.ended;
  return <span className={`pr-badge ${cls}`}>{label}</span>;
}

function targetLabel(promo) {
  if (promo.type === 'product') return promo.product?.name ?? '—';
  if (promo.type === 'brand')   return `${promo.brand?.name ?? '—'} (all products)`;
  return 'All Products (store-wide)';
}

export default function AdminPromotions() {
  const [promotions, setPromotions] = useState([]);
  const [products, setProducts]     = useState([]);
  const [brands, setBrands]         = useState([]);
  const [loading, setLoading]       = useState(true);
  const [modalOpen, setModalOpen]   = useState(false);
  const [form, setForm]             = useState(EMPTY_FORM);
  const [errors, setErrors]         = useState({});
  const [saving, setSaving]         = useState(false);
  const [confirmId, setConfirmId]   = useState(null);
  const [endId, setEndId]           = useState(null);
  const [toast, setToast]           = useState(null);

  const showToast = (msg, type = 'success') => {
    setToast({ msg, type });
    setTimeout(() => setToast(null), 3000);
  };

  const loadPromotions = () => {
    setLoading(true);
    return adminPromotionApi.list(1, 50)
      .then(res => setPromotions(res.data.data || []))
      .catch(() => showToast('Failed to load promotions.', 'error'))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    loadPromotions();
    fetchAllProducts().then(setProducts).catch(() => {});
    brandApi.list(1, 100).then(res => setBrands(res.data.data || [])).catch(() => {});
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  const openAdd = () => {
    setForm({ ...EMPTY_FORM, startDate: toLocalInputValue(new Date()) });
    setErrors({});
    setModalOpen(true);
  };

  const selectedProduct = products.find(p => String(p.id) === String(form.productId));

  const previewNewPrice = (() => {
    if (form.type !== 'product' || !selectedProduct) return null;
    const base = selectedProduct.price;
    if (form.discountType === 'fixed' && form.fixedPrice) {
      return parseFloat(form.fixedPrice);
    }
    if (form.discountType === 'percentage' && form.percentage) {
      return Math.round(base * (1 - parseFloat(form.percentage) / 100) * 100) / 100;
    }
    return null;
  })();

  const validate = () => {
    const e = {};
    if (form.type === 'product' && !form.productId) e.productId = 'Select a product.';
    if (form.type === 'brand' && !form.brandId) e.brandId = 'Select a brand.';
    if (form.discountType === 'percentage') {
      const pct = parseFloat(form.percentage);
      if (!form.percentage || isNaN(pct) || pct <= 0 || pct >= 100) e.percentage = 'Enter a percentage between 1 and 99.';
    } else {
      const price = parseFloat(form.fixedPrice);
      if (!form.fixedPrice || isNaN(price) || price <= 0) e.fixedPrice = 'Enter a valid price.';
      if (selectedProduct && price >= selectedProduct.price) e.fixedPrice = 'New price must be lower than the current price.';
    }
    if (!form.startDate) e.startDate = 'Start date is required.';
    if (!form.noEndDate && !form.endDate) e.endDate = 'Pick an end date or check "No end date".';
    if (!form.noEndDate && form.endDate && form.startDate && form.endDate < form.startDate) e.endDate = 'End date must be after the start date.';
    return e;
  };

  const handleSave = async () => {
    const e = validate();
    if (Object.keys(e).length) { setErrors(e); return; }

    setSaving(true);
    try {
      const payload = {
        type: form.type,
        productId: form.type === 'product' ? parseInt(form.productId) : null,
        brandId: form.type === 'brand' ? parseInt(form.brandId) : null,
        discountType: form.type === 'product' ? form.discountType : 'percentage',
        percentage: form.discountType === 'percentage' ? parseFloat(form.percentage) : null,
        fixedPrice: form.type === 'product' && form.discountType === 'fixed' ? parseFloat(form.fixedPrice) : null,
        startDate: new Date(form.startDate).toISOString(),
        endDate: form.noEndDate ? null : new Date(form.endDate).toISOString(),
      };
      await adminPromotionApi.create(payload);
      showToast('Promotion created successfully.');
      setModalOpen(false);
      await loadPromotions();
    } catch (err) {
      showToast(err.response?.data?.error || 'Failed to create promotion.', 'error');
    } finally {
      setSaving(false);
    }
  };

  const handleEnd = async (id) => {
    try {
      await adminPromotionApi.end(id);
      showToast('Promotion ended.');
      await loadPromotions();
    } catch {
      showToast('Failed to end promotion.', 'error');
    }
    setEndId(null);
  };

  const handleDelete = async (id) => {
    try {
      await adminPromotionApi.remove(id);
      setPromotions(prev => prev.filter(p => p.id !== id));
      showToast('Promotion deleted.');
    } catch {
      showToast('Failed to delete promotion.', 'error');
    }
    setConfirmId(null);
  };

  const activeCount = promotions.filter(p => p.status === 'active').length;

  return (
    <div className="adm-page">
      {toast && <div className={`ac-toast ac-toast--${toast.type}`}>{toast.msg}</div>}

      <div className="adm-page-header">
        <div>
          <h1 className="adm-page-title">Promotions</h1>
          <p className="adm-page-sub">{promotions.length} total &nbsp;·&nbsp; {activeCount} active</p>
        </div>
        <button className="adm-btn-primary" onClick={openAdd}>
          <IconPlus /> Add Promotion
        </button>
      </div>

      <div className="adm-table-wrap">
        <table className="adm-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Target</th>
              <th>Discount</th>
              <th>Old → New Price</th>
              <th>Starts</th>
              <th>Ends</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            {loading ? (
              <tr><td colSpan={8}><div className="adm-empty"><p>Loading promotions…</p></div></td></tr>
            ) : promotions.length === 0 ? (
              <tr><td colSpan={8}><div className="adm-empty"><p>No promotions yet. Click "Add Promotion" to create one.</p></div></td></tr>
            ) : (
              promotions.map((p, i) => (
                <tr key={p.id}>
                  <td className="adm-td-muted">{i + 1}</td>
                  <td><span className="ap-product-name">{targetLabel(p)}</span></td>
                  <td>
                    {p.discountType === 'percentage'
                      ? <span className="pr-discount">{p.percentage}% off</span>
                      : <span className="pr-discount">Fixed price</span>}
                  </td>
                  <td>
                    {p.type === 'product' ? (
                      <span className="pr-prices">
                        <span className="pr-price-old">{formatPrice(p.product.price)}</span>
                        <span className="pr-price-new">
                          {formatPrice(p.discountType === 'fixed' ? p.fixedPrice : Math.round(p.product.price * (1 - p.percentage / 100) * 100) / 100)}
                        </span>
                      </span>
                    ) : <span className="adm-muted">varies per product</span>}
                  </td>
                  <td className="adm-td-muted">{fmtDate(p.startDate)}</td>
                  <td className="adm-td-muted">{p.endDate ? fmtDate(p.endDate) : 'No end date'}</td>
                  <td><StatusBadge status={p.status} /></td>
                  <td>
                    <div className="adm-actions">
                      {p.status !== 'ended' && (
                        <button className="adm-btn-icon adm-btn-edit" onClick={() => setEndId(p.id)}>End Now</button>
                      )}
                      <button className="adm-btn-icon adm-btn-delete" onClick={() => setConfirmId(p.id)}>Delete</button>
                    </div>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      {/* Add modal */}
      {modalOpen && (
        <div className="adm-overlay" onClick={() => setModalOpen(false)}>
          <div className="adm-modal ap-modal-wide" onClick={e => e.stopPropagation()}>
            <div className="adm-modal-head">
              <h2>Add Promotion</h2>
              <button className="adm-modal-close" onClick={() => setModalOpen(false)}>✕</button>
            </div>
            <div className="adm-modal-body">
              <div className="adm-field">
                <label>Apply Promotion To *</label>
                <select
                  value={form.type}
                  onChange={e => setForm(f => ({
                    ...f,
                    type: e.target.value,
                    discountType: e.target.value === 'product' ? f.discountType : 'percentage',
                  }))}
                >
                  <option value="product">A Single Product</option>
                  <option value="brand">A Brand (all its products)</option>
                  <option value="all">All Products (store-wide)</option>
                </select>
              </div>

              {form.type === 'product' && (
                <div className="adm-field">
                  <label>Product *</label>
                  <select value={form.productId} onChange={e => setForm(f => ({ ...f, productId: e.target.value }))}>
                    <option value="">— Select a product —</option>
                    {products.map(p => (
                      <option key={p.id} value={p.id}>{p.name} — {formatPrice(p.price)} TND</option>
                    ))}
                  </select>
                  {errors.productId && <span className="ap-err">{errors.productId}</span>}
                </div>
              )}

              {form.type === 'brand' && (
                <div className="adm-field">
                  <label>Brand *</label>
                  <select value={form.brandId} onChange={e => setForm(f => ({ ...f, brandId: e.target.value }))}>
                    <option value="">— Select a brand —</option>
                    {brands.map(b => (
                      <option key={b.id} value={b.id}>{b.name}</option>
                    ))}
                  </select>
                  {errors.brandId && <span className="ap-err">{errors.brandId}</span>}
                </div>
              )}

              <div className="adm-field">
                <label>Discount Type *</label>
                <select
                  value={form.discountType}
                  onChange={e => setForm(f => ({ ...f, discountType: e.target.value }))}
                  disabled={form.type !== 'product'}
                >
                  <option value="percentage">Percentage off</option>
                  {form.type === 'product' && <option value="fixed">Fixed new price</option>}
                </select>
                {form.type !== 'product' && (
                  <span className="adm-field-hint">Brand and store-wide promotions can only use a percentage.</span>
                )}
              </div>

              <div className="adm-field-row">
                {form.discountType === 'percentage' ? (
                  <div className="adm-field">
                    <label>Percentage (%) *</label>
                    <input
                      type="number" min="1" max="99" step="1"
                      placeholder="e.g. 20"
                      value={form.percentage}
                      onChange={e => setForm(f => ({ ...f, percentage: e.target.value }))}
                    />
                    {errors.percentage && <span className="ap-err">{errors.percentage}</span>}
                  </div>
                ) : (
                  <div className="adm-field">
                    <label>New Price (TND) *</label>
                    <input
                      type="number" min="0" step="0.01"
                      placeholder="0.00"
                      value={form.fixedPrice}
                      onChange={e => setForm(f => ({ ...f, fixedPrice: e.target.value }))}
                    />
                    {errors.fixedPrice && <span className="ap-err">{errors.fixedPrice}</span>}
                  </div>
                )}

                {previewNewPrice !== null && (
                  <div className="adm-field">
                    <label>Preview</label>
                    <div className="pr-preview">
                      <span className="pr-price-old">{formatPrice(selectedProduct.price)}</span>
                      <span className="pr-price-new">{formatPrice(previewNewPrice)} TND</span>
                    </div>
                  </div>
                )}
              </div>

              <div className="adm-field-row">
                <div className="adm-field">
                  <label>Start Date *</label>
                  <input
                    type="datetime-local"
                    value={form.startDate}
                    onChange={e => setForm(f => ({ ...f, startDate: e.target.value }))}
                  />
                  {errors.startDate && <span className="ap-err">{errors.startDate}</span>}
                </div>
                <div className="adm-field">
                  <label>End Date</label>
                  <input
                    type="datetime-local"
                    value={form.endDate}
                    disabled={form.noEndDate}
                    onChange={e => setForm(f => ({ ...f, endDate: e.target.value }))}
                  />
                  {errors.endDate && <span className="ap-err">{errors.endDate}</span>}
                  <label className="pr-checkbox">
                    <input
                      type="checkbox"
                      checked={form.noEndDate}
                      onChange={e => setForm(f => ({ ...f, noEndDate: e.target.checked }))}
                    />
                    No end date — I'll end it manually
                  </label>
                </div>
              </div>
            </div>
            <div className="adm-modal-foot">
              <button className="adm-btn-cancel" onClick={() => setModalOpen(false)}>Cancel</button>
              <button className="adm-btn-save" onClick={handleSave} disabled={saving}>
                {saving ? 'Saving…' : 'Create Promotion'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* End-now confirm */}
      {endId && (
        <div className="adm-overlay" onClick={() => setEndId(null)}>
          <div className="adm-modal adm-modal--sm" onClick={e => e.stopPropagation()}>
            <div className="adm-modal-head">
              <h2>End Promotion Now?</h2>
              <button className="adm-modal-close" onClick={() => setEndId(null)}>✕</button>
            </div>
            <div className="adm-modal-body">
              <p style={{ fontSize: '0.875rem', color: '#475569', margin: 0, lineHeight: 1.6 }}>
                This promotion will stop applying immediately. This action cannot be undone.
              </p>
            </div>
            <div className="adm-modal-foot">
              <button className="adm-btn-cancel" onClick={() => setEndId(null)}>Cancel</button>
              <button className="adm-btn-save" onClick={() => handleEnd(endId)}>End Now</button>
            </div>
          </div>
        </div>
      )}

      {/* Delete confirm */}
      {confirmId && (
        <div className="adm-overlay" onClick={() => setConfirmId(null)}>
          <div className="adm-modal adm-modal--sm" onClick={e => e.stopPropagation()}>
            <div className="adm-modal-head">
              <h2>Delete Promotion?</h2>
              <button className="adm-modal-close" onClick={() => setConfirmId(null)}>✕</button>
            </div>
            <div className="adm-modal-body">
              <p style={{ fontSize: '0.875rem', color: '#475569', margin: 0, lineHeight: 1.6 }}>
                This will permanently remove the promotion. This action cannot be undone.
              </p>
            </div>
            <div className="adm-modal-foot">
              <button className="adm-btn-cancel" onClick={() => setConfirmId(null)}>Cancel</button>
              <button className="adm-btn-save ac-btn-danger" onClick={() => handleDelete(confirmId)}>Delete</button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
