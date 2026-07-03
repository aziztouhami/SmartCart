import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import LanguageDetector from 'i18next-browser-languagedetector';

import enCommon from './locales/en/common.json';
import enNavbar from './locales/en/navbar.json';
import enHome from './locales/en/home.json';
import enProduct from './locales/en/product.json';
import enAuth from './locales/en/auth.json';
import enCart from './locales/en/cart.json';
import enFavorites from './locales/en/favorites.json';
import enOrders from './locales/en/orders.json';
import enProfile from './locales/en/profile.json';
import enProductDetail from './locales/en/productDetail.json';
import enBrands from './locales/en/brands.json';
import enPromotions from './locales/en/promotions.json';
import enChatbot from './locales/en/chatbot.json';

import frCommon from './locales/fr/common.json';
import frNavbar from './locales/fr/navbar.json';
import frHome from './locales/fr/home.json';
import frProduct from './locales/fr/product.json';
import frAuth from './locales/fr/auth.json';
import frCart from './locales/fr/cart.json';
import frFavorites from './locales/fr/favorites.json';
import frOrders from './locales/fr/orders.json';
import frProfile from './locales/fr/profile.json';
import frProductDetail from './locales/fr/productDetail.json';
import frBrands from './locales/fr/brands.json';
import frPromotions from './locales/fr/promotions.json';
import frChatbot from './locales/fr/chatbot.json';

// Each page/area owns its own namespace file (frontend/src/i18n/locales/<lng>/<ns>.json)
// so translating one page never touches another page's JSON.
const resources = {
  en: {
    common: enCommon,
    navbar: enNavbar,
    home: enHome,
    product: enProduct,
    auth: enAuth,
    cart: enCart,
    favorites: enFavorites,
    orders: enOrders,
    profile: enProfile,
    productDetail: enProductDetail,
    brands: enBrands,
    promotions: enPromotions,
    chatbot: enChatbot,
  },
  fr: {
    common: frCommon,
    navbar: frNavbar,
    home: frHome,
    product: frProduct,
    auth: frAuth,
    cart: frCart,
    favorites: frFavorites,
    orders: frOrders,
    profile: frProfile,
    productDetail: frProductDetail,
    brands: frBrands,
    promotions: frPromotions,
    chatbot: frChatbot,
  },
};

i18n
  .use(LanguageDetector)
  .use(initReactI18next)
  .init({
    resources,
    fallbackLng: 'en',
    supportedLngs: ['en', 'fr'],
    ns: Object.keys(resources.en),
    defaultNS: 'common',
    interpolation: { escapeValue: false },
    detection: {
      // Remembers the user's explicit choice (localStorage) on repeat visits;
      // otherwise falls back to their browser language.
      order: ['localStorage', 'navigator'],
      lookupLocalStorage: 'language',
      caches: ['localStorage'],
    },
  });

export default i18n;
