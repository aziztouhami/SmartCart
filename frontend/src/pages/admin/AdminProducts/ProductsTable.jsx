import React from 'react';
import { formatPrice } from '../../../utils/format';
import { IconEdit, IconTrash } from '../../../components/admin/AdminIcons';
import AnalyzeButton from '../../../components/admin/AnalyzeButton';

function stockStatus(stock) {
  if (stock === 0) return { label: 'Out of Stock', cls: 'ap-badge--out' };
  if (stock <= 15) return { label: 'Low Stock', cls: 'ap-badge--low' };
  return { label: 'In Stock', cls: 'ap-badge--ok' };
}

export default function ProductsTable({ products, loading, onEdit, onDeleteRequest, onAnalyze }) {
  return (
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
            <tr>
              <td colSpan={9}>
                <div className="adm-empty">
                  <p>Loading products…</p>
                </div>
              </td>
            </tr>
          ) : products.length === 0 ? (
            <tr>
              <td colSpan={9}>
                <div className="adm-empty">
                  <p>No products match your search.</p>
                </div>
              </td>
            </tr>
          ) : (
            products.map((p, i) => {
              const { label, cls } = stockStatus(p.stock);
              const image = p.images?.[0];
              return (
                <tr key={p.id}>
                  <td className="adm-td-muted">{i + 1}</td>
                  <td>
                    {image ? (
                      <img src={image} alt={p.name} className="ap-thumb" />
                    ) : (
                      <div className="ap-thumb-placeholder">—</div>
                    )}
                  </td>
                  <td>
                    <div className="ap-product-name">{p.name}</div>
                    {p.description && (
                      <div className="ap-product-desc">{p.description.slice(0, 60)}…</div>
                    )}
                  </td>
                  <td>
                    <span className="adm-parent-badge">{p.category?.name || '—'}</span>
                  </td>
                  <td>
                    {p.brand ? (
                      <span className="ap-brand-cell">
                        {p.brand.image && (
                          <img src={p.brand.image} alt={p.brand.name} className="ap-brand-logo" />
                        )}
                        {p.brand.name}
                      </span>
                    ) : (
                      <span className="adm-muted">—</span>
                    )}
                  </td>
                  <td>
                    <span className="ap-price">{formatPrice(p.price)}</span>
                  </td>
                  <td style={{ textAlign: 'center' }}>
                    <span
                      className={
                        p.stock <= 15
                          ? p.stock === 0
                            ? 'adm-stock-out'
                            : 'adm-stock-low'
                          : 'adm-stock-ok'
                      }
                    >
                      {p.stock}
                    </span>
                  </td>
                  <td>
                    <span className={`ap-badge ${cls}`}>{label}</span>
                  </td>
                  <td>
                    <div className="adm-actions">
                      <button className="adm-btn-icon adm-btn-edit" onClick={() => onEdit(p)}>
                        <IconEdit /> Edit
                      </button>
                      <button
                        className="adm-btn-icon adm-btn-delete"
                        onClick={() => onDeleteRequest(p.id)}
                      >
                        <IconTrash /> Delete
                      </button>
                      <AnalyzeButton onClick={() => onAnalyze(p)} />
                    </div>
                  </td>
                </tr>
              );
            })
          )}
        </tbody>
      </table>
    </div>
  );
}
