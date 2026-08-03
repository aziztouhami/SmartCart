import React from 'react';
import { useTranslation } from 'react-i18next';
import ProductCard, { SkeletonCard } from '../../../components/ProductCard';
import { Button } from '../../../components/ui';

// ── Horizontal product row (used for all homepage sections) ───────────────────
export default function ProductRow({
  icon: Icon,
  title,
  subtitle,
  products,
  loading,
  viewAllTo,
  onViewAll,
  skeletonCount = 6,
}) {
  const { t } = useTranslation('home');
  if (!loading && products.length === 0) return null;

  return (
    <div className="h-section">
      <div className="h-section-header">
        <div className="h-section-left">
          <span className="h-section-emoji">
            <Icon size={22} />
          </span>
          <div>
            <h2 className="h-section-title">{title}</h2>
            {subtitle && <p className="h-section-sub">{subtitle}</p>}
          </div>
        </div>
        {viewAllTo && (
          <Button variant="outline" size="sm" className="h-btn-viewall" onClick={onViewAll}>
            {t('viewAll')}
          </Button>
        )}
      </div>

      <div className="h-scroll">
        {loading
          ? Array.from({ length: skeletonCount }).map((_, i) => <SkeletonCard key={i} />)
          : products.map(p => <ProductCard key={p.id} product={p} />)}
      </div>
    </div>
  );
}
