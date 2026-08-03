import axios from 'axios';
import { isAuthenticated, getToken, logout } from './authService';
import { getSessionId } from './sessionService';

const api = axios.create({
  baseURL: process.env.REACT_APP_API_URL,
  timeout: Number(process.env.REACT_APP_REQUEST_TIMEOUT) || 30000,
  headers: { 'Content-Type': 'application/json' },
});

// Only attach the JWT token when it is present AND not expired
api.interceptors.request.use(config => {
  if (isAuthenticated()) {
    config.headers.Authorization = `Bearer ${getToken()}`;
  }
  // Sent on every request (cheap, harmless when authenticated) so guest
  // browsing — and the recommendations it should drive — survives across
  // page loads without needing an account.
  config.headers['X-Session-Id'] = getSessionId();
  return config;
});

// On 401, clear stale auth state so public routes stop getting blocked
api.interceptors.response.use(
  response => response,
  error => {
    if (error.response?.status === 401) {
      logout();
    }
    return Promise.reject(error);
  },
);

export default api;
