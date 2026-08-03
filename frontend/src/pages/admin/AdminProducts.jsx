import React, { useState, useEffect, useCallback } from 'react';
import {
  adminProductApi,
  adminAnalyticsApi,
  brandApi,
  productTypeApi,
} from '../../services/cartService';
import { fetchAllProducts } from '../../utils/fetchAllProducts';
import { useCategories } from '../../context/CategoryContext';
import { IconPlus, IconSearch } from '../../components/admin/AdminIcons';
import AdminToast from '../../components/admin/AdminToast';
import ConfirmModal from '../../components/admin/ConfirmModal';
import AnomalyReportModal from '../../components/admin/AnomalyReportModal';
import useAnalysis from '../../components/admin/useAnalysis';
import ProductsTable from './AdminProducts/ProductsTable';
import ProductFormModal from './AdminProducts/ProductFormModal';
import './AdminProducts.css';

export default function AdminProducts() {
  const [products, setProducts] = useState([]);
  const { leafCategories } = useCategories();
  const [brands, setBrands] = useState([]);
  const [productTypes, setProductTypes] = useState([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [filter, setFilter] = useState('all');
  const [modal, setModal] = useState(null);
  const [confirmId, setConfirmId] = useState(null);
  const [toast, setToast] = useState(null);
  const { analysis, runAnalysis, closeAnalysis } = useAnalysis();

  const showToast = (msg, type = 'success') => {
    setToast({ msg, type });
    setTimeout(() => setToast(null), 3000);
  };

  const loadProducts = useCallback(() => {
    setLoading(true);
    return fetchAllProducts()
      .then(setProducts)
      .catch(() => showToast('Failed to load products.', 'error'))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    loadProducts();

    brandApi
      .list(1, 100)
      .then(res => setBrands(res.data.data || []))
      .catch(() => {});

    productTypeApi
      .list()
      .then(res => setProductTypes(res.data || []))
      .catch(() => {});
  }, [loadProducts]);

  const handleDelete = async id => {
    try {
      await adminProductApi.remove(id);
      setProducts(prev => prev.filter(p => p.id !== id));
      showToast('Product deleted.');
    } catch {
      showToast('Failed to delete product.', 'error');
    }
    setConfirmId(null);
  };

  const filtered = products.filter(p => {
    const matchSearch =
      p.name.toLowerCase().includes(search.toLowerCase()) ||
      (p.category?.name || '').toLowerCase().includes(search.toLowerCase()) ||
      (p.brand?.name || '').toLowerCase().includes(search.toLowerCase());
    const matchFilter =
      filter === 'all'
        ? true
        : filter === 'low'
          ? p.stock > 0 && p.stock <= 15
          : filter === 'out'
            ? p.stock === 0
            : true;
    return matchSearch && matchFilter;
  });

  return (
    <div className="adm-page">
      {toast && <AdminToast msg={toast.msg} type={toast.type} />}

      <div className="adm-page-header">
        <div>
          <h1 className="adm-page-title">Products</h1>
          <p className="adm-page-sub">
            {products.length} total &nbsp;·&nbsp;
            {products.filter(p => p.stock > 0 && p.stock <= 15).length} low stock &nbsp;·&nbsp;
            {products.filter(p => p.stock === 0).length} out of stock
          </p>
        </div>
        <button className="adm-btn-primary" onClick={() => setModal({ mode: 'add' })}>
          <IconPlus /> Add Product
        </button>
      </div>

      <div className="adm-toolbar">
        <div className="adm-search">
          <IconSearch />
          <input
            placeholder="Search products, categories or brands..."
            value={search}
            onChange={e => setSearch(e.target.value)}
          />
        </div>
        <div className="ap-filters">
          {[
            { key: 'all', label: 'All' },
            { key: 'low', label: 'Low Stock' },
            { key: 'out', label: 'Out of Stock' },
          ].map(f => (
            <button
              key={f.key}
              className={`ap-filter-btn${filter === f.key ? ' ap-filter-btn--active' : ''}`}
              onClick={() => setFilter(f.key)}
            >
              {f.label}
            </button>
          ))}
        </div>
      </div>

      <ProductsTable
        products={filtered}
        loading={loading}
        onEdit={p => setModal({ mode: 'edit', product: p })}
        onDeleteRequest={setConfirmId}
        onAnalyze={p => runAnalysis(p.name, () => adminAnalyticsApi.analyzeProduct(p.id))}
      />

      {modal && (
        <ProductFormModal
          // Forces a fresh mount (and thus fresh internal form state) whenever
          // the target mode/product changes, since the modal owns its own
          // form state locally rather than being reset by the parent.
          key={`${modal.mode}-${modal.product?.id ?? 'new'}`}
          mode={modal.mode}
          product={modal.product}
          leafCategories={leafCategories}
          brands={brands}
          productTypes={productTypes}
          setProductTypes={setProductTypes}
          onClose={() => setModal(null)}
          onSaved={() => {
            setModal(null);
            loadProducts();
          }}
          showToast={showToast}
        />
      )}

      {confirmId && (
        <ConfirmModal
          title="Delete Product?"
          message="This product will be permanently removed from the catalog. This action cannot be undone."
          onConfirm={() => handleDelete(confirmId)}
          onCancel={() => setConfirmId(null)}
        />
      )}

      {analysis && <AnomalyReportModal {...analysis} onClose={closeAnalysis} />}
    </div>
  );
}
