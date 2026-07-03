import React, { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { Percent } from 'lucide-react';
import Navbar from '../../components/Navbar';
import ProductCard, { SkeletonCard } from '../../components/ProductCard';
import { productApi } from '../../services/cartService';
import './Promotions.css';

export default function Promotions() {
  const { t } = useTranslation('promotions');
  const [promotions, setPromotions] = useState([]);
  const [loading, setLoading]       = useState(true);

  useEffect(() => {
    productApi.promotions(50)
      .then(res => setPromotions(res.data || []))
      .catch(() => setPromotions([]))
      .finally(() => setLoading(false));
  }, []);

  return (
    <div className="h-page">
      <Navbar />

      <main className="h-main">
        <div className="h-container">
          <div className="pr-page-header">
            <Percent size={22} className="pr-page-icon" />
            <div>
              <h2 className="pr-page-title">{t('title')}</h2>
              <p className="pr-page-sub">{t('subtitle')}</p>
            </div>
            {!loading && promotions.length > 0 && (
              <span className="pr-page-count">{t('promotedCount', { count: promotions.length })}</span>
            )}
          </div>

          {loading ? (
            <div className="h-grid">
              {Array.from({ length: 10 }).map((_, i) => <SkeletonCard key={i} />)}
            </div>
          ) : promotions.length === 0 ? (
            <div className="pr-page-empty">
              <Percent size={28} />
              <h3>{t('empty.title')}</h3>
              <p>{t('empty.subtitle')}</p>
            </div>
          ) : (
            <div className="h-grid">
              {promotions.map(p => <ProductCard key={p.id} product={p} />)}
            </div>
          )}
        </div>
      </main>
    </div>
  );
}
