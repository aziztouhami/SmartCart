import React, { useState, useEffect } from 'react';
import { productTypeApi, adminAnalyticsApi } from '../../services/cartService';
import { IconPlus, IconSearch } from '../../components/admin/AdminIcons';
import AdminToast from '../../components/admin/AdminToast';
import ConfirmModal from '../../components/admin/ConfirmModal';
import AnomalyReportModal from '../../components/admin/AnomalyReportModal';
import useAnalysis from '../../components/admin/useAnalysis';
import TypesTable from './AdminTypes/TypesTable';
import AddTypeModal from './AdminTypes/AddTypeModal';
import EditTypeModal from './AdminTypes/EditTypeModal';
import './AdminTypes.css';

export default function AdminTypes() {
  const [types, setTypes] = useState([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [modal, setModal] = useState(null);
  const [confirmId, setConfirmId] = useState(null);
  const [toast, setToast] = useState(null);
  const { analysis, runAnalysis, closeAnalysis } = useAnalysis();

  const showToast = (msg, type = 'success') => {
    setToast({ msg, type });
    setTimeout(() => setToast(null), 3000);
  };

  const loadTypes = () => {
    setLoading(true);
    return productTypeApi
      .list()
      .then(res => setTypes(res.data || []))
      .catch(() => showToast('Failed to load product types.', 'error'))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    loadTypes();
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  const filtered = types.filter(t => t.name.toLowerCase().includes(search.toLowerCase()));

  const handleDelete = async id => {
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
      {toast && <AdminToast msg={toast.msg} type={toast.type} />}

      <div className="adm-page-header">
        <div>
          <h1 className="adm-page-title">Types</h1>
          <p className="adm-page-sub">
            {types.length} product type{types.length !== 1 ? 's' : ''} &nbsp;·&nbsp;
            {types.reduce((sum, t) => sum + t.attributes.length, 0)} features total
          </p>
        </div>
        <button className="adm-btn-primary" onClick={() => setModal({ mode: 'add' })}>
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

      <TypesTable
        types={filtered}
        loading={loading}
        onEdit={type => setModal({ mode: 'edit', type })}
        onDeleteRequest={setConfirmId}
        onAnalyze={type =>
          runAnalysis(type.name, () => adminAnalyticsApi.analyzeProductType(type.id))
        }
      />

      {modal?.mode === 'add' && (
        <AddTypeModal
          onClose={() => setModal(null)}
          onCreated={() => {
            setModal(null);
            loadTypes();
          }}
          showToast={showToast}
        />
      )}

      {modal?.mode === 'edit' && (
        <EditTypeModal
          // Fresh mount per type being edited, since the modal owns its own
          // form/feature-editing state locally.
          key={modal.type.id}
          type={modal.type}
          onClose={() => setModal(null)}
          onRenamed={() => {
            setModal(null);
            loadTypes();
          }}
          onTypeUpdated={updated =>
            setTypes(prev => prev.map(t => (t.id === updated.id ? updated : t)))
          }
          showToast={showToast}
        />
      )}

      {confirmId && (
        <ConfirmModal
          title="Delete Type?"
          message="This type and its feature definitions will be permanently removed. Refused while any product still uses it."
          onConfirm={() => handleDelete(confirmId)}
          onCancel={() => setConfirmId(null)}
        />
      )}

      {analysis && <AnomalyReportModal {...analysis} onClose={closeAnalysis} />}
    </div>
  );
}
