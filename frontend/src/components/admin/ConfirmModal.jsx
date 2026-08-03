import React from 'react';

export default function ConfirmModal({
  title,
  message,
  confirmLabel = 'Delete',
  danger = true,
  onConfirm,
  onCancel,
}) {
  return (
    <div className="adm-overlay" onClick={onCancel}>
      <div className="adm-modal adm-modal--sm" onClick={e => e.stopPropagation()}>
        <div className="adm-modal-head">
          <h2>{title}</h2>
          <button className="adm-modal-close" onClick={onCancel}>
            ✕
          </button>
        </div>
        <div className="adm-modal-body">
          <p style={{ fontSize: '0.875rem', color: '#475569', margin: 0, lineHeight: 1.6 }}>
            {message}
          </p>
        </div>
        <div className="adm-modal-foot">
          <button className="adm-btn-cancel" onClick={onCancel}>
            Cancel
          </button>
          <button className={`adm-btn-save${danger ? ' ac-btn-danger' : ''}`} onClick={onConfirm}>
            {confirmLabel}
          </button>
        </div>
      </div>
    </div>
  );
}
