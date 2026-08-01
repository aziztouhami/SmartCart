import React, { useState, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Eye, EyeOff } from 'lucide-react';
import './Auth.css';
import { register } from '../../services/authService';
import { categoryApi, brandApi } from '../../services/cartService';

function passwordStrength(password, t) {
  if (!password) return { score: 0, label: '', color: 'transparent' };
  let score = 0;
  if (password.length >= 8)             score++;
  if (/[A-Z]/.test(password))           score++;
  if (/[0-9]/.test(password))           score++;
  if (/[^A-Za-z0-9]/.test(password))   score++;
  const map = [
    { label: t('register.strength.tooShort'), color: '#ef4444' },
    { label: t('register.strength.weak'),     color: '#f97316' },
    { label: t('register.strength.fair'),     color: '#eab308' },
    { label: t('register.strength.good'),     color: '#22c55e' },
    { label: t('register.strength.strong'),   color: '#16a34a' },
  ];
  return { score, ...map[score] };
}

const leftPanelBg = {
  backgroundImage: `url(${process.env.PUBLIC_URL}/assets/backgrounds/auth-bg.jpg)`,
};

export default function Register() {
  const { t } = useTranslation('auth');
  const navigate = useNavigate();
  const [form, setForm] = useState({
    firstName: '', lastName: '', email: '', password: '', confirmPassword: '', marketingOptIn: false,
  });
  const [showPassword, setShowPassword]               = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);
  const [error, setError]                             = useState('');
  const [loading, setLoading]                         = useState(false);

  const [leafCategories, setLeafCategories] = useState([]);
  const [brands, setBrands]                 = useState([]);
  const [preferredCategoryIds, setPreferredCategoryIds] = useState([]);
  const [preferredBrandIds, setPreferredBrandIds]       = useState([]);

  useEffect(() => {
    categoryApi.list()
      .then(res => {
        const tree = res.data || [];
        setLeafCategories(tree.flatMap(parent => parent.children.map(child => ({ id: child.id, name: child.name }))));
      })
      .catch(() => {});
    brandApi.list(1, 24)
      .then(res => setBrands(res.data.data || []))
      .catch(() => {});
  }, []);

  const toggleId = (list, setList, id) => {
    setList(list.includes(id) ? list.filter(x => x !== id) : [...list, id]);
  };

  const strength = passwordStrength(form.password, t);

  const handleChange = (e) => {
    const { name, value, type, checked } = e.target;
    setForm((prev) => ({ ...prev, [name]: type === 'checkbox' ? checked : value }));
    if (error) setError('');
  };

  const validate = () => {
    if (!form.firstName || !form.lastName)      return t('register.errors.nameRequired');
    if (!form.email)                            return t('register.errors.emailRequired');
    if (!/\S+@\S+\.\S+/.test(form.email))      return t('register.errors.emailInvalid');
    if (form.password.length < 8)              return t('register.errors.passwordTooShort');
    if (form.password !== form.confirmPassword) return t('register.errors.passwordsMismatch');
    return null;
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    const err = validate();
    if (err) { setError(err); return; }
    setLoading(true);
    try {
      await register(
        form.firstName, form.lastName, form.email, form.password, form.marketingOptIn,
        preferredCategoryIds, preferredBrandIds,
      );
      navigate('/login', {
        state: { checkEmail: true, prefillEmail: form.email },
        replace: true,
      });
    } catch (err) {
      const message = err.response?.data?.error || t('register.errors.registrationFailed');
      setError(message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="auth-page">
      <div className="auth-card">

        {/* ── Left: image + branding ── */}
        <div className="auth-left">
          <div className="auth-left__bg" style={leftPanelBg} />
          <div className="auth-left__overlay" />
          <div className="auth-left__content">
            <h1 className="auth-left__title">
              {t('leftPanel.welcomeTo')}<span>SmartCart</span>
            </h1>
            <div className="auth-left__divider" />
            <p className="auth-left__subtitle">
              {t('leftPanel.subtitle')}
            </p>
          </div>
        </div>

        {/* ── Right: register form ── */}
        <div className="auth-right">
          <div className="auth-badge">{t('register.badge')}</div>

          <h2 className="auth-title">{t('register.title')}</h2>

          <form className="auth-form" onSubmit={handleSubmit} noValidate>
            {error && <div className="auth-error" data-testid="register-error">{error}</div>}

            <div className="form-row">
              <div className="field-group">
                <label className="field-group__label" htmlFor="firstName">{t('register.firstNameLabel')}</label>
                <div className="field-group__input-wrap">
                  <input
                    id="firstName" name="firstName" type="text"
                    data-testid="register-firstName"
                    className="field-group__input field-group__input--no-icon"
                    placeholder={t('register.firstNamePlaceholder')}
                    value={form.firstName} onChange={handleChange}
                    autoComplete="given-name"
                  />
                </div>
              </div>

              <div className="field-group">
                <label className="field-group__label" htmlFor="lastName">{t('register.lastNameLabel')}</label>
                <div className="field-group__input-wrap">
                  <input
                    id="lastName" name="lastName" type="text"
                    data-testid="register-lastName"
                    className="field-group__input field-group__input--no-icon"
                    placeholder={t('register.lastNamePlaceholder')}
                    value={form.lastName} onChange={handleChange}
                    autoComplete="family-name"
                  />
                </div>
              </div>
            </div>

            <div className="field-group">
              <label className="field-group__label" htmlFor="email">{t('register.emailLabel')}</label>
              <div className="field-group__input-wrap">
                <input
                  id="email" name="email" type="email"
                  data-testid="register-email"
                  className="field-group__input field-group__input--no-icon"
                  placeholder={t('register.emailPlaceholder')}
                  value={form.email} onChange={handleChange}
                  autoComplete="email"
                />
              </div>
            </div>

            <div className="field-group">
              <label className="field-group__label" htmlFor="password">{t('register.passwordLabel')}</label>
              <div className="field-group__input-wrap">
                <input
                  id="password" name="password"
                  type={showPassword ? 'text' : 'password'}
                  data-testid="register-password"
                  className="field-group__input"
                  placeholder={t('register.passwordPlaceholder')}
                  value={form.password} onChange={handleChange}
                  autoComplete="new-password"
                />
                <button type="button" className="field-group__toggle"
                  onClick={() => setShowPassword((v) => !v)}
                  aria-label={showPassword ? t('register.hidePassword') : t('register.showPassword')}>
                  {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
                </button>
              </div>
              {form.password && (
                <div>
                  <div className="strength-bar">
                    {[1, 2, 3, 4].map((i) => (
                      <div key={i} className="strength-bar__segment"
                        style={{ background: i <= strength.score ? strength.color : '#E6F1FB' }} />
                    ))}
                  </div>
                  <span className="strength-bar__label" style={{ color: strength.color }}>
                    {strength.label}
                  </span>
                </div>
              )}
            </div>

            <div className="field-group">
              <label className="field-group__label" htmlFor="confirmPassword">{t('register.confirmPasswordLabel')}</label>
              <div className="field-group__input-wrap">
                <input
                  id="confirmPassword" name="confirmPassword"
                  type={showConfirmPassword ? 'text' : 'password'}
                  data-testid="register-confirmPassword"
                  className="field-group__input"
                  placeholder={t('register.confirmPasswordPlaceholder')}
                  value={form.confirmPassword} onChange={handleChange}
                  autoComplete="new-password"
                />
                <button type="button" className="field-group__toggle"
                  onClick={() => setShowConfirmPassword((v) => !v)}
                  aria-label={showConfirmPassword ? t('register.hideConfirmPassword') : t('register.showConfirmPassword')}>
                  {showConfirmPassword ? <EyeOff size={18} /> : <Eye size={18} />}
                </button>
              </div>
            </div>

            {(leafCategories.length > 0 || brands.length > 0) && (
              <div className="field-group">
                <label className="field-group__label">{t('register.preferencesLabel')}</label>
                <p className="auth-pref-hint">{t('register.preferencesHint')}</p>

                {leafCategories.length > 0 && (
                  <div className="auth-pref-chips">
                    {leafCategories.map(c => (
                      <button
                        type="button"
                        key={c.id}
                        className={`auth-pref-chip${preferredCategoryIds.includes(c.id) ? ' auth-pref-chip--active' : ''}`}
                        onClick={() => toggleId(preferredCategoryIds, setPreferredCategoryIds, c.id)}
                      >
                        {c.name}
                      </button>
                    ))}
                  </div>
                )}

                {brands.length > 0 && (
                  <div className="auth-pref-chips">
                    {brands.map(b => (
                      <button
                        type="button"
                        key={b.id}
                        className={`auth-pref-chip${preferredBrandIds.includes(b.id) ? ' auth-pref-chip--active' : ''}`}
                        onClick={() => toggleId(preferredBrandIds, setPreferredBrandIds, b.id)}
                      >
                        {b.name}
                      </button>
                    ))}
                  </div>
                )}
              </div>
            )}

            <label className="checkbox-row">
              <input
                type="checkbox" name="marketingOptIn"
                checked={form.marketingOptIn} onChange={handleChange}
              />
              <span>{t('register.marketingOptIn')}</span>
            </label>

            <button type="submit" className="btn-submit" data-testid="register-submit" disabled={loading}>
              {loading ? t('register.creatingAccount') : t('register.createAccountButton')}
            </button>

            <p className="auth-terms">
              {t('register.termsPrefix')}{' '}
              <a href="#terms">{t('register.termsOfService')}</a> {t('register.and')}{' '}
              <a href="#privacy">{t('register.privacyPolicy')}</a>.
            </p>
          </form>

          <div className="auth-links">
            <p className="auth-links__secondary">
              {t('register.alreadyHaveAccount')} <Link to="/login">{t('register.signIn')}</Link>
            </p>
          </div>
        </div>

      </div>
    </div>
  );
}
