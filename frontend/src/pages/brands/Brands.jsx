import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Tag } from 'lucide-react';
import Navbar from '../../components/Navbar';
import { brandApi } from '../../services/cartService';
import './Brands.css';

function BrandCard({ brand, onClick }) {
  const { t } = useTranslation('brands');
  return (
    <button className="bd-card" onClick={onClick}>
      <div className="bd-card-logo">
        {brand.image
          ? <img src={brand.image} alt={brand.name} className="bd-card-img" />
          : <span className="bd-card-initial">{brand.name.charAt(0).toUpperCase()}</span>
        }
      </div>
      <span className="bd-card-name">{brand.name}</span>
      {brand.description && <p className="bd-card-desc">{brand.description}</p>}
      <span className="bd-card-count">{t('productCount', { count: brand.productCount })}</span>
    </button>
  );
}

function SkeletonCard() {
  return (
    <div className="bd-card bd-card--skeleton">
      <div className="bd-skeleton bd-skeleton--logo" />
      <div className="bd-skeleton bd-skeleton--name" />
      <div className="bd-skeleton bd-skeleton--count" />
    </div>
  );
}

export default function Brands() {
  const { t } = useTranslation('brands');
  const navigate = useNavigate();
  const [brands, setBrands]   = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    brandApi.list(1, 100)
      .then(res => setBrands(res.data.data || []))
      .catch(() => setBrands([]))
      .finally(() => setLoading(false));
  }, []);

  return (
    <div className="h-page">
      <Navbar />

      <main className="h-main">
        <div className="h-container">
          <div className="bd-header">
            <Tag size={22} className="bd-header-icon" />
            <div>
              <h2 className="bd-title">{t('title')}</h2>
              <p className="bd-sub">{t('subtitle')}</p>
            </div>
            {!loading && <span className="bd-total-count">{t('brandCount', { count: brands.length })}</span>}
          </div>

          {loading ? (
            <div className="bd-grid">
              {Array.from({ length: 12 }).map((_, i) => <SkeletonCard key={i} />)}
            </div>
          ) : brands.length === 0 ? (
            <div className="bd-empty">
              <Tag size={28} />
              <h3>{t('empty.title')}</h3>
            </div>
          ) : (
            <div className="bd-grid">
              {brands.map(b => (
                <BrandCard
                  key={b.id}
                  brand={b}
                  onClick={() => navigate(`/?q=${encodeURIComponent(b.name)}`)}
                />
              ))}
            </div>
          )}
        </div>
      </main>
    </div>
  );
}
