import React from 'react';

const SEVERITY_LABEL = { low: 'Low', medium: 'Medium', high: 'High' };

function healthScoreTone(score) {
  if (score >= 70) return 'good';
  if (score >= 40) return 'warn';
  return 'bad';
}

/** Shared result panel for the "Analyze" button on Products/Categories/
 * Brands/Types. Opens immediately on click and shows its own loading/error/
 * result states — the parent just tracks { entityName, loading, result,
 * error } and passes it straight through. */
export default function AnomalyReportModal({ entityName, loading, result, error, onClose }) {
  return (
    <div className="adm-overlay" onClick={onClose}>
      <div className="adm-modal arm-modal" onClick={e => e.stopPropagation()}>
        <div className="adm-modal-head">
          <h2>AI Analysis — {entityName}</h2>
          <button className="adm-modal-close" onClick={onClose}>
            ✕
          </button>
        </div>

        <div className="adm-modal-body">
          {loading && (
            <div className="arm-loading">
              <div className="arm-spinner" />
              <p>Analyzing with the local AI model — this can take a while on CPU.</p>
            </div>
          )}

          {!loading && error && <div className="arm-error">{error}</div>}

          {!loading && !error && result && (
            <>
              <div className={`arm-health arm-health--${healthScoreTone(result.healthScore)}`}>
                <span className="arm-health-score">{result.healthScore}</span>
                <span className="arm-health-label">/ 100</span>
              </div>

              {result.summary && <p className="arm-summary">{result.summary}</p>}

              {result.anomalies.length === 0 ? (
                <p className="arm-none">No anomalies detected.</p>
              ) : (
                <div className="arm-anomalies">
                  {result.anomalies.map((a, i) => (
                    <div key={i} className={`arm-anomaly arm-anomaly--${a.severity}`}>
                      <div className="arm-anomaly-head">
                        <span className="arm-anomaly-metric">{a.metric}</span>
                        <span className={`arm-severity arm-severity--${a.severity}`}>
                          {SEVERITY_LABEL[a.severity] || a.severity}
                        </span>
                      </div>
                      {a.finding && <p className="arm-anomaly-finding">{a.finding}</p>}
                      {a.recommendation && <p className="arm-anomaly-rec">→ {a.recommendation}</p>}
                    </div>
                  ))}
                </div>
              )}
            </>
          )}
        </div>

        <div className="adm-modal-foot">
          <button className="adm-btn-cancel" onClick={onClose}>
            Close
          </button>
        </div>
      </div>
    </div>
  );
}
