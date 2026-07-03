import React from 'react';
import { useTranslation } from 'react-i18next';
import './LanguageSwitcher.css';

const LANGUAGES = [
  { code: 'en', label: 'EN' },
  { code: 'fr', label: 'FR' },
];

export default function LanguageSwitcher() {
  const { i18n, t } = useTranslation('common');
  const current = i18n.language?.startsWith('fr') ? 'fr' : 'en';

  return (
    <div className="h-lang-switch" role="group" aria-label={t('language')}>
      {LANGUAGES.map(({ code, label }) => (
        <button
          key={code}
          className={`h-lang-btn${current === code ? ' h-lang-btn--active' : ''}`}
          onClick={() => i18n.changeLanguage(code)}
          title={code === 'en' ? t('languageEnglish') : t('languageFrench')}
        >
          {label}
        </button>
      ))}
    </div>
  );
}
