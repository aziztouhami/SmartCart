import api from './api';

const TOKEN_KEY = process.env.REACT_APP_JWT_STORAGE_KEY;
const EXPIRATION_KEY = process.env.REACT_APP_JWT_EXPIRATION_KEY;
const USER_KEY = 'smartcart_user';
const CART_KEY = 'smartcart_cart';

function saveSession(token, expiresIn, user) {
  localStorage.setItem(TOKEN_KEY, token);
  localStorage.setItem(EXPIRATION_KEY, Date.now() + expiresIn * 1000);
  localStorage.setItem(USER_KEY, JSON.stringify(user));
}

export async function login(email, password) {
  const { data } = await api.post('/auth/login', { email, password });
  saveSession(data.token, data.expiresIn, data.user);
  return data;
}

export async function googleLogin(accessToken) {
  const { data } = await api.post('/auth/google-login', { accessToken });
  saveSession(data.token, data.expiresIn, data.user);
  return data;
}

export async function register(
  firstName,
  lastName,
  email,
  password,
  marketingOptIn = false,
  preferredCategoryIds = [],
  preferredBrandIds = [],
) {
  const { data } = await api.post('/auth/register', {
    firstName,
    lastName,
    email,
    password,
    marketingOptIn,
    preferredCategoryIds,
    preferredBrandIds,
  });
  return data;
}

export async function verifyEmail(token) {
  const { data } = await api.post('/auth/verify-email', { token });
  return data;
}

export async function resendVerification(email) {
  const { data } = await api.post('/auth/resend-verification', { email });
  return data;
}

export function logout() {
  localStorage.removeItem(TOKEN_KEY);
  localStorage.removeItem(EXPIRATION_KEY);
  localStorage.removeItem(USER_KEY);
  // The locally-stored cart mirrors the backend cart of the now-logged-out
  // account; clearing it prevents it from being re-merged (and doubled)
  // into the backend cart on the next login.
  localStorage.removeItem(CART_KEY);
}

export function getToken() {
  return localStorage.getItem(TOKEN_KEY);
}

export function getUser() {
  const raw = localStorage.getItem(USER_KEY);
  try {
    return raw ? JSON.parse(raw) : null;
  } catch {
    return null;
  }
}

export function isAuthenticated() {
  const exp = localStorage.getItem(EXPIRATION_KEY);
  return !!getToken() && !!exp && Date.now() < parseInt(exp, 10);
}

export function updateLocalUser(updates) {
  const user = getUser();
  if (user) localStorage.setItem(USER_KEY, JSON.stringify({ ...user, ...updates }));
}

export function isAdmin() {
  return getUser()?.roles?.includes('ROLE_ADMIN') ?? false;
}
