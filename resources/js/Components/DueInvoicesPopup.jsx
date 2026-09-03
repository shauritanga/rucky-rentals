import { useCallback, useEffect, useRef, useState } from 'react';
import { Link, usePage } from '@inertiajs/react';

const ALLOWED_ROLES = ['superuser', 'manager', 'accountant'];

function playChime() {
  try {
    const Ctx = window.AudioContext || window.webkitAudioContext;
    if (!Ctx) return;
    const ctx = new Ctx();
    const now = ctx.currentTime;
    [
      { freq: 880, start: 0, dur: 0.15 },
      { freq: 1318.5, start: 0.13, dur: 0.22 },
    ].forEach(({ freq, start, dur }) => {
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.type = 'sine';
      osc.frequency.value = freq;
      gain.gain.setValueAtTime(0, now + start);
      gain.gain.linearRampToValueAtTime(0.12, now + start + 0.02);
      gain.gain.exponentialRampToValueAtTime(0.0001, now + start + dur);
      osc.connect(gain).connect(ctx.destination);
      osc.start(now + start);
      osc.stop(now + start + dur + 0.02);
    });
    setTimeout(() => ctx.close?.(), 600);
  } catch {
    // AudioContext blocked/unsupported — fail silently, never crash the popup.
  }
}

export default function DueInvoicesPopup({ user }) {
  const { props } = usePage();
  const justLoggedIn = !!props?.just_logged_in;

  const [invoices, setInvoices] = useState([]);
  const [visible, setVisible] = useState(false);
  const playedRef = useRef(false);
  const fetchedRef = useRef(false);

  const fetchDue = useCallback(async () => {
    try {
      const res = await fetch('/invoices/due-reminders', { headers: { Accept: 'application/json' } });
      if (!res.ok) return;
      const json = await res.json();
      const list = json.due_invoices ?? [];
      if (list.length > 0) {
        setInvoices(list);
        setVisible(true);
        if (!playedRef.current) {
          playChime();
          playedRef.current = true;
        }
      }
    } catch {
      // Network/parse failure — skip silently, try again next login.
    }
  }, []);

  useEffect(() => {
    if (!user?.role || !ALLOWED_ROLES.includes(user.role)) return;
    if (!justLoggedIn || fetchedRef.current) return;
    fetchedRef.current = true;
    fetchDue();
  }, [user?.role, justLoggedIn, fetchDue]);

  if (!visible || invoices.length === 0) return null;

  return (
    <div className="due-invoices-popup">
      <div className="due-invoices-popup-header">
        <span>Invoices Due</span>
        <button className="due-invoices-popup-close" onClick={() => setVisible(false)} aria-label="Close">✕</button>
      </div>
      <div className="due-invoices-popup-list">
        {invoices.map((inv) => (
          <div key={inv.id} className="due-invoices-popup-item">
            <div className="due-invoices-popup-item-main">
              <span className="due-invoices-popup-tenant">{inv.tenant_name || 'Tenant'}</span>
              {inv.unit_ref && <span className="due-invoices-popup-unit">{inv.unit_ref}</span>}
            </div>
            <div className="due-invoices-popup-item-meta">
              <span className="due-invoices-popup-amount">{inv.currency} {Number(inv.amount_due).toLocaleString()}</span>
              <span className={`due-invoices-popup-status ${inv.is_overdue ? 'overdue' : 'soon'}`}>
                {inv.is_overdue ? `Overdue by ${inv.days}d` : `Due in ${inv.days}d`}
              </span>
            </div>
          </div>
        ))}
      </div>
      {user?.role !== 'superuser' && (
        <Link href="/invoices" className="due-invoices-popup-footer">View All Invoices</Link>
      )}
    </div>
  );
}
