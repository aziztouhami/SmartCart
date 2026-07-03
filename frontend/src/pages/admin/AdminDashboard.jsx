import React, { useState, useEffect } from 'react';
import { Package, Tag, AlertTriangle, Wallet } from 'lucide-react';
import { dashboardApi } from '../../services/cartService';
import { formatPrice } from '../../utils/format';
import './AdminDashboard.css';

function buildKpiCards(data) {
  const lowStock = data.products.lowStockCount;
  const outOfStock = data.products.outOfStockCount;
  return [
    {
      label: 'Total Products',
      value: data.products.total,
      icon: Package,
      color: '#185FA5',
      bg: '#eff6ff',
    },
    {
      label: 'Total Categories',
      value: data.categories.total,
      icon: Tag,
      color: '#7c3aed',
      bg: '#f5f3ff',
    },
    {
      label: 'Low / Out of Stock',
      value: `${lowStock} / ${outOfStock}`,
      icon: AlertTriangle,
      trend: lowStock + outOfStock > 0 ? `${lowStock + outOfStock} need attention` : 'All stocked',
      color: '#d97706',
      bg: '#fffbeb',
    },
    {
      label: 'Total Revenue',
      value: `${formatPrice(data.revenue.total)} TND`,
      icon: Wallet,
      color: '#059669',
      bg: '#ecfdf5',
    },
  ];
}

export default function AdminDashboard() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);

  useEffect(() => {
    dashboardApi.get()
      .then(res => setData(res.data))
      .catch(() => setError(true))
      .finally(() => setLoading(false));
  }, []);

  if (loading) {
    return <div className="adm-page"><p className="adm-page-sub">Loading dashboard…</p></div>;
  }

  if (error || !data) {
    return <div className="adm-page"><p className="adm-page-sub">Failed to load dashboard data.</p></div>;
  }

  const kpiCards = buildKpiCards(data);
  const monthly = data.revenue.monthly;
  const maxRevenue = Math.max(1, ...monthly.map(m => m.value));
  const topCats = data.categories.top;
  const maxCount = Math.max(1, ...topCats.map(c => c.productCount));
  const topSelling = data.topSelling;

  return (
    <div className="adm-page">
      <div className="adm-page-header">
        <div>
          <h1 className="adm-page-title">Dashboard</h1>
          <p className="adm-page-sub">Welcome back, Admin — here's what's happening today.</p>
        </div>
        <div className="db-date">
          {new Date().toLocaleDateString('en-GB', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}
        </div>
      </div>

      {/* KPI cards */}
      <div className="db-kpi-grid">
        {kpiCards.map(card => (
          <div key={card.label} className="db-kpi-card">
            <div className="db-kpi-icon" style={{ background: card.bg, color: card.color }}>
              <card.icon size={20} />
            </div>
            <div className="db-kpi-body">
              <p className="db-kpi-label">{card.label}</p>
              <p className="db-kpi-value">{card.value}</p>
              {card.trend && (
                <p className="db-kpi-trend db-kpi-trend--warn">{card.trend}</p>
              )}
            </div>
          </div>
        ))}
      </div>

      {/* Charts row */}
      <div className="db-charts-row">

        {/* Monthly revenue bar chart */}
        <div className="db-card db-chart-card">
          <div className="db-card-head">
            <div>
              <h3 className="db-card-title">Monthly Revenue</h3>
              <p className="db-card-sub">Last {monthly.length} months</p>
            </div>
            <span className="db-total-badge">
              {formatPrice(data.revenue.total)} TND
            </span>
          </div>
          <div className="db-bar-chart">
            {monthly.map(({ month, year, value }) => {
              const pct = Math.round((value / maxRevenue) * 100);
              return (
                <div key={`${month}-${year}`} className="db-bar-col">
                  <div className="db-bar-val">{(value / 1000).toFixed(1)}k</div>
                  <div className="db-bar-track">
                    <div className="db-bar-fill" style={{ height: `${pct}%` }} />
                  </div>
                  <div className="db-bar-label">{month}</div>
                </div>
              );
            })}
          </div>
        </div>

        {/* Top categories horizontal bars */}
        <div className="db-card db-cat-card">
          <div className="db-card-head">
            <div>
              <h3 className="db-card-title">Top Categories</h3>
              <p className="db-card-sub">By product count</p>
            </div>
          </div>
          <div className="db-cat-list">
            {topCats.length === 0 ? (
              <p className="adm-muted">No categories yet.</p>
            ) : topCats.map(cat => {
              const pct = Math.round((cat.productCount / maxCount) * 100);
              return (
                <div key={cat.id} className="db-cat-row">
                  <div className="db-cat-info">
                    <span className="db-cat-name">{cat.name}</span>
                    <span className="db-cat-count">{cat.productCount}</span>
                  </div>
                  <div className="db-hbar-track">
                    <div className="db-hbar-fill" style={{ width: `${pct}%` }} />
                  </div>
                </div>
              );
            })}
          </div>
        </div>

      </div>

      {/* Top selling products */}
      <div className="db-card db-recent-card">
        <div className="db-card-head">
          <div>
            <h3 className="db-card-title">Top Selling Products</h3>
            <p className="db-card-sub">Best performing items by units sold</p>
          </div>
        </div>
        <div className="adm-table-wrap" style={{ border: 'none', boxShadow: 'none' }}>
          <table className="adm-table">
            <thead>
              <tr>
                <th>Product</th>
                <th>Category</th>
                <th>Price</th>
                <th>Stock</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              {topSelling.length === 0 ? (
                <tr><td colSpan={5}><div className="adm-empty"><p>No sales recorded yet.</p></div></td></tr>
              ) : topSelling.map(p => (
                <tr key={p.id}>
                  <td style={{ fontWeight: 600 }}>{p.name}</td>
                  <td><span className="adm-parent-badge">{p.category?.name}</span></td>
                  <td style={{ fontWeight: 600, color: 'var(--color-primary)' }}>
                    {formatPrice(p.price)} TND
                  </td>
                  <td>{p.stock}</td>
                  <td>
                    {p.stock === 0 ? (
                      <span className="db-status db-status--out">Out of Stock</span>
                    ) : p.stock <= 15 ? (
                      <span className="db-status db-status--low">Low Stock</span>
                    ) : (
                      <span className="db-status db-status--ok">In Stock</span>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

    </div>
  );
}
