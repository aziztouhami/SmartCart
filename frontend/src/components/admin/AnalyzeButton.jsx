import React from 'react';
import { IconAnalyze } from './AdminIcons';

/** Per-row "Analyze" action, shared by the admin Products/Categories/Brands/
 * Types tables. Purely presentational — the parent page owns the modal state
 * and kicks off the API call in onClick (see AnomalyReportModal). */
export default function AnalyzeButton({ onClick }) {
  return (
    <button className="adm-btn-icon adm-btn-analyze" onClick={onClick}>
      <IconAnalyze /> Analyze
    </button>
  );
}
