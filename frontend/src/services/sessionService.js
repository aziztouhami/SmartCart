const SESSION_KEY = 'smartcart_session_id';

function generateId() {
  if (window.crypto?.randomUUID) return window.crypto.randomUUID();
  // Fallback for browsers without crypto.randomUUID
  return 'sess-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 12);
}

/** A stable, anonymous id for this browser, used to attribute guest
 * views/cart-adds to "one visitor" without requiring an account. Persists
 * across page loads (survives refresh, new tabs) via localStorage; only a
 * fresh browser/profile gets a new one. */
export function getSessionId() {
  let id = localStorage.getItem(SESSION_KEY);
  if (!id) {
    id = generateId();
    localStorage.setItem(SESSION_KEY, id);
  }
  return id;
}
