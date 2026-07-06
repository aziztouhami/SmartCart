import React, { useState, useRef, useEffect } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Flame, Tag } from 'lucide-react';
import { getUser, isAuthenticated, logout } from '../services/authService';
import { useCart } from '../context/CartContext';
import { useFavorites } from '../context/FavoriteContext';
import { useCategories } from '../context/CategoryContext';
import { productApi } from '../services/cartService';
import { CATEGORY_ICONS, DEFAULT_CATEGORY_ICON } from '../constants/categoryIcons';
import { formatPrice } from '../utils/format';
import { HeartIcon } from './ui';
import LanguageSwitcher from './LanguageSwitcher';
import './Navbar.css';

function UserMenu() {
  const { t } = useTranslation('navbar');
  const navigate = useNavigate();
  const [open, setOpen] = useState(false);
  const ref = useRef();
  const user = getUser();
  const { resetCart } = useCart();

  useEffect(() => {
    const handler = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, []);

  const handleLogout = () => { logout(); resetCart(); navigate('/login'); };
  const initials = user
    ? `${user.firstName?.[0] ?? ''}${user.lastName?.[0] ?? ''}`.toUpperCase() || '?'
    : '?';

  return (
    <div className="h-user-wrap" ref={ref}>
      <button className="h-user-btn" onClick={() => setOpen(o => !o)}>
        <div className="h-user-avatar">{initials}</div>
        <span className="h-user-name">{user?.firstName ?? t('account')}</span>
        <svg className={`h-chevron ${open ? 'h-chevron--open' : ''}`} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" width="13" height="13">
          <polyline points="6 9 12 15 18 9" />
        </svg>
      </button>

      {open && (
        <div className="h-user-dropdown">
          <div className="h-user-info">
            <div className="h-user-avatar h-user-avatar--lg">{initials}</div>
            <div>
              <div className="h-user-fullname">{user?.firstName} {user?.lastName}</div>
              <div className="h-user-email">{user?.email}</div>
            </div>
          </div>
          <div className="h-user-divider" />
          <button className="h-user-item" onClick={() => { setOpen(false); navigate('/profile'); }}>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" width="15" height="15">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
            </svg>
            {t('myProfile')}
          </button>
          <button className="h-user-item" onClick={() => { setOpen(false); navigate('/favorites'); }}>
            <HeartIcon size={15} />
            {t('myFavorites')}
          </button>
          <button className="h-user-item" onClick={() => { setOpen(false); navigate('/orders'); }}>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" width="15" height="15">
              <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
            </svg>
            {t('myOrders')}
          </button>
          {user?.roles?.includes('ROLE_ADMIN') && (
            <button className="h-user-item" onClick={() => { setOpen(false); navigate('/admin'); }}>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" width="15" height="15">
                <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
                <rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>
              </svg>
              {t('adminPanel')}
            </button>
          )}
          <div className="h-user-divider" />
          <button className="h-user-item h-user-item--danger" onClick={handleLogout}>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" width="15" height="15">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
              <polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
            {t('logOut')}
          </button>
        </div>
      )}
    </div>
  );
}

function highlight(text, query) {
  const words = query.trim().split(/\s+/).filter(Boolean);
  if (!words.length) return text;
  const escaped = words.map(w => w.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
  const regex = new RegExp(`(${escaped.join('|')})`, 'gi');
  return text.split(regex).map((part, i) =>
    regex.test(part) ? <mark key={i} className="h-sugg-mark">{part}</mark> : part
  );
}

const EMPTY_GROUPS = { nameStart: [], nameContains: [], byBrand: [], byCategory: [] };

function SuggItem({ product, flatIndex, activeSugg, onSelect, onHover, query }) {
  const { t } = useTranslation('navbar');
  return (
    <button
      className={`h-sugg-item${activeSugg === flatIndex ? ' h-sugg-item--active' : ''}`}
      onMouseDown={e => { e.preventDefault(); onSelect(product.id); }}
      onMouseEnter={() => onHover(flatIndex)}
    >
      <div className="h-sugg-left">
        <span className="h-sugg-name">{highlight(product.name, query)}</span>
        <span className="h-sugg-meta">
          {product.brand    && <span className="h-sugg-brand">{highlight(product.brand.name, query)}</span>}
          {product.category && <span className="h-sugg-cat">{product.category.name}</span>}
          {!product.inStock && <span className="h-sugg-out">{t('outOfStock')}</span>}
        </span>
      </div>
      <span className="h-sugg-price">
        {formatPrice(product.price)} TND
      </span>
    </button>
  );
}

export default function Navbar() {
  const { t } = useTranslation('navbar');
  const navigate = useNavigate();
  const { cartCount } = useCart();
  const { favCount } = useFavorites();
  const [searchParams] = useSearchParams();
  const urlQ = searchParams.get('q') || '';
  const [query, setQuery] = useState(urlQ);
  const [groups, setGroups] = useState(EMPTY_GROUPS);
  const [activeSugg, setActiveSugg] = useState(-1);
  const searchRef = useRef();
  const loggedIn = isAuthenticated();

  const { categories } = useCategories();
  const [hoveredCatId, setHoveredCatId] = useState(null);

  const flatSugg = [
    ...groups.nameStart,
    ...groups.nameContains,
    ...groups.byBrand,
    ...groups.byCategory,
  ];
  const hasResults = flatSugg.length > 0;

  useEffect(() => { setQuery(urlQ); }, [urlQ]);

  useEffect(() => {
    if (query.trim().length < 1) { setGroups(EMPTY_GROUPS); setActiveSugg(-1); return; }
    const controller = new AbortController();
    const timer = setTimeout(() => {
      productApi.autocomplete(query.trim(), { signal: controller.signal })
        .then(res => {
          const data = res.data || EMPTY_GROUPS;
          setGroups({
            nameStart:    data.nameStart    || [],
            nameContains: data.nameContains || [],
            byBrand:      data.byBrand      || [],
            byCategory:   data.byCategory   || [],
          });
          setActiveSugg(-1);
        })
        .catch(() => { if (!controller.signal.aborted) setGroups(EMPTY_GROUPS); });
    }, 200);
    return () => { clearTimeout(timer); controller.abort(); };
  }, [query]);

  useEffect(() => {
    const handler = (e) => {
      if (searchRef.current && !searchRef.current.contains(e.target)) {
        setGroups(EMPTY_GROUPS); setActiveSugg(-1);
      }
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, []);

  const closeSugg = () => { setGroups(EMPTY_GROUPS); setActiveSugg(-1); };

  const submitSearch = () => {
    const q = query.trim();
    closeSugg();
    navigate(q ? `/?q=${encodeURIComponent(q)}` : '/');
  };

  const handleKeyDown = (e) => {
    if (!hasResults) { if (e.key === 'Enter') submitSearch(); return; }
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      setActiveSugg(a => Math.min(a + 1, flatSugg.length - 1));
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      setActiveSugg(a => Math.max(a - 1, -1));
    } else if (e.key === 'Enter') {
      if (activeSugg >= 0) { navigate(`/product/${flatSugg[activeSugg].id}`); closeSugg(); setQuery(''); }
      else { submitSearch(); }
    } else if (e.key === 'Escape') {
      closeSugg();
    }
  };

  const clearSearch = () => { setQuery(''); navigate('/'); };

  const nameStartOffset    = 0;
  const nameContainsOffset = groups.nameStart.length;
  const byBrandOffset      = nameContainsOffset + groups.nameContains.length;
  const byCategoryOffset   = byBrandOffset + groups.byBrand.length;
  const handleSelect = (id) => { navigate(`/product/${id}`); closeSugg(); setQuery(''); };

  const hoveredCat   = categories.find(c => c.id === hoveredCatId) || null;
  const megaChildren = hoveredCat?.children || [];

  return (
    <>
      {/* ── Main navbar ── */}
      <nav className="h-navbar">

        {/* Row 1: Logo | Search | Favorites | Cart | Auth */}
        <div className="h-navbar-top">
          <div className="h-logo" onClick={() => navigate('/')} style={{ cursor: 'pointer' }}>
            <span className="h-logo-icon">S</span>
            <span className="h-logo-text">SmartCart</span>
          </div>

          <div className="h-search" ref={searchRef}>
            <button className="h-search-icon-btn" onClick={submitSearch} title={t('searchTitle')}>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2">
                <circle cx="11" cy="11" r="8" /><path d="m21 21-4.35-4.35" />
              </svg>
            </button>
            <input
              type="text"
              className="h-search-input"
              placeholder={t('searchPlaceholder')}
              value={query}
              onChange={e => setQuery(e.target.value)}
              onKeyDown={handleKeyDown}
            />
            {query && <button className="h-search-clear" onClick={clearSearch}>✕</button>}
            {hasResults && (
              <div className="h-sugg">
                {groups.nameStart.map((s, i) => (
                  <SuggItem key={s.id} product={s} flatIndex={nameStartOffset + i}
                    activeSugg={activeSugg} onSelect={handleSelect} onHover={setActiveSugg} query={query} />
                ))}
                {groups.nameContains.length > 0 && (
                  <>
                    {groups.nameStart.length > 0 && <div className="h-sugg-section">{t('otherNameMatches')}</div>}
                    {groups.nameContains.map((s, i) => (
                      <SuggItem key={s.id} product={s} flatIndex={nameContainsOffset + i}
                        activeSugg={activeSugg} onSelect={handleSelect} onHover={setActiveSugg} query={query} />
                    ))}
                  </>
                )}
                {groups.byBrand.length > 0 && (
                  <>
                    <div className="h-sugg-section">{t('byBrand')}</div>
                    {groups.byBrand.map((s, i) => (
                      <SuggItem key={s.id} product={s} flatIndex={byBrandOffset + i}
                        activeSugg={activeSugg} onSelect={handleSelect} onHover={setActiveSugg} query={query} />
                    ))}
                  </>
                )}
                {groups.byCategory.length > 0 && (
                  <>
                    <div className="h-sugg-section">{t('byCategory')}</div>
                    {groups.byCategory.map((s, i) => (
                      <SuggItem key={s.id} product={s} flatIndex={byCategoryOffset + i}
                        activeSugg={activeSugg} onSelect={handleSelect} onHover={setActiveSugg} query={query} />
                    ))}
                  </>
                )}
                <button className="h-sugg-footer" onMouseDown={e => { e.preventDefault(); submitSearch(); }}>
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" width="13" height="13">
                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                  </svg>
                  {t('seeAllResultsFor')} <strong>&ldquo;{query.trim()}&rdquo;</strong>
                </button>
              </div>
            )}
          </div>

          <LanguageSwitcher />

          {/* Brands */}
          <button className="h-icon-btn" title={t('brandsTitle')} onClick={() => navigate('/brands')}>
            <Tag size={20} />
          </button>

          {/* Favorites */}
          <button
            className="h-icon-btn"
            title={t('favoritesTitle')}
            onClick={() => loggedIn ? navigate('/favorites') : navigate('/login', { state: { from: '/favorites' } })}
          >
            <HeartIcon size={20} filled={loggedIn && favCount > 0} />
            {loggedIn && favCount > 0 && <span className="h-icon-badge">{favCount}</span>}
          </button>

          {/* Cart */}
          <button className="h-icon-btn" title={t('cartTitle')} onClick={() => navigate('/cart')}>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" width="20" height="20">
              <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
              <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
            </svg>
            {cartCount > 0 && <span className="h-icon-badge">{cartCount}</span>}
          </button>

          {/* Auth: UserMenu when logged in, Sign In + Create Account when not */}
          {loggedIn ? <UserMenu /> : (
            <div className="h-auth-btns">
              <button className="h-btn-signin" onClick={() => navigate('/login')}>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" width="15" height="15">
                  <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                </svg>
                {t('signIn')}
              </button>
              <button className="h-btn-register" onClick={() => navigate('/register')}>
                {t('createAccount')}
              </button>
            </div>
          )}
        </div>

      </nav>

      {/* ── Category bar + mega dropdown ── */}
      <div className="h-navcat-bar" onMouseLeave={() => setHoveredCatId(null)}>
        <div className="h-navcat-list">
          <button
            className="h-navcat-promo"
            onMouseEnter={() => setHoveredCatId(null)}
            onClick={() => { navigate('/promotions'); setHoveredCatId(null); }}
          >
            <Flame size={14} className="h-navcat-icon" /> {t('promotions')}
          </button>
          {categories.map(cat => {
            const Icon = CATEGORY_ICONS[cat.name] || DEFAULT_CATEGORY_ICON;
            return (
            <button
              key={cat.id}
              className={`h-navcat-pill${hoveredCatId === cat.id ? ' h-navcat-pill--active' : ''}`}
              onMouseEnter={() => setHoveredCatId(cat.id)}
              onClick={() => navigate(`/?cat=${cat.id}`)}
            >
              <span className="h-navcat-icon"><Icon size={14} /></span>
              {cat.name}
            </button>
            );
          })}
        </div>

        {hoveredCatId && megaChildren.length > 0 && (
          <div className="h-navcat-mega">
            <div className="h-navcat-mega-inner">
              <span className="h-navcat-mega-title">
                {(() => {
                  const Icon = CATEGORY_ICONS[hoveredCat?.name] || DEFAULT_CATEGORY_ICON;
                  return <Icon size={14} className="h-navcat-icon" />;
                })()}
                {hoveredCat?.name}
              </span>
              <div className="h-navcat-mega-children">
                {megaChildren.map(child => (
                  <button
                    key={child.id}
                    className="h-navcat-mega-item"
                    onClick={() => { navigate(`/?cat=${child.id}`); setHoveredCatId(null); }}
                  >
                    {child.name}
                  </button>
                ))}
              </div>
            </div>
          </div>
        )}
      </div>
    </>
  );
}
