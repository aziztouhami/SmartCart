import React, { useState } from 'react';
import { Link, useNavigate, useLocation } from 'react-router-dom';
import { useGoogleLogin } from '@react-oauth/google';
import { useTranslation } from 'react-i18next';
import { Eye, EyeOff } from 'lucide-react';
import './Auth.css';
import { login, googleLogin, resendVerification } from '../../services/authService';
import { useCart } from '../../context/CartContext';
import { useFavorites } from '../../context/FavoriteContext';

const leftPanelBg = {
  backgroundImage: `url(${process.env.PUBLIC_URL}/assets/backgrounds/auth-bg.jpg)`,
};

export default function Login() {
  const { t }                           = useTranslation('auth');
  const navigate                        = useNavigate();
  const location                        = useLocation();
  const { syncWithBackend }             = useCart();
  const { loadFavorites }               = useFavorites();
  const [form, setForm]                 = useState({
    email:    location.state?.prefillEmail    ?? '',
    password: '',
  });
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError]               = useState('');
  const [errorCode, setErrorCode]       = useState('');
  const [loading, setLoading]           = useState(false);
  const [resendState, setResendState]   = useState(''); // '' | 'sending' | 'sent'
  const checkEmail                      = location.state?.checkEmail ?? false;

  const signInWithGoogle = useGoogleLogin({
    onSuccess: async (tokenResponse) => {
      setLoading(true);
      setError('');
      try {
        const data = await googleLogin(tokenResponse.access_token);
        const isAdminUser = data.user?.roles?.includes('ROLE_ADMIN');
        await Promise.all([syncWithBackend(), loadFavorites()]);
        const from = location.state?.from;
        navigate(isAdminUser ? '/admin' : (from || '/'), { replace: true });
      } catch (err) {
        setError(err.response?.data?.error || t('login.googleSignInFailed'));
      } finally {
        setLoading(false);
      }
    },
    onError: () => setError(t('login.googleSignInFailed')),
  });

  const handleChange = (e) => {
    const { name, value } = e.target;
    setForm((prev) => ({ ...prev, [name]: value }));
    if (error) { setError(''); setErrorCode(''); }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!form.email || !form.password) { setError(t('login.fillAllFields')); return; }
    setLoading(true);
    setResendState('');
    try {
      const data = await login(form.email, form.password);
      const isAdminUser = data.user?.roles?.includes('ROLE_ADMIN');
      // Sync cart and load favorites after login
      await Promise.all([syncWithBackend(), loadFavorites()]);
      const from = location.state?.from;
      navigate(isAdminUser ? '/admin' : (from || '/'), { replace: true });
    } catch (err) {
      const message = err.response?.data?.error || t('login.invalidCredentials');
      setError(message);
      setErrorCode(err.response?.data?.code || '');
    } finally {
      setLoading(false);
    }
  };

  const handleResend = async () => {
    setResendState('sending');
    try {
      await resendVerification(form.email);
      setResendState('sent');
    } catch {
      setResendState('');
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

        {/* ── Right: login form ── */}
        <div className="auth-right">
          <div className="auth-badge">{t('login.badge')}</div>

          <h2 className="auth-title">{t('login.title')}</h2>

          {/* Social login */}
          <div className="auth-social">
            <button type="button" className="auth-social-btn auth-social-btn--google" onClick={() => signInWithGoogle()}>
              <svg viewBox="0 0 24 24" width="18" height="18">
                <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
                <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
              </svg>
              {t('login.googleButton')}
            </button>
            <button type="button" className="auth-social-btn auth-social-btn--facebook">
              <svg viewBox="0 0 24 24" width="18" height="18" fill="#ffffff">
                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
              </svg>
              {t('login.facebookButton')}
            </button>
            <button type="button" className="auth-social-btn auth-social-btn--apple">
              <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor">
                <path d="M12.152 6.896c-.948 0-2.415-1.078-3.96-1.04-2.04.027-3.91 1.183-4.961 3.014-2.117 3.675-.546 9.103 1.519 12.09 1.013 1.454 2.208 3.09 3.792 3.039 1.52-.065 2.09-.987 3.935-.987 1.831 0 2.35.987 3.96.948 1.637-.026 2.676-1.48 3.676-2.948 1.156-1.688 1.636-3.325 1.662-3.415-.039-.013-3.182-1.221-3.22-4.857-.026-3.04 2.48-4.494 2.597-4.559-1.429-2.09-3.623-2.324-4.39-2.376-2-.156-3.675 1.09-4.61 1.09zM15.53 3.83c.843-1.012 1.4-2.427 1.245-3.83-1.207.052-2.662.805-3.532 1.818-.78.896-1.454 2.338-1.273 3.714 1.338.104 2.715-.688 3.559-1.701"/>
              </svg>
              {t('login.appleButton')}
            </button>
          </div>

          <div className="auth-divider"><span>{t('login.orContinueWith')}</span></div>

          <form className="auth-form" onSubmit={handleSubmit} noValidate>
            {checkEmail && !error && (
              <div className="auth-info">
                {t('login.registrationSuccess', { email: form.email })}
              </div>
            )}
            {error && (
              <div className="auth-error" data-testid="login-error">
                {error}
                {errorCode === 'EMAIL_NOT_VERIFIED' && (
                  resendState === 'sent' ? (
                    <p className="auth-error__hint">{t('login.confirmationResent')}</p>
                  ) : (
                    <button type="button" className="auth-error__action" onClick={handleResend} disabled={resendState === 'sending'}>
                      {resendState === 'sending' ? t('login.resending') : t('login.resendConfirmation')}
                    </button>
                  )
                )}
              </div>
            )}

            <div className="field-group">
              <label className="field-group__label" htmlFor="email">{t('login.emailLabel')}</label>
              <div className="field-group__input-wrap">
                <input
                  id="email"
                  name="email"
                  type="email"
                  data-testid="login-email"
                  className="field-group__input field-group__input--no-icon"
                  placeholder={t('login.emailPlaceholder')}
                  value={form.email}
                  onChange={handleChange}
                  autoComplete="email"
                />
              </div>
            </div>

            <div className="field-group">
              <label className="field-group__label" htmlFor="password">{t('login.passwordLabel')}</label>
              <div className="field-group__input-wrap">
                <input
                  id="password"
                  name="password"
                  type={showPassword ? 'text' : 'password'}
                  data-testid="login-password"
                  className="field-group__input"
                  placeholder="••••••••"
                  value={form.password}
                  onChange={handleChange}
                  autoComplete="current-password"
                />
                <button
                  type="button"
                  className="field-group__toggle"
                  onClick={() => setShowPassword((v) => !v)}
                  aria-label={showPassword ? t('login.hidePassword') : t('login.showPassword')}
                >
                  {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
                </button>
              </div>
            </div>

            <button type="submit" className="btn-submit" data-testid="login-submit" disabled={loading}>
              {loading ? t('login.signingIn') : t('login.signIn')}
            </button>
          </form>

          <div className="auth-links">
            <p className="auth-links__secondary">
              {t('login.noAccount')} <Link to="/register">{t('login.createAccount')}</Link>
            </p>
            <button className="auth-links__forgot">{t('login.forgotPassword')}</button>
          </div>
        </div>

      </div>
    </div>
  );
}
