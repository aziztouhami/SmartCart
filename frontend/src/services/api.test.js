import api from './api';
import { isAuthenticated, getToken, logout } from './authService';
import { getSessionId } from './sessionService';

jest.mock('./authService', () => ({
  isAuthenticated: jest.fn(),
  getToken: jest.fn(),
  logout: jest.fn(),
}));

jest.mock('./sessionService', () => ({
  getSessionId: jest.fn(),
}));

// axios stores registered interceptors on interceptors.request/response.handlers[i]
// as { fulfilled, rejected }. Since api.js installs exactly one of each at module
// load time, we can invoke them directly instead of mocking axios.create.
const requestInterceptor = () => api.interceptors.request.handlers[0];
const responseInterceptor = () => api.interceptors.response.handlers[0];

describe('api instance configuration', () => {
  it('uses the configured base URL', () => {
    expect(api.defaults.baseURL).toBe(process.env.REACT_APP_API_URL);
  });

  it('uses the configured request timeout', () => {
    expect(api.defaults.timeout).toBe(Number(process.env.REACT_APP_REQUEST_TIMEOUT));
  });

  it('defaults Content-Type to application/json', () => {
    expect(api.defaults.headers['Content-Type']).toBe('application/json');
  });
});

describe('api request interceptor', () => {
  beforeEach(() => {
    getSessionId.mockReturnValue('sess-123');
  });

  it('attaches a Bearer Authorization header when authenticated', () => {
    isAuthenticated.mockReturnValue(true);
    getToken.mockReturnValue('tok-abc');

    const config = requestInterceptor().fulfilled({ headers: {} });

    expect(config.headers.Authorization).toBe('Bearer tok-abc');
  });

  it('does not attach an Authorization header when not authenticated', () => {
    isAuthenticated.mockReturnValue(false);

    const config = requestInterceptor().fulfilled({ headers: {} });

    expect(config.headers.Authorization).toBeUndefined();
  });

  it('attaches X-Session-Id on every request, authenticated or not', () => {
    isAuthenticated.mockReturnValue(false);

    const config = requestInterceptor().fulfilled({ headers: {} });

    expect(config.headers['X-Session-Id']).toBe('sess-123');
  });

  it('still attaches X-Session-Id when authenticated', () => {
    isAuthenticated.mockReturnValue(true);
    getToken.mockReturnValue('tok-abc');

    const config = requestInterceptor().fulfilled({ headers: {} });

    expect(config.headers['X-Session-Id']).toBe('sess-123');
  });
});

describe('api response interceptor', () => {
  it('passes successful responses through unchanged', () => {
    const response = { status: 200, data: { ok: true } };

    expect(responseInterceptor().fulfilled(response)).toBe(response);
  });

  it('logs out and rejects on a 401 response', async () => {
    const error = { response: { status: 401 } };

    await expect(responseInterceptor().rejected(error)).rejects.toBe(error);
    expect(logout).toHaveBeenCalledTimes(1);
  });

  it('does not log out on a non-401 error response', async () => {
    const error = { response: { status: 500 } };

    await expect(responseInterceptor().rejected(error)).rejects.toBe(error);
    expect(logout).not.toHaveBeenCalled();
  });

  it('does not log out on a network error with no response', async () => {
    const error = { message: 'Network Error' };

    await expect(responseInterceptor().rejected(error)).rejects.toBe(error);
    expect(logout).not.toHaveBeenCalled();
  });
});
