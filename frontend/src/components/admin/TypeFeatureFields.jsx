import React from 'react';
import './TypeFeatureFields.css';

export const EMPTY_FEATURE = { name: '', dataType: 'text', unit: '', options: '', required: false };

/** Turns the dynamic feature values collected in a form into the
 * {slug: value} payload the backend expects, coercing numbers and
 * dropping anything left blank. */
export function buildAttributesPayload(type, values) {
  if (!type) return {};
  const out = {};
  for (const attr of type.attributes) {
    const v = values[attr.slug];
    if (v === undefined || v === null || v === '') continue;
    out[attr.slug] = attr.dataType === 'number' ? parseFloat(v) : v;
  }
  return out;
}

/** Turns a comma-separated options string back into the array the
 * backend expects for "select" features. */
export function parseOptions(raw) {
  return raw.split(',').map(o => o.trim()).filter(Boolean);
}

/** One row of the inline "define a feature" editor, used both when
 * creating a brand new type and when adding a feature to an existing one. */
export function FeatureRowEditor({ feature, onChange, onRemove }) {
  return (
    <div className="tff-feature-row">
      <input
        className="tff-feature-name"
        placeholder="Feature name (e.g. Color)"
        value={feature.name}
        onChange={e => onChange({ ...feature, name: e.target.value })}
      />
      <select
        className="tff-feature-type"
        value={feature.dataType}
        onChange={e => onChange({ ...feature, dataType: e.target.value })}
      >
        <option value="text">Text</option>
        <option value="number">Number</option>
        <option value="boolean">Yes / No</option>
        <option value="select">Choice list</option>
      </select>
      {feature.dataType === 'number' && (
        <input
          className="tff-feature-unit"
          placeholder="Unit (e.g. mAh)"
          value={feature.unit}
          onChange={e => onChange({ ...feature, unit: e.target.value })}
        />
      )}
      {feature.dataType === 'select' && (
        <input
          className="tff-feature-options"
          placeholder="Options, comma separated"
          value={feature.options}
          onChange={e => onChange({ ...feature, options: e.target.value })}
        />
      )}
      <label className="tff-feature-required">
        <input
          type="checkbox"
          checked={feature.required}
          onChange={e => onChange({ ...feature, required: e.target.checked })}
        />
        Required
      </label>
      {onRemove && (
        <button type="button" className="tff-feature-remove" onClick={onRemove} title="Remove feature">✕</button>
      )}
    </div>
  );
}

/** Renders the right kind of input for one feature definition, bound to
 * a product's current value for that feature's slug. */
export function AttributeValueInput({ attr, value, onChange }) {
  if (attr.dataType === 'boolean') {
    return (
      <select value={value === undefined || value === null ? '' : String(value)} onChange={e => onChange(e.target.value === '' ? undefined : e.target.value === 'true')}>
        <option value="">—</option>
        <option value="true">Yes</option>
        <option value="false">No</option>
      </select>
    );
  }
  if (attr.dataType === 'select') {
    return (
      <select value={value ?? ''} onChange={e => onChange(e.target.value || undefined)}>
        <option value="">—</option>
        {(attr.options || []).map(opt => <option key={opt} value={opt}>{opt}</option>)}
      </select>
    );
  }
  if (attr.dataType === 'number') {
    return (
      <input
        type="number"
        step="any"
        value={value ?? ''}
        placeholder={attr.unit ? `Value in ${attr.unit}` : ''}
        onChange={e => onChange(e.target.value === '' ? undefined : e.target.value)}
      />
    );
  }
  return <input type="text" value={value ?? ''} onChange={e => onChange(e.target.value || undefined)} />;
}
