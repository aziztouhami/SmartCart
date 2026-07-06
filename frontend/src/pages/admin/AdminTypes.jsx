import React, { useState, useEffect } from 'react';
import { productTypeApi } from '../../services/cartService';
import { EMPTY_FEATURE, parseOptions, FeatureRowEditor } from '../../components/admin/TypeFeatureFields';
import { IconPlus, IconEdit, IconTrash, IconSearch } from '../../components/admin/AdminIcons';
import './AdminTypes.css';

const IconSparkles = () => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 3v4M12 17v4M3 12h4M17 12h4M5.6 5.6l2.8 2.8M15.6 15.6l2.8 2.8M18.4 5.6l-2.8 2.8M8.4 15.6l-2.8 2.8"/></svg>;

const DATA_TYPE_LABEL = { text: 'Text', number: 'Number', boolean: 'Yes/No', select: 'Choice list' };

export default function AdminTypes() {
  const [types, setTypes]       = useState([]);
  const [loading, setLoading]   = useState(true);
  const [search, setSearch]     = useState('');
  const [modal, setModal]       = useState(null);
  const [saving, setSaving]     = useState(false);
  const [confirmId, setConfirmId] = useState(null);
  const [toast, setToast]       = useState(null);

  // Add-type modal state
  const [newTypeName, setNewTypeName]     = useState('');
  const [newFeatures, setNewFeatures]     = useState([]);
  const [error, setError]                 = useState('');
  const [suggesting, setSuggesting]       = useState(false);

  // Edit-type modal state
  const [editName, setEditName]           = useState('');
  const [addingFeature, setAddingFeature] = useState(false);
  const [newFeature, setNewFeature]       = useState(EMPTY_FEATURE);
  const [featureSaving, setFeatureSaving] = useState(false);
  const [removingId, setRemovingId]       = useState(null);
  const [suggestedFeatures, setSuggestedFeatures] = useState([]);
  const [suggestingEdit, setSuggestingEdit]       = useState(false);
  const [addingSuggested, setAddingSuggested]     = useState(false);

  const showToast = (msg, type = 'success') => {
    setToast({ msg, type });
    setTimeout(() => setToast(null), 3000);
  };

  const loadTypes = () => {
    setLoading(true);
    return productTypeApi.list()
      .then(res => setTypes(res.data || []))
      .catch(() => showToast('Failed to load product types.', 'error'))
      .finally(() => setLoading(false));
  };

  useEffect(() => { loadTypes(); }, []); // eslint-disable-line react-hooks/exhaustive-deps

  const filtered = types.filter(t => t.name.toLowerCase().includes(search.toLowerCase()));

  const openAdd = () => {
    setNewTypeName('');
    setNewFeatures([]);
    setError('');
    setSuggesting(false);
    setModal({ mode: 'add' });
  };

  const openEdit = (type) => {
    setEditName(type.name);
    setAddingFeature(false);
    setNewFeature(EMPTY_FEATURE);
    setSuggestedFeatures([]);
    setSuggestingEdit(false);
    setError('');
    setModal({ mode: 'edit', type });
  };

  const closeModal = () => setModal(null);

  const handleSuggestAttributes = async () => {
    if (!newTypeName.trim() || suggesting) return;

    setError('');
    setSuggesting(true);
    try {
      const res = await productTypeApi.suggestAttributes(newTypeName.trim());
      const suggested = (res.data.attributes || []).map(a => ({
        name: a.name,
        dataType: a.dataType,
        unit: a.unit || '',
        options: (a.options || []).join(', '),
        required: !!a.required,
        _source: 'ai',
      }));

      if (suggested.length === 0) {
        showToast('No suggestions found — add features manually.', 'error');
      } else {
        // Replace the previous AI suggestions (e.g. from before the type
        // name was changed) rather than piling on top of them; anything the
        // admin added manually is kept as-is.
        setNewFeatures(rows => [...rows.filter(r => r._source !== 'ai'), ...suggested]);
        showToast(`${suggested.length} feature${suggested.length > 1 ? 's' : ''} suggested — review before creating.`);
      }
    } catch (err) {
      setError(err.response?.data?.error || 'Failed to get AI suggestions.');
    } finally {
      setSuggesting(false);
    }
  };

  const handleSuggestForEdit = async () => {
    if (!editName.trim() || suggestingEdit) return;

    setError('');
    setSuggestingEdit(true);
    try {
      const existingNames = modal.type.attributes.map(a => a.name);
      const res = await productTypeApi.suggestAttributes(editName.trim(), existingNames);
      const existingLower = existingNames.map(n => n.trim().toLowerCase());
      const suggested = (res.data.attributes || [])
        .filter(a => !existingLower.includes((a.name || '').trim().toLowerCase()))
        .map(a => ({
          name: a.name,
          dataType: a.dataType,
          unit: a.unit || '',
          options: (a.options || []).join(', '),
          required: !!a.required,
        }));

      // Always replace: re-suggesting (e.g. after renaming the type) should
      // show suggestions for the current name, not pile on top of the last batch.
      setSuggestedFeatures(suggested);
      if (suggested.length === 0) {
        showToast('No new suggestions — this type already covers the standard features.', 'error');
      } else {
        showToast(`${suggested.length} new feature${suggested.length > 1 ? 's' : ''} suggested — review before adding.`);
      }
    } catch (err) {
      setError(err.response?.data?.error || 'Failed to get AI suggestions.');
    } finally {
      setSuggestingEdit(false);
    }
  };

  const handleAddAllSuggested = async () => {
    const pending = suggestedFeatures;
    if (pending.every(f => !f.name.trim())) return;

    setAddingSuggested(true);
    let lastType = modal.type;
    const addedIndices = new Set();
    let failure = null;
    for (let i = 0; i < pending.length; i++) {
      const feat = pending[i];
      if (!feat.name.trim()) continue;

      const payload = {
        name: feat.name.trim(),
        dataType: feat.dataType,
        unit: feat.dataType === 'number' ? (feat.unit.trim() || null) : null,
        options: feat.dataType === 'select' ? parseOptions(feat.options) : null,
        required: !!feat.required,
      };
      try {
        const res = await productTypeApi.addAttribute(modal.type.id, payload);
        lastType = res.data;
        addedIndices.add(i);
      } catch (err) {
        failure = err;
        break;
      }
    }

    if (addedIndices.size > 0) {
      setModal(m => ({ ...m, type: lastType }));
      setTypes(prev => prev.map(t => (t.id === lastType.id ? lastType : t)));
    }
    // Drop only the rows that were actually added; keep the rest (including
    // the one that failed) so the admin can retry or fix it.
    setSuggestedFeatures(prev => prev.filter((_, i) => !addedIndices.has(i)));

    if (failure) {
      showToast(failure.response?.data?.error || 'Failed to add some suggested features.', 'error');
    } else {
      showToast(`${addedIndices.size} feature${addedIndices.size > 1 ? 's' : ''} added.`);
    }
    setAddingSuggested(false);
  };

  const handleCreate = async () => {
    setError('');
    if (!newTypeName.trim()) {
      setError('Type name is required.');
      return;
    }

    const cleanFeatures = newFeatures
      .filter(f => f.name.trim())
      .map(f => ({
        name: f.name.trim(),
        dataType: f.dataType,
        unit: f.dataType === 'number' ? (f.unit.trim() || null) : null,
        options: f.dataType === 'select' ? parseOptions(f.options) : null,
        required: !!f.required,
      }));

    setSaving(true);
    try {
      await productTypeApi.create({ name: newTypeName.trim(), attributes: cleanFeatures });
      showToast('Type created successfully.');
      closeModal();
      await loadTypes();
    } catch (err) {
      setError(err.response?.data?.error || 'Failed to create type.');
    } finally {
      setSaving(false);
    }
  };

  const handleRename = async () => {
    setError('');
    if (!editName.trim()) {
      setError('Type name is required.');
      return;
    }
    if (editName.trim() === modal.type.name) {
      closeModal();
      return;
    }

    setSaving(true);
    try {
      await productTypeApi.rename(modal.type.id, { name: editName.trim() });
      showToast('Type renamed successfully.');
      closeModal();
      await loadTypes();
    } catch (err) {
      setError(err.response?.data?.error || 'Failed to rename type.');
    } finally {
      setSaving(false);
    }
  };

  const handleAddFeatureToType = async () => {
    if (!newFeature.name.trim()) return;

    const payload = {
      name: newFeature.name.trim(),
      dataType: newFeature.dataType,
      unit: newFeature.dataType === 'number' ? (newFeature.unit.trim() || null) : null,
      options: newFeature.dataType === 'select' ? parseOptions(newFeature.options) : null,
      required: !!newFeature.required,
    };

    setFeatureSaving(true);
    try {
      const res = await productTypeApi.addAttribute(modal.type.id, payload);
      setModal(m => ({ ...m, type: res.data }));
      setTypes(prev => prev.map(t => (t.id === res.data.id ? res.data : t)));
      setAddingFeature(false);
      setNewFeature(EMPTY_FEATURE);
      showToast('Feature added.');
    } catch (err) {
      showToast(err.response?.data?.error || 'Failed to add feature.', 'error');
    } finally {
      setFeatureSaving(false);
    }
  };

  const handleRemoveFeature = async (attributeId) => {
    setRemovingId(attributeId);
    try {
      const res = await productTypeApi.removeAttribute(modal.type.id, attributeId);
      setModal(m => ({ ...m, type: res.data }));
      setTypes(prev => prev.map(t => (t.id === res.data.id ? res.data : t)));
      showToast('Feature removed.');
    } catch (err) {
      showToast(err.response?.data?.error || 'Failed to remove feature.', 'error');
    } finally {
      setRemovingId(null);
    }
  };

  const handleDelete = async (id) => {
    try {
      await productTypeApi.remove(id);
      setTypes(prev => prev.filter(t => t.id !== id));
      showToast('Type deleted.');
    } catch (err) {
      showToast(err.response?.data?.error || 'Failed to delete type.', 'error');
    }
    setConfirmId(null);
  };

  return (
    <div className="adm-page">
      {toast && <Toast msg={toast.msg} type={toast.type} />}

      <div className="adm-page-header">
        <div>
          <h1 className="adm-page-title">Types</h1>
          <p className="adm-page-sub">
            {types.length} product type{types.length !== 1 ? 's' : ''} &nbsp;·&nbsp;
            {types.reduce((sum, t) => sum + t.attributes.length, 0)} features total
          </p>
        </div>
        <button className="adm-btn-primary" onClick={openAdd}>
          <IconPlus /> Add Type
        </button>
      </div>

      <div className="adm-toolbar">
        <div className="adm-search">
          <IconSearch />
          <input
            placeholder="Search types..."
            value={search}
            onChange={e => setSearch(e.target.value)}
          />
        </div>
      </div>

      <div className="adm-table-wrap">
        <table className="adm-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Type</th>
              <th>Features</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            {loading ? (
              <tr><td colSpan={4}><div className="adm-empty"><p>Loading types…</p></div></td></tr>
            ) : filtered.length === 0 ? (
              <tr><td colSpan={4}><div className="adm-empty"><p>No product types yet. Click "Add Type" to create one (e.g. Smartphone, Smart Watch).</p></div></td></tr>
            ) : (
              filtered.map((t, i) => (
                <tr key={t.id}>
                  <td className="adm-td-muted">{i + 1}</td>
                  <td>
                    <div className="aty-name-cell">
                      {t.name}
                      <span className="aty-slug">{t.slug}</span>
                    </div>
                  </td>
                  <td>
                    {t.attributes.length === 0 ? (
                      <span className="aty-empty-chip">No features defined</span>
                    ) : (
                      <div className="aty-chips">
                        {t.attributes.map(a => (
                          <span key={a.id} className={`aty-chip${a.required ? ' aty-chip--required' : ''}`}>
                            {a.name}{a.unit ? ` (${a.unit})` : ''}
                          </span>
                        ))}
                      </div>
                    )}
                  </td>
                  <td>
                    <div className="adm-actions">
                      <button className="adm-btn-icon adm-btn-edit" onClick={() => openEdit(t)}>
                        <IconEdit /> Edit
                      </button>
                      <button className="adm-btn-icon adm-btn-delete" onClick={() => setConfirmId(t.id)}>
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

      {/* Add type modal */}
      {modal?.mode === 'add' && (
        <div className="adm-overlay" onClick={closeModal}>
          <div className="adm-modal aty-type-modal" onClick={e => e.stopPropagation()}>
            <div className="adm-modal-head">
              <h2>Add New Type</h2>
              <button className="adm-modal-close" onClick={closeModal}>✕</button>
            </div>
            <div className="adm-modal-body">
              <div className="adm-field">
                <label>Type Name *</label>
                <input
                  type="text"
                  placeholder="e.g. Smartphone"
                  value={newTypeName}
                  onChange={e => setNewTypeName(e.target.value)}
                  autoFocus
                />
              </div>

              <div className="tff-feature-list">
                <div className="tff-feature-list-head">
                  <label className="tff-feature-list-label">Features</label>
                  <button
                    type="button"
                    className="tff-suggest-btn"
                    onClick={handleSuggestAttributes}
                    disabled={suggesting || !newTypeName.trim()}
                    title={!newTypeName.trim() ? 'Enter a type name first' : 'Suggest standard features with AI'}
                  >
                    <IconSparkles /> {suggesting ? 'Thinking…' : 'Suggest with AI'}
                  </button>
                </div>
                {newFeatures.length === 0 && (
                  <p className="aty-no-features">No features yet — suggest with AI or add one manually.</p>
                )}
                {newFeatures.map((feat, idx) => (
                  <FeatureRowEditor
                    key={idx}
                    feature={feat}
                    onChange={updated => setNewFeatures(rows => rows.map((r, i) => (i === idx ? updated : r)))}
                    onRemove={() => setNewFeatures(rows => rows.filter((_, i) => i !== idx))}
                  />
                ))}
                <button
                  type="button"
                  className="tff-add-feature-btn"
                  onClick={() => setNewFeatures(rows => [...rows, { ...EMPTY_FEATURE, _source: 'manual' }])}
                >
                  + Add feature
                </button>
              </div>

              {error && <span className="ap-err">{error}</span>}
            </div>
            <div className="adm-modal-foot">
              <button className="adm-btn-cancel" onClick={closeModal}>Cancel</button>
              <button className="adm-btn-save" onClick={handleCreate} disabled={saving}>
                {saving ? 'Creating…' : 'Create Type'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Edit type modal */}
      {modal?.mode === 'edit' && (
        <div className="adm-overlay" onClick={closeModal}>
          <div className="adm-modal aty-type-modal" onClick={e => e.stopPropagation()}>
            <div className="adm-modal-head">
              <h2>Edit Type</h2>
              <button className="adm-modal-close" onClick={closeModal}>✕</button>
            </div>
            <div className="adm-modal-body">
              <div className="adm-field">
                <label>Type Name *</label>
                <input
                  type="text"
                  value={editName}
                  onChange={e => setEditName(e.target.value)}
                  autoFocus
                />
                {error && <span className="ap-err">{error}</span>}
              </div>

              <div className="tff-feature-list">
                <div className="tff-feature-list-head">
                  <label className="tff-feature-list-label">Features</label>
                  <button
                    type="button"
                    className="tff-suggest-btn"
                    onClick={handleSuggestForEdit}
                    disabled={suggestingEdit || !editName.trim()}
                    title={!editName.trim() ? 'Enter a type name first' : 'Suggest new features with AI'}
                  >
                    <IconSparkles /> {suggestingEdit ? 'Thinking…' : 'Suggest with AI'}
                  </button>
                </div>
                {modal.type.attributes.length === 0 ? (
                  <p className="aty-no-features">No features defined yet.</p>
                ) : (
                  <div className="aty-existing-features">
                    {modal.type.attributes.map(attr => (
                      <div className="aty-existing-feature" key={attr.id}>
                        <span className="aty-existing-feature-name">{attr.name}</span>
                        <span className="aty-existing-feature-meta">
                          {DATA_TYPE_LABEL[attr.dataType]}{attr.unit ? ` · ${attr.unit}` : ''}{attr.required ? ' · required' : ''}
                        </span>
                        <button
                          type="button"
                          className="aty-existing-feature-remove"
                          disabled={removingId === attr.id}
                          onClick={() => handleRemoveFeature(attr.id)}
                        >
                          {removingId === attr.id ? 'Removing…' : 'Remove'}
                        </button>
                      </div>
                    ))}
                  </div>
                )}

                {suggestedFeatures.length > 0 && (
                  <div className="aty-suggested-block">
                    <div className="aty-suggested-head">
                      <span className="aty-suggested-title"><IconSparkles /> Suggested features — review before adding</span>
                      <button type="button" className="aty-suggested-discard" onClick={() => setSuggestedFeatures([])}>
                        Discard
                      </button>
                    </div>
                    {suggestedFeatures.map((feat, idx) => (
                      <FeatureRowEditor
                        key={idx}
                        feature={feat}
                        onChange={updated => setSuggestedFeatures(rows => rows.map((r, i) => (i === idx ? updated : r)))}
                        onRemove={() => setSuggestedFeatures(rows => rows.filter((_, i) => i !== idx))}
                      />
                    ))}
                    <button
                      type="button"
                      className="adm-btn-save aty-suggested-addall"
                      disabled={addingSuggested}
                      onClick={handleAddAllSuggested}
                    >
                      {addingSuggested ? 'Adding…' : `Add all (${suggestedFeatures.length})`}
                    </button>
                  </div>
                )}
              </div>

              {!addingFeature ? (
                <button type="button" className="tff-add-feature-link" onClick={() => setAddingFeature(true)}>
                  + Add a new feature to "{modal.type.name}"
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
                    <button type="button" className="adm-btn-save" disabled={featureSaving} onClick={handleAddFeatureToType}>
                      {featureSaving ? 'Adding…' : 'Add Feature'}
                    </button>
                  </div>
                </div>
              )}
            </div>
            <div className="adm-modal-foot">
              <button className="adm-btn-cancel" onClick={closeModal}>Cancel</button>
              <button className="adm-btn-save" onClick={handleRename} disabled={saving}>
                {saving ? 'Saving…' : 'Save Changes'}
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
              <h2>Delete Type?</h2>
              <button className="adm-modal-close" onClick={() => setConfirmId(null)}>✕</button>
            </div>
            <div className="adm-modal-body">
              <p style={{ fontSize: '0.875rem', color: '#475569', margin: 0, lineHeight: 1.6 }}>
                This type and its feature definitions will be permanently removed. Refused while any product still uses it.
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
