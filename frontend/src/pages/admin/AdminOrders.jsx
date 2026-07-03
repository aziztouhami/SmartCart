import React, { useState, useEffect, useCallback } from 'react';
import { adminOrderApi } from '../../services/cartService';
import { formatPrice as fmt } from '../../utils/format';
import './AdminOrders.css';

const TRANSITIONS = {
  pending:   ['confirmed', 'cancelled'],
  confirmed: ['shipped', 'cancelled'],
  shipped:   ['delivered'],
  delivered: [],
  cancelled: [],
};

const STATUS_FR = {
  pending:   'En attente',
  confirmed: 'Confirmée',
  shipped:   'Expédiée',
  delivered: 'Livrée',
  cancelled: 'Annulée',
};

const FILTERS = [
  { key: '',          label: 'All' },
  { key: 'pending',   label: 'En attente' },
  { key: 'confirmed', label: 'Confirmées' },
  { key: 'shipped',   label: 'Expédiées' },
  { key: 'delivered', label: 'Livrées' },
  { key: 'cancelled', label: 'Annulées' },
];

function fmtDate(iso) {
  return new Date(iso).toLocaleDateString('fr-TN', {
    day: '2-digit', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  });
}

export default function AdminOrders() {
  const [orders,      setOrders]      = useState([]);
  const [loading,     setLoading]     = useState(true);
  const [filter,      setFilter]      = useState('');
  const [page,        setPage]        = useState(1);
  const [total,       setTotal]       = useState(0);
  const [expanded,    setExpanded]    = useState(null);
  const [detailCache, setDetailCache] = useState({});
  const [updating,    setUpdating]    = useState(null);
  const [toast,       setToast]       = useState(null);
  const limit = 20;

  const showToast = (msg, type = 'success') => {
    setToast({ msg, type });
    setTimeout(() => setToast(null), 3500);
  };

  const load = useCallback(() => {
    setLoading(true);
    adminOrderApi.getOrders(filter || null, page, limit)
      .then(res => {
        setOrders(res.data.data  || []);
        setTotal(res.data.total || 0);
      })
      .catch(() => showToast('Failed to load orders.', 'error'))
      .finally(() => setLoading(false));
  }, [filter, page]);

  useEffect(() => { load(); }, [load]);

  const changeFilter = (f) => { setFilter(f); setPage(1); setExpanded(null); };

  const toggleExpand = async (id) => {
    if (expanded === id) { setExpanded(null); return; }
    setExpanded(id);
    if (detailCache[id]) return;
    try {
      const res = await adminOrderApi.getOrder(id);
      setDetailCache(prev => ({ ...prev, [id]: res.data }));
    } catch {}
  };

  const handleStatusChange = async (orderId, newStatus) => {
    setUpdating(orderId);
    try {
      await adminOrderApi.updateStatus(orderId, newStatus);
      showToast(`Order #${orderId} → ${STATUS_FR[newStatus]}`);
      setOrders(prev => prev.map(o => o.id === orderId ? { ...o, status: newStatus } : o));
      setDetailCache(prev => { const n = { ...prev }; delete n[orderId]; return n; });
    } catch (err) {
      showToast(err.response?.data?.error || 'Failed to update status.', 'error');
    } finally {
      setUpdating(null);
    }
  };

  const totalPages = Math.ceil(total / limit);

  return (
    <div className="ao-page">

      {/* Toast notification */}
      {toast && (
        <div className={`ao-toast ao-toast--${toast.type}`}>{toast.msg}</div>
      )}

      <div className="ao-head">
        <h1 className="ao-title">Orders</h1>
        <p className="ao-subtitle">{total} order{total !== 1 ? 's' : ''} total</p>
      </div>

      {/* Status filter tabs */}
      <div className="ao-filters">
        {FILTERS.map(f => (
          <button
            key={f.key}
            className={`ao-filter ${filter === f.key ? 'ao-filter--on' : ''}`}
            onClick={() => changeFilter(f.key)}
          >
            {f.label}
          </button>
        ))}
      </div>

      {loading && <div className="ao-loading">Loading orders…</div>}

      {!loading && orders.length === 0 && (
        <div className="ao-empty">No orders found for this filter.</div>
      )}

      {!loading && orders.length > 0 && (
        <>
          <div className="ao-table-wrap">
            <table className="ao-table">
              <thead>
                <tr>
                  <th>Order</th>
                  <th>Customer</th>
                  <th>Date</th>
                  <th className="ao-center">Items</th>
                  <th>Total</th>
                  <th>Status</th>
                  <th>Update</th>
                  <th className="ao-center">Detail</th>
                </tr>
              </thead>
              <tbody>
                {orders.map(order => {
                  const nexts  = TRANSITIONS[order.status] || [];
                  const detail = detailCache[order.id];
                  const isExpanded = expanded === order.id;

                  return (
                    <React.Fragment key={order.id}>
                      <tr className={`ao-row ${isExpanded ? 'ao-row--open' : ''}`}>
                        <td className="ao-id">#{order.id}</td>

                        <td className="ao-customer">
                          {(order.userFirstName || order.userLastName)
                            ? <><strong>{order.userFirstName} {order.userLastName}</strong><br /></>
                            : null
                          }
                          <span className="ao-email">{order.userEmail || '—'}</span>
                        </td>

                        <td className="ao-date">{fmtDate(order.createdAt)}</td>
                        <td className="ao-center">{order.itemCount}</td>
                        <td className="ao-total">{fmt(order.totalAmount)} TND</td>

                        <td>
                          <span className={`ao-badge ao-badge--${order.status}`}>
                            {STATUS_FR[order.status]}
                          </span>
                        </td>

                        <td>
                          {nexts.length > 0 ? (
                            <select
                              className="ao-select"
                              value=""
                              onChange={e => e.target.value && handleStatusChange(order.id, e.target.value)}
                              disabled={updating === order.id}
                            >
                              <option value="">Change…</option>
                              {nexts.map(s => (
                                <option key={s} value={s}>{STATUS_FR[s]}</option>
                              ))}
                            </select>
                          ) : (
                            <span className="ao-final">—</span>
                          )}
                        </td>

                        <td className="ao-center">
                          <button className="ao-btn-expand" onClick={() => toggleExpand(order.id)}>
                            {isExpanded ? '▲' : '▼'}
                          </button>
                        </td>
                      </tr>

                      {isExpanded && (
                        <tr className="ao-detail-row">
                          <td colSpan={8}>
                            {detail ? (
                              <div className="ao-detail">
                                <div className="ao-detail-items">
                                  {detail.items.map(item => (
                                    <div key={item.id} className="ao-detail-item">
                                      <span className="ao-di-name">{item.productName}</span>
                                      <span className="ao-di-qty">× {item.quantity}</span>
                                      <span className="ao-di-unit">{fmt(item.unitPrice)} TND/unit</span>
                                      <span className="ao-di-sub">{fmt(item.subtotal)} TND</span>
                                    </div>
                                  ))}
                                </div>
                                {detail.shippingAddress && (
                                  <div className="ao-detail-addr">
                                    <strong>Ship to:</strong>{' '}
                                    {detail.shippingAddress.street},{' '}
                                    {detail.shippingAddress.city}
                                    {detail.shippingAddress.postalCode ? ` ${detail.shippingAddress.postalCode}` : ''},{' '}
                                    {detail.shippingAddress.country}
                                  </div>
                                )}
                              </div>
                            ) : (
                              <div className="ao-detail-loading">Loading…</div>
                            )}
                          </td>
                        </tr>
                      )}
                    </React.Fragment>
                  );
                })}
              </tbody>
            </table>
          </div>

          {totalPages > 1 && (
            <div className="ao-pager">
              <button className="ao-page-btn" disabled={page === 1} onClick={() => setPage(p => p - 1)}>
                ← Previous
              </button>
              <span className="ao-page-info">Page {page} of {totalPages}</span>
              <button className="ao-page-btn" disabled={page === totalPages} onClick={() => setPage(p => p + 1)}>
                Next →
              </button>
            </div>
          )}
        </>
      )}
    </div>
  );
}
