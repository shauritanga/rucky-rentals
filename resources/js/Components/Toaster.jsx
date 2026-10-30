import { useCallback, useEffect, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';

const DURATION = { success: 4000, info: 4000, warning: 6000, error: 7000 };
const TITLES = { success: 'Success', info: 'Notice', warning: 'Warning', error: 'Something went wrong' };
const listeners = new Set();

/** Show a toast from anywhere: notify('success', 'Saved.') */
export function notify(type, message) {
  if (!message) return;
  listeners.forEach((fn) => fn(type, String(message)));
}

function Icon({ type }) {
  const common = { width: 16, height: 16, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2.4, strokeLinecap: 'round', strokeLinejoin: 'round' };
  if (type === 'success') return <svg {...common}><polyline points="20 6 9 17 4 12" /></svg>;
  if (type === 'warning') return <svg {...common}><path d="M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z" /><line x1="12" y1="9" x2="12" y2="13" /><line x1="12" y1="17" x2="12.01" y2="17" /></svg>;
  if (type === 'error') return <svg {...common}><circle cx="12" cy="12" r="9" /><line x1="15" y1="9" x2="9" y2="15" /><line x1="9" y1="9" x2="15" y2="15" /></svg>;
  return <svg {...common}><circle cx="12" cy="12" r="9" /><line x1="12" y1="16" x2="12" y2="12" /><line x1="12" y1="8" x2="12.01" y2="8" /></svg>;
}

export default function Toaster() {
  const { props } = usePage();
  const flash = props?.flash;
  const [toasts, setToasts] = useState([]);
  const nextId = useRef(1);

  const remove = useCallback((id) => setToasts((list) => list.filter((t) => t.id !== id)), []);

  const add = useCallback((type, message) => {
    const id = nextId.current++;
    setToasts((list) => [...list.slice(-3), { id, type, message }]);
    window.setTimeout(() => remove(id), DURATION[type] ?? 4000);
  }, [remove]);

  useEffect(() => {
    listeners.add(add);
    return () => listeners.delete(add);
  }, [add]);

  // Server flash messages: one toast per visit that carries them.
  useEffect(() => {
    if (!flash) return;
    if (flash.success) add('success', flash.success);
    if (flash.warning) add('warning', flash.warning);
    if (flash.error) add('error', flash.error);
  }, [flash]); // eslint-disable-line react-hooks/exhaustive-deps

  if (toasts.length === 0) return null;

  return (
    <div className="toaster" role="region" aria-label="Notifications">
      {toasts.map((t) => (
        <div key={t.id} className={`toast-card ${t.type}`} role={t.type === 'error' ? 'alert' : 'status'} style={{ '--toast-ms': `${DURATION[t.type] ?? 4000}ms` }}>
          <div className="toast-card-icon"><Icon type={t.type} /></div>
          <div className="toast-card-body">
            <div className="toast-card-title">{TITLES[t.type] ?? TITLES.info}</div>
            <div className="toast-card-msg">{t.message}</div>
          </div>
          <button type="button" className="toast-card-close" onClick={() => remove(t.id)} aria-label="Dismiss">✕</button>
          <span className="toast-card-bar" />
        </div>
      ))}
    </div>
  );
}
