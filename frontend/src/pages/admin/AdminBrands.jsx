import React, { useState, useEffect } from 'react';
import { brandApi, adminBrandApi } from '../../services/cartService';
import { formatPrice } from '../../utils/format';
import ImageUpload from '../../components/ImageUpload';
import './AdminBrands.css';

const IconPlus  = () => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>;
const IconEdit  = () => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>;
const IconTrash = () => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>;

const EMPTY = { name: '', description: '', image: null };

function fmtRevenue(amount) {
  return formatPrice(amount) + ' TND';
}

function fmtDate(iso) {
  if (!iso) return '—';
  return new Date(iso).toLocaleDateString('fr-TN', { day: '2-digit', month: 'short', year: 'numeric' });
}

function StarRating({ value }) {
  if (value === null || value === undefined) return <span className="br-no-rating">—</span>;
  return <span className="br-rating">&#9733; {Number(value).toFixed(1)}</span>;
}

export default function AdminBrands() {
  const [brands,       setBrands]       = useState([]);
  const [loading,      setLoading]      = useState(true);
  const [modal,        setModal]        = useState(null);
  const [form,         setForm]         = useState(EMPTY);
  const [imageFile,    setImageFile]    = useState(null);
  const [imagePreview, setImagePreview] = useState(null);
  const [saving,       setSaving]       = useState(false);
  const [errors,       setErrors]       = useState({});
  const [confirmId,    setConfirmId]    = useState(null);
  const [toast,        setToast]        = useState(null);

  const showToast = (msg, type = 'success') => {
    setToast({ msg, type });
    setTimeout(() => setToast(null), 3000);
  };

  const loadBrands = () => {
    setLoading(true);
    brandApi.list(1, 100)
      .then(res => setBrands(res.data.data || []))
      .catch(() => showToast('Failed to load brands.', 'error'))
      .finally(() => setLoading(false));
  };

  useEffect(() => { loadBrands(); }, []); // eslint-disable-line react-hooks/exhaustive-deps

  const openAdd = () => {
    setForm(EMPTY);
    setErrors({});
    setImageFile(null);
    setImagePreview(null);
    setModal({ mode: 'add' });
  };

  const openEdit = (brand) => {
    setForm({ name: brand.name, description: brand.description || '', image: brand.image || null });
    setErrors({});
    setImageFile(null);
    setImagePreview(brand.image || null);
    setModal({ mode: 'edit', brand });
  };

  const handleImageFile = (file) => {
    setImageFile(file);
    setImagePreview(URL.createObjectURL(file));
  };

  const handleImageClear = () => {
    setImageFile(null);
    setImagePreview(null);
    setForm(prev => ({ ...prev, image: null }));
  };

  const validate = () => {
    const e = {};
    if (!form.name.trim()) e.name = 'Name is required.';
    return e;
  };

  const handleSave = async () => {
    const e = validate();
    if (Object.keys(e).length) { setErrors(e); return; }
    setSaving(true);
    try {
      // form.image tracks the current intent: the existing URL, or null if
      // the admin removed it. imageFile (a newly picked file) wins over both.
      let imageUrl = form.image;

      if (imageFile) {
        const uploadRes = await adminBrandApi.uploadImage(imageFile);
        imageUrl = uploadRes.data.url;
      }

      const payload = {
        name:        form.name.trim(),
        image:       imageUrl,
        description: form.description.trim() || null,
      };

      if (modal.mode === 'add') {
        const res = await adminBrandApi.create(payload);
        setBrands(prev => [...prev, { ...res.data, productCount: 0, soldCount: 0, revenue: 0, avgRating: null }]);
        showToast('Brand added successfully.');
      } else {
        const res = await adminBrandApi.update(modal.brand.id, payload);
        setBrands(prev => prev.map(b => b.id === modal.brand.id ? { ...b, ...res.data } : b));
        showToast('Brand updated successfully.');
      }
      setModal(null);
    } catch (err) {
      showToast(err.response?.data?.error || 'Failed to save brand.', 'error');
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (id) => {
    try {
      await adminBrandApi.remove(id);
      setBrands(prev => prev.filter(b => b.id !== id));
      showToast('Brand deleted.');
    } catch {
      showToast('Failed to delete brand.', 'error');
    }
    setConfirmId(null);
  };

  return (
    <div className="adm-page">

      {toast && <Toast msg={toast.msg} type={toast.type} />}

      <div className="adm-page-header">
        <div>
          <h1 className="adm-page-title">Brands</h1>
          <p className="adm-page-sub">{brands.length} brand{brands.length !== 1 ? 's' : ''} total</p>
        </div>
        <button className="adm-btn-primary" onClick={openAdd}>
          <IconPlus /> Add Brand
        </button>
      </div>

      <div className="br-table-wrap">
        <table className="br-table">
          <thead>
            <tr>
              <th>Brand</th>
              <th>Description</th>
              <th>Joined</th>
              <th className="br-center">Products</th>
              <th className="br-center">Sold</th>
              <th>Revenue</th>
              <th className="br-center">Avg Rating</th>
              <th className="br-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            {loading ? (
              <tr><td colSpan={8}><div className="adm-empty"><p>Loading brands…</p></div></td></tr>
            ) : brands.length === 0 ? (
              <tr><td colSpan={8}><div className="adm-empty"><p>No brands yet. Click "Add Brand" to create one.</p></div></td></tr>
            ) : (
              brands.map(brand => (
                <tr key={brand.id} className="br-row">
                  <td className="br-brand-cell">
                    {brand.image ? (
                      <img
                        src={brand.image}
                        alt={brand.name}
                        className="br-logo-img"
                        onError={e => { e.target.style.display = 'none'; e.target.nextSibling.style.display = 'flex'; }}
                      />
                    ) : null}
                    <div className="br-logo-placeholder" style={{ display: brand.image ? 'none' : 'flex' }}>
                      {brand.name.charAt(0).toUpperCase()}
                    </div>
                    <span className="br-brand-name">{brand.name}</span>
                  </td>
                  <td className="br-desc">
                    {brand.description
                      ? brand.description.length > 60 ? brand.description.slice(0, 60) + '…' : brand.description
                      : <span className="br-muted">—</span>}
                  </td>
                  <td className="br-date">{fmtDate(brand.joinedAt)}</td>
                  <td className="br-center"><span className="br-stat-badge">{brand.productCount}</span></td>
                  <td className="br-center"><span className="br-stat-badge">{brand.soldCount}</span></td>
                  <td className="br-revenue">{fmtRevenue(brand.revenue)}</td>
                  <td className="br-center"><StarRating value={brand.avgRating} /></td>
                  <td className="br-center">
                    <div className="adm-actions">
                      <button className="adm-btn-icon adm-btn-edit" onClick={() => openEdit(brand)}>
                        <IconEdit /> Edit
                      </button>
                      <button className="adm-btn-icon adm-btn-delete" onClick={() => setConfirmId(brand.id)}>
                        <IconTrash /> Delete
                      </button>
                    </div>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      {/* Add / Edit Modal */}
      {modal && (
        <div className="adm-overlay" onClick={() => setModal(null)}>
          <div className="adm-modal" onClick={e => e.stopPropagation()}>
            <div className="adm-modal-head">
              <h2>{modal.mode === 'add' ? 'Add New Brand' : 'Edit Brand'}</h2>
              <button className="adm-modal-close" onClick={() => setModal(null)}>✕</button>
            </div>

            <div className="adm-modal-body">
              <div className="adm-field">
                <label>Brand Name *</label>
                <input
                  type="text"
                  placeholder="e.g. Nike"
                  value={form.name}
                  onChange={e => setForm(f => ({ ...f, name: e.target.value }))}
                  autoFocus
                />
                {errors.name && <span className="ap-err">{errors.name}</span>}
              </div>

              <div className="adm-field">
                <label>Logo / Image</label>
                <ImageUpload
                  preview={imagePreview}
                  onFile={handleImageFile}
                  onClear={handleImageClear}
                />
              </div>

              <div className="adm-field">
                <label>Description</label>
                <textarea
                  rows={3}
                  placeholder="Brief description of the brand…"
                  value={form.description}
                  onChange={e => setForm(f => ({ ...f, description: e.target.value }))}
                />
              </div>
            </div>

            <div className="adm-modal-foot">
              <button className="adm-btn-cancel" onClick={() => setModal(null)} disabled={saving}>Cancel</button>
              <button className="adm-btn-save" onClick={handleSave} disabled={saving}>
                {saving ? 'Saving…' : modal.mode === 'add' ? 'Add Brand' : 'Save Changes'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Delete confirmation */}
      {confirmId !== null && (
        <div className="adm-overlay" onClick={() => setConfirmId(null)}>
          <div className="adm-modal adm-modal--sm" onClick={e => e.stopPropagation()}>
            <div className="adm-modal-head">
              <h2>Delete Brand?</h2>
              <button className="adm-modal-close" onClick={() => setConfirmId(null)}>✕</button>
            </div>
            <div className="adm-modal-body">
              <p style={{ fontSize: '0.875rem', color: '#475569', margin: 0, lineHeight: 1.6 }}>
                Delete this brand? Products assigned to it will have their brand cleared. This action cannot be undone.
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

function Toast({ msg, type }) {
  return <div className={`ac-toast ac-toast--${type}`}>{msg}</div>;
}
