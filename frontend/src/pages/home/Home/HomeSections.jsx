import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Wand2, TrendingUp, Clock, Percent } from 'lucide-react';
import { productApi, recommendationApi } from '../../../services/cartService';
import { CATEGORY_ICONS, DEFAULT_CATEGORY_ICON } from '../../../constants/categoryIcons';
import ProductRow from './ProductRow';

// ── Sectioned homepage (no filters active) ─────────────────────────────────────
export default function HomeSections({ categories }) {
  const { t } = useTranslation('home');
  const navigate = useNavigate();

  const [recommended, setRecommended] = useState([]);
  const [recommendedLoading, setRecommendedLoading] = useState(true);
  const [bestSellers, setBestSellers] = useState([]);
  const [bestSellersLoading, setBestSellersLoading] = useState(true);
  const [newest, setNewest] = useState([]);
  const [newestLoading, setNewestLoading] = useState(true);
  const [promoProducts, setPromoProducts] = useState([]);
  const [promoLoading, setPromoLoading] = useState(true);
  const [catProducts, setCatProducts] = useState({});
  const [catLoading, setCatLoading] = useState(true);

  useEffect(() => {
    // Session-based: driven by what this visitor (guest session or, if
    // logged in, their interaction history) has actually viewed/carted.
    // Empty with nothing to base it on yet (cold start) — ProductRow
    // already hides the whole section when products is empty, so a
    // brand-new visitor simply doesn't see it.
    recommendationApi
      .get(10)
      .then(res => setRecommended(res.data.recommendations || []))
      .catch(() => setRecommended([]))
      .finally(() => setRecommendedLoading(false));

    productApi
      .bestSellers(10)
      .then(res => setBestSellers(res.data || []))
      .catch(() => setBestSellers([]))
      .finally(() => setBestSellersLoading(false));

    productApi
      .list({ limit: 10, sort: 'createdAt', order: 'desc' })
      .then(res => setNewest(res.data.data || []))
      .catch(() => setNewest([]))
      .finally(() => setNewestLoading(false));

    productApi
      .promotions(10)
      .then(res => setPromoProducts(res.data || []))
      .catch(() => setPromoProducts([]))
      .finally(() => setPromoLoading(false));
  }, []);

  useEffect(() => {
    if (categories.length === 0) return;
    setCatLoading(true);
    Promise.all(
      categories.map(cat =>
        productApi
          .list({ category: cat.id, limit: 8, sort: 'createdAt', order: 'desc' })
          .then(res => [cat.id, res.data.data || []])
          .catch(() => [cat.id, []]),
      ),
    )
      .then(entries => setCatProducts(Object.fromEntries(entries)))
      .finally(() => setCatLoading(false));
  }, [categories]);

  return (
    <>
      <ProductRow
        icon={Wand2}
        title={t('sections.recommended.title')}
        subtitle={t('sections.recommended.subtitle')}
        products={recommended}
        loading={recommendedLoading}
      />

      <ProductRow
        icon={TrendingUp}
        title={t('sections.bestSellers.title')}
        subtitle={t('sections.bestSellers.subtitle')}
        products={bestSellers}
        loading={bestSellersLoading}
      />

      <ProductRow
        icon={Clock}
        title={t('sections.newest.title')}
        subtitle={t('sections.newest.subtitle')}
        products={newest}
        loading={newestLoading}
      />

      <ProductRow
        icon={Percent}
        title={t('sections.promotions.title')}
        subtitle={t('sections.promotions.subtitle')}
        products={promoProducts}
        loading={promoLoading}
        viewAllTo="/promotions"
        onViewAll={() => navigate('/promotions')}
      />

      {categories.map(cat => (
        <ProductRow
          key={cat.id}
          icon={CATEGORY_ICONS[cat.name] || DEFAULT_CATEGORY_ICON}
          title={cat.name}
          products={catProducts[cat.id] || []}
          loading={catLoading}
          viewAllTo={`/?cat=${cat.id}`}
          onViewAll={() => navigate(`/?cat=${cat.id}`)}
        />
      ))}
    </>
  );
}
