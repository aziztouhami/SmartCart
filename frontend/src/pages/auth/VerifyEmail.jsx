import React, { useState, useEffect } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { CheckCircle2, XCircle, Loader2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import './Auth.css';
import { verifyEmail } from '../../services/authService';

const leftPanelBg = {
  backgroundImage: `url(${process.env.PUBLIC_URL}/assets/backgrounds/auth-bg.jpg)`,
};

export default function VerifyEmail() {
  const { t } = useTranslation('auth');
  const [searchParams] = useSearchParams();
  const token = searchParams.get('token');
  const [status, setStatus] = useState('loading'); // loading | success | error
  const [message, setMessage] = useState('');

  useEffect(() => {
    if (!token) {
      setStatus('error');
      setMessage(t('verifyEmail.missingToken'));
      return;
    }
    verifyEmail(token)
      .then(data => {
        setStatus('success');
        setMessage(data.message || t('verifyEmail.confirmed'));
      })
      .catch(err => {
        setStatus('error');
        setMessage(err.response?.data?.error || t('verifyEmail.invalidOrExpired'));
      });
  }, [token, t]);

  return (
    <div className="auth-page">
      <div className="auth-card">
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

        <div className="auth-right">
          <div className="auth-badge">{t('verifyEmail.badge')}</div>

          <div className="verify-status">
            {status === 'loading' && (
              <>
                <Loader2 size={42} className="verify-status__icon verify-status__icon--spin" />
                <h2 className="auth-title">{t('verifyEmail.confirming')}</h2>
              </>
            )}
            {status === 'success' && (
              <>
                <CheckCircle2 size={42} className="verify-status__icon verify-status__icon--ok" />
                <h2 className="auth-title">{t('verifyEmail.successTitle')}</h2>
                <p className="verify-status__msg">{message}</p>
                <Link to="/login" className="btn-submit" style={{ display: 'inline-block', textAlign: 'center', textDecoration: 'none' }}>
                  {t('verifyEmail.goToLogin')}
                </Link>
              </>
            )}
            {status === 'error' && (
              <>
                <XCircle size={42} className="verify-status__icon verify-status__icon--err" />
                <h2 className="auth-title">{t('verifyEmail.errorTitle')}</h2>
                <p className="verify-status__msg">{message}</p>
                <Link to="/login" className="btn-submit" style={{ display: 'inline-block', textAlign: 'center', textDecoration: 'none' }}>
                  {t('verifyEmail.backToLogin')}
                </Link>
              </>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
