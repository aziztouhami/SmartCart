import React, { useState, useEffect, useCallback } from 'react';
import { adminProductApi, brandApi, productTypeApi } from '../../services/cartService';
import { fetchAllProducts } from '../../utils/fetchAllProducts';
import { useCategories } from '../../context/CategoryContext';
import ImageUpload from '../../components/ImageUpload';
import { uploadImage } from '../../services/uploadService';
import { formatPrice } from '../../utils/format';
import { EMPTY_FEATURE, buildAttributesPayload, parseOptions, FeatureRowEditor, AttributeValueInput } from '../../components/admin/TypeFeatureFields';
import { IconPlus, IconEdit, IconTrash, IconSearch } from '../../components/admin/AdminIcons';
import './AdminProducts.css';

const EMPTY_FORM = { name: '', description: '', price: '', stock: '', categoryId: '', brandId: '', productTypeId: '', attributes: {}, image: null };

function stockStatus(stock) {
  if (stock === 0)   return { label: 'Out of Stock', cls: 'ap-badge--out' };
  if (stock <= 15)   return { label: 'Low Stock',    cls: 'ap-badge--low' };
  return               { label: 'In Stock',      cls: 'ap-badge--ok'  };
}

export default function AdminProducts() {
  const [products, setProducts]       = useState([]);
  const { leafCategories }            = useCategories();
  const [brands, setBrands]           = useState([]);
  const [productTypes, setProductTypes] = useState([]);
  const [loading, setLoading]         = useState(true);
  const [search, setSearch]           = useState('');
  const [filter, setFilter]           = useState('all');
  const [modal, setModal]             = useState(null);
  const [form, setForm]               = useState(EMPTY_FORM);
  const [imageFile, setImageFile]     = useState(null);
  const [imagePreview, setImagePreview] = useState(null);
  const [saving, setSaving]           = useState(false);
  const [errors, setErrors]           = useState({});
  const [confirmId, setConfirmId]     = useState(null);
  const [toast, setToast]             = useState(null);

  // "Create a new type" inline panel
  const [creatingType, setCreatingType]     = useState(false);
  const [newTypeName, setNewTypeName]       = useState('');
  const [newTypeFeatures, setNewTypeFeatures] = useState([]);
  const [typeSaving, setTypeSaving]         = useState(false);
  const [typeError, setTypeError]           = useState('');

  // "Add a feature to the selected type" inline panel
  const [addingFeature, setAddingFeature]   = useState(false);
  const [newFeature, setNewFeature]         = useState(EMPTY_FEATURE);
  const [featureSaving, setFeatureSaving]   = useState(false);

  const selectedType = productTypes.find(t => String(t.id) === String(form.productTypeId)) || null;

  const showToast = (msg, type = 'success') => {
    setToast({ msg, type });
    setTimeout(() => setToast(null), 3000);
  };

  const loadProducts = useCallback(() => {
    setLoading(true);
    return fetchAllProducts()
      .then(setProducts)
      .catch(() => showToast('Failed to load products.', 'error'))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    loadProducts();

    brandApi.list(1, 100)
      .then(res => setBrands(res.data.data || []))
      .catch(() => {});

    productTypeApi.list()
      .then(res => setProductTypes(res.data || []))
      .catch(() => {});
  }, [loadProducts]);

  const resetTypePanels = () => {
    setCreatingType(false);
    setNewTypeName('');
    setNewTypeFeatures([]);
    setTypeError('');
    setAddingFeature(false);
    setNewFeature(EMPTY_FEATURE);
  };

  const openAdd = () => {
    setForm(EMPTY_FORM);
    setImageFile(null);
    setImagePreview(null);
    setErrors({});
    resetTypePanels();
    setModal({ mode: 'add' });
  };

  const openEdit = (p) => {
    setForm({
      name: p.name,
      description: p.description || '',
      price: String(p.price),
      stock: String(p.stock),
      categoryId: p.category?.id ? String(p.category.id) : '',
      brandId: p.brand?.id ? String(p.brand.id) : '',
      productTypeId: p.productType?.id ? String(p.productType.id) : '',
      attributes: p.attributes || {},
      image: p.images?.[0] || null,
    });
    setImageFile(null);
    setImagePreview(p.images?.[0] || null);
    setErrors({});
    resetTypePanels();
    setModal({ mode: 'edit', id: p.id });
  };

  const closeModal = () => {
    setModal(null);
    resetTypePanels();
  };

  const handleTypeSelect = (value) => {
    if (value === '__new__') {
      setCreatingType(true);
      setForm(f => ({ ...f, productTypeId: '', attributes: {} }));
    } else {
      setCreatingType(false);
      setForm(f => ({ ...f, productTypeId: value, attributes: {} }));
    }
  };

  const handleCreateType = async () => {
    setTypeError('');
    if (!newTypeName.trim()) {
      setTypeError('Type name is required.');
      return;
    }

    const cleanFeatures = newTypeFeatures
      .filter(f => f.name.trim())
      .map(f => ({
        name: f.name.trim(),
        dataType: f.dataType,
        unit: f.dataType === 'number' ? (f.unit.trim() || null) : null,
        options: f.dataType === 'select' ? parseOptions(f.options) : null,
        required: !!f.required,
      }));

    setTypeSaving(true);
    try {
      const res = await productTypeApi.create({ name: newTypeName.trim(), attributes: cleanFeatures });
      const created = res.data;
      setProductTypes(prev => [...prev, created]);
      setForm(f => ({ ...f, productTypeId: String(created.id), attributes: {} }));
      setCreatingType(false);
      setNewTypeName('');
      setNewTypeFeatures([]);
      showToast(`Type "${created.name}" created.`);
    } catch (err) {
      setTypeError(err.response?.data?.error || 'Failed to create type.');
    } finally {
      setTypeSaving(false);
    }
  };

  const handleAddFeature = async () => {
    if (!selectedType || !newFeature.name.trim()) return;

    const payload = {
      name: newFeature.name.trim(),
      dataType: newFeature.dataType,
      unit: newFeature.dataType === 'number' ? (newFeature.unit.trim() || null) : null,
      options: newFeature.dataType === 'select' ? parseOptions(newFeature.options) : null,
      required: !!newFeature.required,
    };

    setFeatureSaving(true);
    try {
      const res = await productTypeApi.addAttribute(selectedType.id, payload);
      const updated = res.data;
      setProductTypes(prev => prev.map(t => (t.id === updated.id ? updated : t)));
      setAddingFeature(false);
      setNewFeature(EMPTY_FEATURE);
      showToast('Feature added.');
    } catch (err) {
      showToast(err.response?.data?.error || 'Failed to add feature.', 'error');
    } finally {
      setFeatureSaving(false);
    }
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
    if (!form.name.trim())          e.name = 'Name is required.';
    if (!form.categoryId)           e.categoryId = 'Please select a category.';
    if (!form.price || isNaN(parseFloat(form.price)) || parseFloat(form.price) < 0)
                                    e.price = 'Enter a valid price.';
    if (!form.stock || isNaN(parseInt(form.stock)) || parseInt(form.stock) < 0)
                                    e.stock = 'Enter a valid stock quantity.';
    return e;
  };

  const handleSave = async () => {
    const e = validate();
    if (Object.keys(e).length) { setErrors(e); return; }

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
        name:          form.name.trim(),
        description:   form.description.trim() || null,
        price:         parseFloat(form.price),
        stock:         parseInt(form.stock),
        categoryId:    parseInt(form.categoryId),
        brandId:       form.brandId ? parseInt(form.brandId) : null,
        productTypeId: form.productTypeId ? parseInt(form.productTypeId) : null,
        // On edit, "no type selected" must mean "leave attributes untouched" (null),
        // not "clear them" ({}) — the backend treats an empty object as an explicit
        // clear and rejects it if the product still has required features.
        attributes: (!selectedType && modal.mode === 'edit')
          ? null
          : buildAttributesPayload(selectedType, form.attributes),
        images:        imageUrl ? [imageUrl] : [],
      };

      if (modal.mode === 'add') {
        await adminProductApi.create(payload);
        showToast('Product added successfully.');
      } else {
        await adminProductApi.update(modal.id, payload);
        showToast('Product updated successfully.');
      }
      closeModal();
      await loadProducts();
    } catch (err) {
      showToast(err.response?.data?.error || 'Failed to save product.', 'error');
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (id) => {
    try {
      await adminProductApi.remove(id);
      setProducts(prev => prev.filter(p => p.id !== id));
      showToast('Product deleted.');
    } catch {
      showToast('Failed to delete product.', 'error');
    }
    setConfirmId(null);
  };

  const filtered = products.filter(p => {
    const matchSearch = p.name.toLowerCase().includes(search.toLowerCase()) ||
                        (p.category?.name || '').toLowerCase().includes(search.toLowerCase()) ||
                        (p.brand?.name || '').toLowerCase().includes(search.toLowerCase());
    const matchFilter =
      filter === 'all' ? true :
      filter === 'low' ? p.stock > 0 && p.stock <= 15 :
      filter === 'out' ? p.stock === 0 : true;
    return matchSearch && matchFilter;
  });

  return (
    <div className="adm-page">
      {toast && <Toast msg={toast.msg} type={toast.type} />}

      <div className="adm-page-header">
        <div>
          <h1 className="adm-page-title">Products</h1>
          <p className="adm-page-sub">
            {products.length} total &nbsp;·&nbsp;
            {products.filter(p => p.stock > 0 && p.stock <= 15).length} low stock &nbsp;·&nbsp;
            {products.filter(p => p.stock === 0).length} out of stock
          </p>
        </div>
        <button className="adm-btn-primary" onClick={openAdd}>
          <IconPlus /> Add Product
        </button>
      </div>

      <div className="adm-toolbar">
        <div className="adm-search">
          <IconSearch />
          <input
            placeholder="Search products, categories or brands..."
            value={search}
            onChange={e => setSearch(e.target.value)}
          />
        </div>
        <div className="ap-filters">
          {[
            { key: 'all', label: 'All' },
            { key: 'low', label: 'Low Stock' },
            { key: 'out', label: 'Out of Stock' },
          ].map(f => (
            <button
              key={f.key}
              className={`ap-filter-btn${filter === f.key ? ' ap-filter-btn--active' : ''}`}
              onClick={() => setFilter(f.key)}
            >
              {f.label}
            </button>
          ))}
        </div>
      </div>

      <div className="adm-table-wrap">
        <table className="adm-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Image</th>
              <th>Product Name</th>
              <th>Category</th>
              <th>Brand</th>
              <th>Price (TND)</th>
              <th style={{ textAlign: 'center' }}>Stock</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            {loading ? (
              <tr><td colSpan={9}><div className="adm-empty"><p>Loading products…</p></div></td></tr>
            ) : filtered.length === 0 ? (
              <tr><td colSpan={9}><div className="adm-empty"><p>No products match your search.</p></div></td></tr>
            ) : (
              filtered.map((p, i) => {
                const { label, cls } = stockStatus(p.stock);
                const image = p.images?.[0];
                return (
                  <tr key={p.id}>
                    <td className="adm-td-muted">{i + 1}</td>
                    <td>
                      {image
                        ? <img src={image} alt={p.name} className="ap-thumb" />
                        : <div className="ap-thumb-placeholder">—</div>
                      }
                    </td>
                    <td>
                      <div className="ap-product-name">{p.name}</div>
                      {p.description && (
                        <div className="ap-product-desc">{p.description.slice(0, 60)}…</div>
                      )}
                    </td>
                    <td><span className="adm-parent-badge">{p.category?.name || '—'}</span></td>
                    <td>
                      {p.brand ? (
                        <span className="ap-brand-cell">
                          {p.brand.image && <img src={p.brand.image} alt={p.brand.name} className="ap-brand-logo" />}
                          {p.brand.name}
                        </span>
                      ) : <span className="adm-muted">—</span>}
                    </td>
                    <td><span className="ap-price">{formatPrice(p.price)}</span></td>
                    <td style={{ textAlign: 'center' }}>
                      <span className={p.stock <= 15 ? (p.stock === 0 ? 'adm-stock-out' : 'adm-stock-low') : 'adm-stock-ok'}>
                        {p.stock}
                      </span>
                    </td>
                    <td><span className={`ap-badge ${cls}`}>{label}</span></td>
                    <td>
                      <div className="adm-actions">
                        <button className="adm-btn-icon adm-btn-edit" onClick={() => openEdit(p)}>
                          <IconEdit /> Edit
                        </button>
                        <button className="adm-btn-icon adm-btn-delete" onClick={() => setConfirmId(p.id)}>
                          <IconTrash /> Delete
                        </button>
                      </div>
                    </td>
                  </tr>
                );
              })
            )}
          </tbody>
        </table>
      </div>

      {/* Add / Edit Modal */}
      {modal && (
        <div className="adm-overlay" onClick={closeModal}>
          <div className="adm-modal ap-modal-wide" onClick={e => e.stopPropagation()}>
            <div className="adm-modal-head">
              <h2>{modal.mode === 'add' ? 'Add New Product' : 'Edit Product'}</h2>
              <button className="adm-modal-close" onClick={closeModal}>✕</button>
            </div>
            <div className="adm-modal-body ap-form-body">

              <section className="ap-section">
                <div className="ap-hero-row">
                  <div className="ap-image-slot">
                    <label>Photo</label>
                    <ImageUpload
                      preview={imagePreview}
                      onFile={handleImageFile}
                      onClear={handleImageClear}
                    />
                  </div>
                  <div className="ap-hero-fields">
                    <div className="adm-field">
                      <label>Product Name *</label>
                      <input
                        type="text"
                        placeholder="e.g. Apple iPhone 15 128GB"
                        value={form.name}
                        onChange={e => setForm(f => ({ ...f, name: e.target.value }))}
                        autoFocus
                      />
                      {errors.name && <span className="ap-err">{errors.name}</span>}
                    </div>
                    <div className="adm-field">
                      <label>Description</label>
                      <textarea
                        placeholder="Brief product description..."
                        value={form.description}
                        onChange={e => setForm(f => ({ ...f, description: e.target.value }))}
                      />
                    </div>
                  </div>
                </div>
              </section>

              <section className="ap-section">
                <h3 className="ap-section-title">Pricing &amp; Inventory</h3>
                <div className="adm-field-row">
                  <div className="adm-field">
                    <label>Price (TND) *</label>
                    <input
                      type="number"
                      min="0"
                      step="0.01"
                      placeholder="0.00"
                      value={form.price}
                      onChange={e => setForm(f => ({ ...f, price: e.target.value }))}
                    />
                    {errors.price && <span className="ap-err">{errors.price}</span>}
                  </div>
                  <div className="adm-field">
                    <label>Stock Quantity *</label>
                    <input
                      type="number"
                      min="0"
                      step="1"
                      placeholder="0"
                      value={form.stock}
                      onChange={e => setForm(f => ({ ...f, stock: e.target.value }))}
                    />
                    {errors.stock && <span className="ap-err">{errors.stock}</span>}
                  </div>
                </div>
              </section>

              <section className="ap-section">
                <h3 className="ap-section-title">Organization</h3>
                <div className="adm-field-row">
                  <div className="adm-field">
                    <label>Category *</label>
                    <select
                      value={form.categoryId}
                      onChange={e => setForm(f => ({ ...f, categoryId: e.target.value }))}
                    >
                      <option value="">— Select a category —</option>
                      {leafCategories.map(c => (
                        <option key={c.id} value={c.id}>{c.parentName} › {c.name}</option>
                      ))}
                    </select>
                    {errors.categoryId && <span className="ap-err">{errors.categoryId}</span>}
                  </div>
                  <div className="adm-field">
                    <label>Brand</label>
                    <select
                      value={form.brandId}
                      onChange={e => setForm(f => ({ ...f, brandId: e.target.value }))}
                    >
                      <option value="">— No brand —</option>
                      {brands.map(b => (
                        <option key={b.id} value={b.id}>{b.name}</option>
                      ))}
                    </select>
                  </div>
                </div>

                <div className="adm-field">
                  <label>Product Type</label>
                  <select
                    value={creatingType ? '__new__' : form.productTypeId}
                    onChange={e => handleTypeSelect(e.target.value)}
                  >
                    <option value="">— No type —</option>
                    {productTypes.map(t => (
                      <option key={t.id} value={t.id}>{t.name}</option>
                    ))}
                    <option value="__new__">+ Create new type…</option>
                  </select>
                  <span className="adm-field-hint">
                    Pick a type to fill in its technical features below, e.g. Color or Battery for a Smartphone.
                  </span>
                </div>

                {creatingType && (
                  <div className="tff-type-create">
                    <div className="adm-field">
                      <label>New Type Name *</label>
                      <input
                        type="text"
                        placeholder="e.g. Smartwatch"
                        value={newTypeName}
                        onChange={e => setNewTypeName(e.target.value)}
                      />
                    </div>
                    <label className="tff-feature-list-label">Features</label>
                    {newTypeFeatures.map((feat, idx) => (
                      <FeatureRowEditor
                        key={idx}
                        feature={feat}
                        onChange={updated => setNewTypeFeatures(rows => rows.map((r, i) => (i === idx ? updated : r)))}
                        onRemove={() => setNewTypeFeatures(rows => rows.filter((_, i) => i !== idx))}
                      />
                    ))}
                    <button
                      type="button"
                      className="tff-add-feature-btn"
                      onClick={() => setNewTypeFeatures(rows => [...rows, { ...EMPTY_FEATURE }])}
                    >
                      + Add feature
                    </button>
                    {typeError && <span className="ap-err">{typeError}</span>}
                    <div className="tff-type-create-actions">
                      <button
                        type="button"
                        className="adm-btn-cancel"
                        onClick={() => { setCreatingType(false); setNewTypeName(''); setNewTypeFeatures([]); setTypeError(''); }}
                      >
                        Cancel
                      </button>
                      <button type="button" className="adm-btn-save" disabled={typeSaving} onClick={handleCreateType}>
                        {typeSaving ? 'Creating…' : 'Create Type'}
                      </button>
                    </div>
                  </div>
                )}
              </section>

              {selectedType && !creatingType && (
                <section className="ap-section ap-section--last">
                  <h3 className="ap-section-title">{selectedType.name} Features</h3>
                  <div className="ap-feature-grid">
                    {selectedType.attributes.map(attr => (
                      <div className="adm-field" key={attr.slug}>
                        <label>
                          {attr.name}{attr.required ? ' *' : ''}{attr.unit ? ` (${attr.unit})` : ''}
                        </label>
                        <AttributeValueInput
                          attr={attr}
                          value={form.attributes[attr.slug]}
                          onChange={val => setForm(f => ({ ...f, attributes: { ...f.attributes, [attr.slug]: val } }))}
                        />
                      </div>
                    ))}
                  </div>

                  {!addingFeature ? (
                    <button type="button" className="tff-add-feature-link" onClick={() => setAddingFeature(true)}>
                      + Add a new feature to "{selectedType.name}"
                    </button>
                  ) : (
                    <div className="tff-type-create">
                      <FeatureRowEditor feature={newFeature} onChange={setNewFeature} />
                      <div className="tff-type-create-actions">
                        <button
                          type="button"
                          className="adm-btn-cancel"
                          onClick={() => { setAddingFeature(false); setNewFeature(EMPTY_FEATURE); }}
                        >
                          Cancel
                        </button>
                        <button type="button" className="adm-btn-save" disabled={featureSaving} onClick={handleAddFeature}>
                          {featureSaving ? 'Adding…' : 'Add Feature'}
                        </button>
                      </div>
                    </div>
                  )}
                </section>
              )}

            </div>
            <div className="adm-modal-foot">
              <button className="adm-btn-cancel" onClick={closeModal}>Cancel</button>
              <button className="adm-btn-save" onClick={handleSave} disabled={saving}>
                {saving ? 'Saving…' : (modal.mode === 'add' ? 'Add Product' : 'Save Changes')}
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
              <h2>Delete Product?</h2>
              <button className="adm-modal-close" onClick={() => setConfirmId(null)}>✕</button>
            </div>
            <div className="adm-modal-body">
              <p style={{ fontSize: '0.875rem', color: '#475569', margin: 0, lineHeight: 1.6 }}>
                This product will be permanently removed from the catalog. This action cannot be undone.
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
