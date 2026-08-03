import React, { useState } from 'react';
import { productTypeApi } from '../../../services/cartService';
import {
  EMPTY_FEATURE,
  parseOptions,
  FeatureRowEditor,
} from '../../../components/admin/TypeFeatureFields';
import IconSparkles from './IconSparkles';

export default function AddTypeModal({ onClose, onCreated, showToast }) {
  const [newTypeName, setNewTypeName] = useState('');
  const [newFeatures, setNewFeatures] = useState([]);
  const [error, setError] = useState('');
  const [suggesting, setSuggesting] = useState(false);
  const [saving, setSaving] = useState(false);

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
        showToast(
          `${suggested.length} feature${suggested.length > 1 ? 's' : ''} suggested — review before creating.`,
        );
      }
    } catch (err) {
      setError(err.response?.data?.error || 'Failed to get AI suggestions.');
    } finally {
      setSuggesting(false);
    }
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
        unit: f.dataType === 'number' ? f.unit.trim() || null : null,
        options: f.dataType === 'select' ? parseOptions(f.options) : null,
        required: !!f.required,
      }));

    setSaving(true);
    try {
      await productTypeApi.create({ name: newTypeName.trim(), attributes: cleanFeatures });
      showToast('Type created successfully.');
      onCreated();
    } catch (err) {
      setError(err.response?.data?.error || 'Failed to create type.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="adm-overlay" onClick={onClose}>
      <div className="adm-modal aty-type-modal" onClick={e => e.stopPropagation()}>
        <div className="adm-modal-head">
          <h2>Add New Type</h2>
          <button className="adm-modal-close" onClick={onClose}>
            ✕
          </button>
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
                title={
                  !newTypeName.trim()
                    ? 'Enter a type name first'
                    : 'Suggest standard features with AI'
                }
              >
                <IconSparkles /> {suggesting ? 'Thinking…' : 'Suggest with AI'}
              </button>
            </div>
            {newFeatures.length === 0 && (
              <p className="aty-no-features">
                No features yet — suggest with AI or add one manually.
              </p>
            )}
            {newFeatures.map((feat, idx) => (
              <FeatureRowEditor
                key={idx}
                feature={feat}
                onChange={updated =>
                  setNewFeatures(rows => rows.map((r, i) => (i === idx ? updated : r)))
                }
                onRemove={() => setNewFeatures(rows => rows.filter((_, i) => i !== idx))}
              />
            ))}
            <button
              type="button"
              className="tff-add-feature-btn"
              onClick={() =>
                setNewFeatures(rows => [...rows, { ...EMPTY_FEATURE, _source: 'manual' }])
              }
            >
              + Add feature
            </button>
          </div>

          {error && <span className="ap-err">{error}</span>}
        </div>
        <div className="adm-modal-foot">
          <button className="adm-btn-cancel" onClick={onClose}>
            Cancel
          </button>
          <button className="adm-btn-save" onClick={handleCreate} disabled={saving}>
            {saving ? 'Creating…' : 'Create Type'}
          </button>
        </div>
      </div>
    </div>
  );
}
