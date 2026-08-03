import React, { useState } from 'react';
import { productTypeApi } from '../../../services/cartService';
import {
  EMPTY_FEATURE,
  parseOptions,
  FeatureRowEditor,
} from '../../../components/admin/TypeFeatureFields';
import IconSparkles from './IconSparkles';

const DATA_TYPE_LABEL = {
  text: 'Text',
  number: 'Number',
  boolean: 'Yes/No',
  select: 'Choice list',
};

export default function EditTypeModal({
  type: initialType,
  onClose,
  onRenamed,
  onTypeUpdated,
  showToast,
}) {
  const [type, setType] = useState(initialType);
  const [editName, setEditName] = useState(initialType.name);
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);

  const [addingFeature, setAddingFeature] = useState(false);
  const [newFeature, setNewFeature] = useState(EMPTY_FEATURE);
  const [featureSaving, setFeatureSaving] = useState(false);
  const [removingId, setRemovingId] = useState(null);
  const [suggestedFeatures, setSuggestedFeatures] = useState([]);
  const [suggestingEdit, setSuggestingEdit] = useState(false);
  const [addingSuggested, setAddingSuggested] = useState(false);

  const applyUpdatedType = updated => {
    setType(updated);
    onTypeUpdated(updated);
  };

  const handleSuggestForEdit = async () => {
    if (!editName.trim() || suggestingEdit) return;

    setError('');
    setSuggestingEdit(true);
    try {
      const existingNames = type.attributes.map(a => a.name);
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
        showToast(
          `${suggested.length} new feature${suggested.length > 1 ? 's' : ''} suggested — review before adding.`,
        );
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
    let lastType = type;
    const addedIndices = new Set();
    let failure = null;
    for (let i = 0; i < pending.length; i++) {
      const feat = pending[i];
      if (!feat.name.trim()) continue;

      const payload = {
        name: feat.name.trim(),
        dataType: feat.dataType,
        unit: feat.dataType === 'number' ? feat.unit.trim() || null : null,
        options: feat.dataType === 'select' ? parseOptions(feat.options) : null,
        required: !!feat.required,
      };
      try {
        const res = await productTypeApi.addAttribute(type.id, payload);
        lastType = res.data;
        addedIndices.add(i);
      } catch (err) {
        failure = err;
        break;
      }
    }

    if (addedIndices.size > 0) {
      applyUpdatedType(lastType);
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

  const handleRename = async () => {
    setError('');
    if (!editName.trim()) {
      setError('Type name is required.');
      return;
    }
    if (editName.trim() === type.name) {
      onClose();
      return;
    }

    setSaving(true);
    try {
      await productTypeApi.rename(type.id, { name: editName.trim() });
      showToast('Type renamed successfully.');
      onRenamed();
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
      unit: newFeature.dataType === 'number' ? newFeature.unit.trim() || null : null,
      options: newFeature.dataType === 'select' ? parseOptions(newFeature.options) : null,
      required: !!newFeature.required,
    };

    setFeatureSaving(true);
    try {
      const res = await productTypeApi.addAttribute(type.id, payload);
      applyUpdatedType(res.data);
      setAddingFeature(false);
      setNewFeature(EMPTY_FEATURE);
      showToast('Feature added.');
    } catch (err) {
      showToast(err.response?.data?.error || 'Failed to add feature.', 'error');
    } finally {
      setFeatureSaving(false);
    }
  };

  const handleRemoveFeature = async attributeId => {
    setRemovingId(attributeId);
    try {
      const res = await productTypeApi.removeAttribute(type.id, attributeId);
      applyUpdatedType(res.data);
      showToast('Feature removed.');
    } catch (err) {
      showToast(err.response?.data?.error || 'Failed to remove feature.', 'error');
    } finally {
      setRemovingId(null);
    }
  };

  return (
    <div className="adm-overlay" onClick={onClose}>
      <div className="adm-modal aty-type-modal" onClick={e => e.stopPropagation()}>
        <div className="adm-modal-head">
          <h2>Edit Type</h2>
          <button className="adm-modal-close" onClick={onClose}>
            ✕
          </button>
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
                title={
                  !editName.trim() ? 'Enter a type name first' : 'Suggest new features with AI'
                }
              >
                <IconSparkles /> {suggestingEdit ? 'Thinking…' : 'Suggest with AI'}
              </button>
            </div>
            {type.attributes.length === 0 ? (
              <p className="aty-no-features">No features defined yet.</p>
            ) : (
              <div className="aty-existing-features">
                {type.attributes.map(attr => (
                  <div className="aty-existing-feature" key={attr.id}>
                    <span className="aty-existing-feature-name">{attr.name}</span>
                    <span className="aty-existing-feature-meta">
                      {DATA_TYPE_LABEL[attr.dataType]}
                      {attr.unit ? ` · ${attr.unit}` : ''}
                      {attr.required ? ' · required' : ''}
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
                  <span className="aty-suggested-title">
                    <IconSparkles /> Suggested features — review before adding
                  </span>
                  <button
                    type="button"
                    className="aty-suggested-discard"
                    onClick={() => setSuggestedFeatures([])}
                  >
                    Discard
                  </button>
                </div>
                {suggestedFeatures.map((feat, idx) => (
                  <FeatureRowEditor
                    key={idx}
                    feature={feat}
                    onChange={updated =>
                      setSuggestedFeatures(rows => rows.map((r, i) => (i === idx ? updated : r)))
                    }
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
            <button
              type="button"
              className="tff-add-feature-link"
              onClick={() => setAddingFeature(true)}
            >
              + Add a new feature to "{type.name}"
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
                  onClick={handleAddFeatureToType}
                >
                  {featureSaving ? 'Adding…' : 'Add Feature'}
                </button>
              </div>
            </div>
          )}
        </div>
        <div className="adm-modal-foot">
          <button className="adm-btn-cancel" onClick={onClose}>
            Cancel
          </button>
          <button className="adm-btn-save" onClick={handleRename} disabled={saving}>
            {saving ? 'Saving…' : 'Save Changes'}
          </button>
        </div>
      </div>
    </div>
  );
}
