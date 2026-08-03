import api from './api';
import {
  login,
  logout,
  getToken,
  getUser,
  isAuthenticated,
  updateLocalUser,
  isAdmin,
} from './authService';

jest.mock('./api', () => ({
  __esModule: true,
  default: { post: jest.fn() },
}));

const TOKEN_KEY = process.env.REACT_APP_JWT_STORAGE_KEY;
const EXPIRATION_KEY = process.env.REACT_APP_JWT_EXPIRATION_KEY;

describe('authService', () => {
  beforeEach(() => {
    localStorage.clear();
    jest.clearAllMocks();
  });

  describe('login', () => {
    it('posts credentials and persists the session on success', async () => {
      const user = { id: 1, firstName: 'Ada', roles: ['ROLE_USER'] };
      api.post.mockResolvedValueOnce({ data: { token: 'jwt-123', expiresIn: 3600, user } });

      const result = await login('ada@example.com', 'secret');

      expect(api.post).toHaveBeenCalledWith('/auth/login', {
        email: 'ada@example.com',
        password: 'secret',
      });
      expect(result.token).toBe('jwt-123');
      expect(getToken()).toBe('jwt-123');
      expect(getUser()).toEqual(user);
    });

    it('propagates the error and stores nothing on failure', async () => {
      api.post.mockRejectedValueOnce({ response: { data: { error: 'Invalid credentials' } } });

      await expect(login('ada@example.com', 'wrong')).rejects.toBeTruthy();
      expect(getToken()).toBeNull();
    });
  });

  describe('isAuthenticated', () => {
    it('is false with no stored session', () => {
      expect(isAuthenticated()).toBe(false);
    });

    it('is true when the token exists and has not expired', () => {
      localStorage.setItem(TOKEN_KEY, 'jwt-123');
      localStorage.setItem(EXPIRATION_KEY, String(Date.now() + 60_000));
      expect(isAuthenticated()).toBe(true);
    });

    it('is false when the token has expired', () => {
      localStorage.setItem(TOKEN_KEY, 'jwt-123');
      localStorage.setItem(EXPIRATION_KEY, String(Date.now() - 1));
      expect(isAuthenticated()).toBe(false);
    });
  });

  describe('logout', () => {
    it('clears the session, the cached user, and the local cart mirror', () => {
      localStorage.setItem(TOKEN_KEY, 'jwt-123');
      localStorage.setItem(EXPIRATION_KEY, String(Date.now() + 60_000));
      localStorage.setItem('smartcart_user', JSON.stringify({ id: 1 }));
      localStorage.setItem('smartcart_cart', JSON.stringify([{ id: 1, qty: 2 }]));

      logout();

      expect(localStorage.getItem(TOKEN_KEY)).toBeNull();
      expect(localStorage.getItem(EXPIRATION_KEY)).toBeNull();
      expect(localStorage.getItem('smartcart_user')).toBeNull();
      expect(localStorage.getItem('smartcart_cart')).toBeNull();
    });
  });

  describe('getUser', () => {
    it('returns null when nothing is stored', () => {
      expect(getUser()).toBeNull();
    });

    it('returns null instead of throwing on corrupted JSON', () => {
      localStorage.setItem('smartcart_user', '{not-json');
      expect(getUser()).toBeNull();
    });
  });

  describe('updateLocalUser', () => {
    it('merges updates into the cached user', () => {
      localStorage.setItem(
        'smartcart_user',
        JSON.stringify({ id: 1, firstName: 'Ada', phone: null }),
      );
      updateLocalUser({ phone: '20123456' });
      expect(getUser()).toEqual({ id: 1, firstName: 'Ada', phone: '20123456' });
    });

    it('does nothing when there is no cached user', () => {
      updateLocalUser({ phone: '20123456' });
      expect(getUser()).toBeNull();
    });
  });

  describe('isAdmin', () => {
    it('is true when the cached user has ROLE_ADMIN', () => {
      localStorage.setItem(
        'smartcart_user',
        JSON.stringify({ roles: ['ROLE_USER', 'ROLE_ADMIN'] }),
      );
      expect(isAdmin()).toBe(true);
    });

    it('is false for a plain user, and false with no user at all', () => {
      localStorage.setItem('smartcart_user', JSON.stringify({ roles: ['ROLE_USER'] }));
      expect(isAdmin()).toBe(false);
      localStorage.clear();
      expect(isAdmin()).toBe(false);
    });
  });
});
