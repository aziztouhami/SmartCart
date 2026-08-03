import { useState } from 'react';

/** Shared "Analyze" state machine for the admin Products/Categories/Brands/
 * Types pages — opens the report modal immediately and tracks its own
 * loading/result/error, so each page only needs to call runAnalysis(name,
 * apiCall) from its AnalyzeButton and render <AnomalyReportModal {...analysis} />. */
export default function useAnalysis() {
  const [analysis, setAnalysis] = useState(null); // { entityName, loading, result, error } | null

  const runAnalysis = async (entityName, apiCall) => {
    setAnalysis({ entityName, loading: true, result: null, error: null });
    try {
      const res = await apiCall();
      setAnalysis({ entityName, loading: false, result: res.data, error: null });
    } catch (err) {
      const message = err.response?.data?.error || 'Analysis failed. Please try again.';
      setAnalysis({ entityName, loading: false, result: null, error: message });
    }
  };

  const closeAnalysis = () => setAnalysis(null);

  return { analysis, runAnalysis, closeAnalysis };
}
