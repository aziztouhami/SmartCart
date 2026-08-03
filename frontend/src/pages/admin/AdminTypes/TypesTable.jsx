import React from 'react';
import { IconEdit, IconTrash } from '../../../components/admin/AdminIcons';
import AnalyzeButton from '../../../components/admin/AnalyzeButton';

export default function TypesTable({ types, loading, onEdit, onDeleteRequest, onAnalyze }) {
  return (
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
            <tr>
              <td colSpan={4}>
                <div className="adm-empty">
                  <p>Loading types…</p>
                </div>
              </td>
            </tr>
          ) : types.length === 0 ? (
            <tr>
              <td colSpan={4}>
                <div className="adm-empty">
                  <p>
                    No product types yet. Click "Add Type" to create one (e.g. Smartphone, Smart
                    Watch).
                  </p>
                </div>
              </td>
            </tr>
          ) : (
            types.map((t, i) => (
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
                        <span
                          key={a.id}
                          className={`aty-chip${a.required ? ' aty-chip--required' : ''}`}
                        >
                          {a.name}
                          {a.unit ? ` (${a.unit})` : ''}
                        </span>
                      ))}
                    </div>
                  )}
                </td>
                <td>
                  <div className="adm-actions">
                    <button className="adm-btn-icon adm-btn-edit" onClick={() => onEdit(t)}>
                      <IconEdit /> Edit
                    </button>
                    <button
                      className="adm-btn-icon adm-btn-delete"
                      onClick={() => onDeleteRequest(t.id)}
                    >
                      <IconTrash /> Delete
                    </button>
                    <AnalyzeButton onClick={() => onAnalyze(t)} />
                  </div>
                </td>
              </tr>
            ))
          )}
        </tbody>
      </table>
    </div>
  );
}
