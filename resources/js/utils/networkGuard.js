import { router } from '@inertiajs/react';
import { notify } from '@/Components/Toaster';

/**
 * Keeps saves safe when the connection is bad.
 *
 *  - Every save (anything but a page load) carries an X-Idempotency-Key. If the connection drops and the outcome is
 *    unclear, pressing the button again sends the SAME key, so the server returns the first result instead of
 *    creating the record twice (see App\Http\Middleware\EnsureIdempotent).
 *  - A lost connection shows one clear message instead of a spinner that silently stops.
 *  - The page is marked offline (html[data-offline]) so save buttons are disabled until the connection returns.
 */
const RETRY_WINDOW_MS = 15 * 60 * 1000;
const HEADER = 'X-Idempotency-Key';

const uncertain = new Map(); // request signature -> { key, at }  (attempts whose result we never saw)

const newKey = () => (window.crypto?.randomUUID ? window.crypto.randomUUID() : `${Date.now()}-${Math.random().toString(36).slice(2)}`);

const stable = (value) => {
  if (value instanceof FormData) {
    return JSON.stringify([...value.entries()].map(([k, v]) => [k, v instanceof File ? `${v.name}:${v.size}` : String(v)]));
  }
  try {
    return JSON.stringify(value, (_k, v) => (v && typeof v === 'object' && !Array.isArray(v)
      ? Object.fromEntries(Object.entries(v).sort(([a], [b]) => a.localeCompare(b)))
      : v));
  } catch {
    return String(Math.random());
  }
};

const signatureOf = (visit) => {
  let path = '';
  try { path = new URL(visit.url, window.location.origin).pathname; } catch { path = String(visit.url); }
  return `${String(visit.method).toLowerCase()}|${path}|${stable(visit.data)}`;
};

export function installNetworkGuard() {
  if (typeof window === 'undefined' || window.__networkGuardInstalled) return;
  window.__networkGuardInstalled = true;

  let current = null;

  router.on('before', (event) => {
    const visit = event.detail.visit;
    current = null;
    if (!visit || String(visit.method).toLowerCase() === 'get') return;

    const signature = signatureOf(visit);
    const known = uncertain.get(signature);
    const key = known && Date.now() - known.at < RETRY_WINDOW_MS ? known.key : newKey();

    visit.headers = { ...(visit.headers || {}), [HEADER]: key };
    current = { signature, key, settled: false };
  });

  // The server answered (saved, or refused with a message): the outcome is known, so the next press is a new save.
  const settle = () => { if (current) current.settled = true; };
  router.on('success', settle);
  router.on('error', settle);

  router.on('exception', () => {
    if (current) {
      notify('error', 'Connection lost. Your changes were not confirmed. Check the list before trying again; pressing the button again is safe.');
    } else {
      notify('error', 'Connection lost. The page could not be loaded. Check your internet and try again.');
    }
  });

  router.on('finish', () => {
    if (!current) return;
    if (current.settled) {
      uncertain.delete(current.signature);
    } else {
      uncertain.set(current.signature, { key: current.key, at: Date.now() });
    }
    current = null;
  });

  // Offline marker for the CSS that disables save buttons, plus a notice when the connection returns.
  const mark = (offline) => { document.documentElement.dataset.offline = offline ? 'true' : 'false'; };
  mark(!navigator.onLine);
  window.addEventListener('offline', () => mark(true));
  window.addEventListener('online', () => {
    mark(false);
    notify('success', 'Back online. You can save again.');
  });
}
