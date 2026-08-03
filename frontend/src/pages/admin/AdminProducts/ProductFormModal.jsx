import React, { useState } from 'react';
import { adminProductApi, productTypeApi } from '../../../services/cartService';
import ImageUpload from '../../../components/ImageUpload';
import { uploadImage } from '../../../services/uploadService';
import {
  EMPTY_FEATURE,
  buildAttributesPayload,
  parseOptions,
  FeatureRowEditor,
  AttributeValueInput,
} from '../../../components/admin/TypeFeatureFields';

function emptyForm() {
  return {
    name: '',
    description: '',
    price: '',
    stock: '',
    categoryId: '',
    brandId: '',
    productTypeId: '',
    attributes: {},
    image: null,
  };
}

function formFromProduct(p) {
  return {
    name: p.name,
    description: p.description || '',
    price: String(p.price),
    stock: String(p.stock),
    categoryId: p.category?.id ? String(p.category.id) : '',
    brandId: p.brand?.id ? String(p.brand.id) : '',
    productTypeId: p.productType?.id ? String(p.productType.id) : '',
    attributes: p.attributes || {},
    image: p.images?.[0] || null,
  };
}

export default function ProductFormModal({
  mode,
  product,
  leafCategories,
  brands,
  productTypes,
  setProductTypes,
  onClose,
  onSaved,
  showToast,
}) {
  const [form, setForm] = useState(mode === 'edit' ? formFromProduct(product) : emptyForm());
  const [imageFile, setImageFile] = useState(null);
  const [imagePreview, setImagePreview] = useState(
    mode === 'edit' ? product.images?.[0] || null : null,
  );
  const [errors, setErrors] = useState({});
  const [saving, setSaving] = useState(false);

  // "Create a new type" inline panel
  const [creatingType, setCreatingType] = useState(false);
  const [newTypeName, setNewTypeName] = useState('');
  const [newTypeFeatures, setNewTypeFeatures] = useState([]);
  const [typeSaving, setTypeSaving] = useState(false);
  const [typeError, setTypeError] = useState('');

  // "Add a feature to the selected type" inline panel
  const [addingFeature, setAddingFeature] = useState(false);
  const [newFeature, setNewFeature] = useState(EMPTY_FEATURE);
  const [featureSaving, setFeatureSaving] = useState(false);

  const selectedType = productTypes.find(t => String(t.id) === String(form.productTypeId)) || null;

  const handleTypeSelect = value => {
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
        unit: f.dataType === 'number' ? f.unit.trim() || null : null,
        options: f.dataType === 'select' ? parseOptions(f.options) : null,
        required: !!f.required,
      }));

    setTypeSaving(true);
    try {
      const res = await productTypeApi.create({
        name: newTypeName.trim(),
        attributes: cleanFeatures,
      });
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
      unit: newFeature.dataType === 'number' ? newFeature.unit.trim() || null : null,
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

  const handleImageFile = file => {
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
    if (!form.categoryId) e.categoryId = 'Please select a category.';
    if (!form.price || isNaN(parseFloat(form.price)) || parseFloat(form.price) < 0)
      e.price = 'Enter a valid price.';
    if (!form.stock || isNaN(parseInt(form.stock)) || parseInt(form.stock) < 0)
      e.stock = 'Enter a valid stock quantity.';
    return e;
  };

  const handleSave = async () => {
    const e = validate();
    if (Object.keys(e).length) {
      setErrors(e);
      return;
    }

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
        name: form.name.trim(),
        description: form.description.trim() || null,
        price: parseFloat(form.price),
        stock: parseInt(form.stock),
        categoryId: parseInt(form.categoryId),
        brandId: form.brandId ? parseInt(form.brandId) : null,
        productTypeId: form.productTypeId ? parseInt(form.productTypeId) : null,
        // On edit, "no type selected" must mean "leave attributes untouched" (null),
        // not "clear them" ({}) — the backend treats an empty object as an explicit
        // clear and rejects it if the product still has required features.
        attributes:
          !selectedType && mode === 'edit'
            ? null
            : buildAttributesPayload(selectedType, form.attributes),
        images: imageUrl ? [imageUrl] : [],
      };

      if (mode === 'add') {
        await adminProductApi.create(payload);
        showToast('Product added successfully.');
      } else {
        await adminProductApi.update(product.id, payload);
        showToast('Product updated successfully.');
      }
      onSaved();
    } catch (err) {
      showToast(err.response?.data?.error || 'Failed to save product.', 'error');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="adm-overlay" onClick={onClose}>
      <div className="adm-modal ap-modal-wide" onClick={e => e.stopPropagation()}>
        <div className="adm-modal-head">
          <h2>{mode === 'add' ? 'Add New Product' : 'Edit Product'}</h2>
          <button className="adm-modal-close" onClick={onClose}>
            ✕
          </button>
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
                    <option key={c.id} value={c.id}>
                      {c.parentName} › {c.name}
                    </option>
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
                    <option key={b.id} value={b.id}>
                      {b.name}
                    </option>
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
                  <option key={t.id} value={t.id}>
                    {t.name}
                  </option>
                ))}
                <option value="__new__">+ Create new type…</option>
              </select>
              <span className="adm-field-hint">
                Pick a type to fill in its technical features below, e.g. Color or Battery for a
                Smartphone.
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
                    onChange={updated =>
                      setNewTypeFeatures(rows => rows.map((r, i) => (i === idx ? updated : r)))
                    }
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
                    onClick={() => {
                      setCreatingType(false);
                      setNewTypeName('');
                      setNewTypeFeatures([]);
                      setTypeError('');
                    }}
                  >
                    Cancel
                  </button>
                  <button
                    type="button"
                    className="adm-btn-save"
                    disabled={typeSaving}
                    onClick={handleCreateType}
                  >
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
                      {attr.name}
                      {attr.required ? ' *' : ''}
                      {attr.unit ? ` (${attr.unit})` : ''}
                    </label>
                    <AttributeValueInput
                      attr={attr}
                      value={form.attributes[attr.slug]}
                      onChange={val =>
                        setForm(f => ({
                          ...f,
                          attributes: { ...f.attributes, [attr.slug]: val },
                        }))
                      }
                    />
                  </div>
                ))}
              </div>

              {!addingFeature ? (
                <button
                  type="button"
                  className="tff-add-feature-link"
                  onClick={() => setAddingFeature(true)}
                >
                  + Add a new feature to "{selectedType.name}"
                </button>
              ) : (
                <div className="tff-type-create">
                  <FeatureRowEditor feature={newFeature} onChange={setNewFeature} />
                  <div className="tff-type-create-actions">
                    <button
                      type="button"
                      className="adm-btn-cancel"
                      onClick={() => {
                        setAddingFeature(false);
                        setNewFeature(EMPTY_FEATURE);
                      }}
                    >
                      Cancel
                    </button>
                    <button
                      type="button"
                      className="adm-btn-save"
                      disabled={featureSaving}
                      onClick={handleAddFeature}
                    >
                      {featureSaving ? 'Adding…' : 'Add Feature'}
                    </button>
                  </div>
                </div>
              )}
            </section>
          )}
        </div>
        <div className="adm-modal-foot">
          <button className="adm-btn-cancel" onClick={onClose}>
            Cancel
          </button>
          <button className="adm-btn-save" onClick={handleSave} disabled={saving}>
            {saving ? 'Saving…' : mode === 'add' ? 'Add Product' : 'Save Changes'}
          </button>
        </div>
      </div>
    </div>
  );
}
