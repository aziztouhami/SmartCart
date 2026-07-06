import React, { useState, useEffect, useCallback } from 'react';
import { categoryApi, adminCategoryApi } from '../../services/cartService';
import ImageUpload from '../../components/ImageUpload';
import { uploadImage } from '../../services/uploadService';
import { IconPlus, IconEdit, IconTrash, IconSearch } from '../../components/admin/AdminIcons';
import './AdminCategories.css';

const EMPTY_FORM = { name: '', parentId: '', image: null, seasonalMonths: [] };

const MONTH_LABELS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

function flattenTree(tree) {
  const rows = [];
  for (const parent of tree) {
    rows.push({ id: parent.id, name: parent.name, slug: parent.slug, image: parent.image, seasonalMonths: parent.seasonalMonths || [], parentId: null, parentName: null });
    for (const child of parent.children) {
      rows.push({ id: child.id, name: child.name, slug: child.slug, image: child.image, seasonalMonths: child.seasonalMonths || [], parentId: parent.id, parentName: parent.name });
    }
  }
  return rows;
}

export default function AdminCategories() {
  const [tree, setTree]             = useState([]);
  const [counts, setCounts]         = useState({});
  const [loading, setLoading]       = useState(true);
  const [search, setSearch]         = useState('');
  const [modal, setModal]           = useState(null);
  const [form, setForm]             = useState(EMPTY_FORM);
  const [imageFile, setImageFile]   = useState(null);
  const [imagePreview, setImagePreview] = useState(null);
  const [saving, setSaving]         = useState(false);
  const [errors, setErrors]         = useState({});
  const [confirmId, setConfirmId]   = useState(null);
  const [toast, setToast]           = useState(null);

  const categories = flattenTree(tree);
  const parents = tree.map(p => ({ id: p.id, name: p.name }));

  const filtered = categories.filter(c =>
    c.name.toLowerCase().includes(search.toLowerCase()) ||
    c.slug.toLowerCase().includes(search.toLowerCase())
  );

  const showToast = (msg, type = 'success') => {
    setToast({ msg, type });
    setTimeout(() => setToast(null), 3000);
  };

  const loadCategories = useCallback(() => {
    setLoading(true);
    return categoryApi.list()
      .then(res => {
        const data = res.data || [];
        setTree(data);
        const rows = flattenTree(data);
        return Promise.all(
          rows.map(c =>
            categoryApi.products(c.id, { limit: 1 })
              .then(r => [c.id, r.data.category?.productCount ?? 0])
              .catch(() => [c.id, 0])
          )
        );
      })
      .then(entries => setCounts(Object.fromEntries(entries)))
      .catch(() => showToast('Failed to load categories.', 'error'))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => { loadCategories(); }, [loadCategories]);

  const openAdd = () => {
    setForm(EMPTY_FORM);
    setImageFile(null);
    setImagePreview(null);
    setErrors({});
    setModal({ mode: 'add' });
  };

  const openEdit = (cat) => {
    setForm({ name: cat.name, parentId: cat.parentId ? String(cat.parentId) : '', image: cat.image || null, seasonalMonths: cat.seasonalMonths || [] });
    setImageFile(null);
    setImagePreview(cat.image || null);
    setErrors({});
    setModal({ mode: 'edit', id: cat.id });
  };

  const toggleSeasonalMonth = (month) => {
    setForm(f => ({
      ...f,
      seasonalMonths: f.seasonalMonths.includes(month)
        ? f.seasonalMonths.filter(m => m !== month)
        : [...f.seasonalMonths, month].sort((a, b) => a - b),
    }));
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

  const handleSave = async () => {
    if (!form.name.trim()) {
      setErrors({ name: 'Category name is required.' });
      return;
    }
    setErrors({});
    setSaving(true);
    try {
      let imageUrl = form.image;
      if (imageFile) {
        try {
          imageUrl = await uploadImage(imageFile);
        } catch {
          showToast('Image upload failed. Saving without image.', 'error');
          imageUrl = null;
        }
      }

      const payload = {
        name:           form.name.trim(),
        parentId:       form.parentId ? parseInt(form.parentId) : null,
        image:          imageUrl,
        seasonalMonths: form.seasonalMonths.length ? form.seasonalMonths : null,
      };

      if (modal.mode === 'add') {
        await adminCategoryApi.create(payload);
        showToast('Category added successfully.');
      } else {
        await adminCategoryApi.update(modal.id, payload);
        showToast('Category updated successfully.');
      }
      setModal(null);
      await loadCategories();
    } catch (err) {
      showToast(err.response?.data?.error || 'Failed to save category.', 'error');
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (id) => {
    try {
      await adminCategoryApi.remove(id);
      showToast('Category deleted.');
      await loadCategories();
    } catch (err) {
      showToast(err.response?.data?.error || 'Failed to delete category.', 'error');
    }
    setConfirmId(null);
  };

  return (
    <div className="adm-page">
      {toast && <Toast msg={toast.msg} type={toast.type} />}

      <div className="adm-page-header">
        <div>
          <h1 className="adm-page-title">Categories</h1>
          <p className="adm-page-sub">
            {categories.length} total &nbsp;·&nbsp;
            {parents.length} parent &nbsp;·&nbsp;
            {categories.length - parents.length} subcategories
          </p>
        </div>
        <button className="adm-btn-primary" onClick={openAdd}>
          <IconPlus /> Add Category
        </button>
      </div>

      <div className="adm-toolbar">
        <div className="adm-search">
          <IconSearch />
          <input
            placeholder="Search categories..."
            value={search}
            onChange={e => setSearch(e.target.value)}
          />
        </div>
        {search && (
          <span className="ac-results">{filtered.length} result{filtered.length !== 1 ? 's' : ''}</span>
        )}
      </div>

      <div className="adm-table-wrap">
        <table className="adm-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Image</th>
              <th>Name</th>
              <th>Slug</th>
              <th>Parent</th>
              <th>Season</th>
              <th style={{ textAlign: 'center' }}>Products</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            {loading ? (
              <tr><td colSpan={8}><div className="adm-empty"><p>Loading categories…</p></div></td></tr>
            ) : filtered.length === 0 ? (
              <tr><td colSpan={8}><div className="adm-empty"><p>No categories found.</p></div></td></tr>
            ) : (
              filtered.map((cat, i) => (
                <tr key={cat.id}>
                  <td className="adm-td-muted">{i + 1}</td>
                  <td>
                    {cat.image
                      ? <img src={cat.image} alt={cat.name} className="ac-thumb" />
                      : <div className="ac-thumb-placeholder">—</div>
                    }
                  </td>
                  <td>
                    <div className="ac-name">
                      {cat.parentName && <span className="ac-indent">↳</span>}
                      <span className="ac-label">{cat.name}</span>
                    </div>
                  </td>
                  <td><code className="adm-slug">{cat.slug}</code></td>
                  <td>
                    {cat.parentName
                      ? <span className="adm-parent-badge">{cat.parentName}</span>
                      : <span className="adm-muted">—</span>}
                  </td>
                  <td>
                    {cat.seasonalMonths?.length
                      ? <span className="adm-parent-badge">{cat.seasonalMonths.map(m => MONTH_LABELS[m - 1]).join(', ')}</span>
                      : <span className="adm-muted">—</span>}
                  </td>
                  <td style={{ textAlign: 'center' }}>
                    <span className="ac-count">{counts[cat.id] ?? 0}</span>
                  </td>
                  <td>
                    <div className="adm-actions">
                      <button className="adm-btn-icon adm-btn-edit" onClick={() => openEdit(cat)}>
                        <IconEdit /> Edit
                      </button>
                      <button className="adm-btn-icon adm-btn-delete" onClick={() => setConfirmId(cat.id)}>
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
              <h2>{modal.mode === 'add' ? 'Add New Category' : 'Edit Category'}</h2>
              <button className="adm-modal-close" onClick={() => setModal(null)}>✕</button>
            </div>
            <div className="adm-modal-body">
              <div className="adm-field">
                <label>Category Name *</label>
                <input
                  type="text"
                  placeholder="e.g. Smartphones"
                  value={form.name}
                  onChange={e => setForm(f => ({ ...f, name: e.target.value }))}
                  autoFocus
                />
                {errors.name && <span className="ap-err">{errors.name}</span>}
              </div>
              <div className="adm-field">
                <label>Parent Category</label>
                <select value={form.parentId} onChange={e => setForm(f => ({ ...f, parentId: e.target.value }))}>
                  <option value="">— None (top-level) —</option>
                  {parents.filter(p => p.id !== modal.id).map(p => (
                    <option key={p.id} value={p.id}>{p.name}</option>
                  ))}
                </select>
              </div>
              <div className="adm-field">
                <label>Category Image</label>
                <ImageUpload
                  preview={imagePreview}
                  onFile={handleImageFile}
                  onClear={handleImageClear}
                />
              </div>
              <div className="adm-field">
                <label>Seasonal Boost Months</label>
                <p className="ac-field-hint">Products in this category get a recommendation boost during the selected months.</p>
                <div className="ac-month-grid">
                  {MONTH_LABELS.map((label, i) => {
                    const month = i + 1;
                    const active = form.seasonalMonths.includes(month);
                    return (
                      <button
                        type="button"
                        key={month}
                        className={`ac-month-btn ${active ? 'ac-month-btn--active' : ''}`}
                        onClick={() => toggleSeasonalMonth(month)}
                      >
                        {label}
                      </button>
                    );
                  })}
                </div>
              </div>
            </div>
            <div className="adm-modal-foot">
              <button className="adm-btn-cancel" onClick={() => setModal(null)}>Cancel</button>
              <button className="adm-btn-save" onClick={handleSave} disabled={!form.name.trim() || saving}>
                {saving ? 'Saving…' : (modal.mode === 'add' ? 'Add Category' : 'Save Changes')}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Delete confirm */}
      {confirmId && (
        <div className="adm-overlay" onClick={() => setConfirmId(null)}>
          <div className="adm-modal adm-modal--sm" onClick={e => e.stopPropagation()}>
            <div className="adm-modal-head">
              <h2>Delete Category?</h2>
              <button className="adm-modal-close" onClick={() => setConfirmId(null)}>✕</button>
            </div>
            <div className="adm-modal-body">
              <p className="ac-confirm-msg">
                This will also remove all subcategories linked to this category. This action cannot be undone.
              </p>
            </div>
            <div className="adm-modal-foot">
              <button className="adm-btn-cancel" onClick={() => setConfirmId(null)}>Cancel</button>
              <button className="adm-btn-save ac-btn-danger" onClick={() => handleDelete(confirmId)}>
                Delete
              </button>
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
