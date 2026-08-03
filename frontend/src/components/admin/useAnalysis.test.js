import { renderHook, act } from '@testing-library/react';
import useAnalysis from './useAnalysis';

describe('useAnalysis', () => {
  it('starts with no analysis', () => {
    const { result } = renderHook(() => useAnalysis());
    expect(result.current.analysis).toBeNull();
  });

  it('sets a loading state immediately, then the result on success', async () => {
    const { result } = renderHook(() => useAnalysis());
    let resolveCall;
    const apiCall = () => new Promise(resolve => (resolveCall = resolve));

    act(() => {
      result.current.runAnalysis('Test Product', apiCall);
    });

    expect(result.current.analysis).toEqual({
      entityName: 'Test Product',
      loading: true,
      result: null,
      error: null,
    });

    await act(async () => {
      resolveCall({ data: { healthScore: 80, summary: 'ok', anomalies: [] } });
    });

    expect(result.current.analysis).toEqual({
      entityName: 'Test Product',
      loading: false,
      result: { healthScore: 80, summary: 'ok', anomalies: [] },
      error: null,
    });
  });

  it('sets the server error message on failure', async () => {
    const { result } = renderHook(() => useAnalysis());
    const apiCall = () =>
      Promise.reject({ response: { data: { error: 'AI analytics unavailable' } } });

    await act(async () => {
      await result.current.runAnalysis('Test Brand', apiCall);
    });

    expect(result.current.analysis).toEqual({
      entityName: 'Test Brand',
      loading: false,
      result: null,
      error: 'AI analytics unavailable',
    });
  });

  it('falls back to a generic error message when the server gives none', async () => {
    const { result } = renderHook(() => useAnalysis());
    const apiCall = () => Promise.reject(new Error('network down'));

    await act(async () => {
      await result.current.runAnalysis('Test Category', apiCall);
    });

    expect(result.current.analysis.error).toBe('Analysis failed. Please try again.');
  });

  it('closeAnalysis resets to null', async () => {
    const { result } = renderHook(() => useAnalysis());
    const apiCall = () =>
      Promise.resolve({ data: { healthScore: 50, summary: '', anomalies: [] } });

    await act(async () => {
      await result.current.runAnalysis('Test Type', apiCall);
    });
    expect(result.current.analysis).not.toBeNull();

    act(() => {
      result.current.closeAnalysis();
    });
    expect(result.current.analysis).toBeNull();
  });
});
