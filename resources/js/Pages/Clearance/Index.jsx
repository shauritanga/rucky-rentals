import { useEffect, useMemo, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { formatDisplayDate } from '@/utils/dateFormat';

const CURRENCY_FALLBACK = 'TZS';

function resolveCurrency(currency) {
  const normalized = String(currency || '').toUpperCase();
  return ['USD', 'TZS'].includes(normalized) ? normalized : CURRENCY_FALLBACK;
}

function formatMoney(amount, currency = CURRENCY_FALLBACK) {
  if (amount == null || Number.isNaN(Number(amount))) return '—';
  const code = resolveCurrency(currency);
  const noDecimals = code === 'TZS';
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: code,
    minimumFractionDigits: noDecimals ? 0 : 2,
    maximumFractionDigits: noDecimals ? 0 : 2,
  }).format(Number(amount));
}

const STATUS_META = {
  scheduled:   { label: 'Scheduled',   bg: 'var(--bg-elevated)', color: 'var(--text-secondary)' },
  in_progress: { label: 'In Progress', bg: 'var(--accent-dim)',  color: 'var(--accent)'          },
  completed:   { label: 'Completed',   bg: 'var(--green-dim)',   color: 'var(--green)'           },
  cancelled:   { label: 'Cancelled',   bg: 'var(--red-dim)',     color: 'var(--red)'              },
};

const FILTERS = [
  ['all', 'All'],
  ['scheduled', 'Scheduled'],
  ['in_progress', 'In Progress'],
  ['completed', 'Completed'],
  ['cancelled', 'Cancelled'],
];

const CHECKLIST_CATEGORIES = [
  'Living Room / Office Area',
  'Bedroom(s)',
  'Kitchen',
  'Bathroom(s)',
  'Doors & Windows',
  'Walls & Ceiling',
  'Flooring',
  'Electrical',
  'Plumbing',
  'Appliances',
  'General Cleanliness',
];

const CONDITION_META = {
  good:    { label: 'Good',    color: 'var(--green)' },
  fair:    { label: 'Fair',    color: 'var(--amber)' },
  damaged: { label: 'Damaged', color: 'var(--red)'   },
};

const DAMAGE_CATEGORIES = ['Walls', 'Flooring', 'Doors & Windows', 'Plumbing', 'Electrical', 'Kitchen', 'Bathroom', 'Appliances', 'Cleaning', 'Other'];

const WIZARD_STEPS = [
  { key: 1, label: 'Summary' },
  { key: 2, label: 'Inspection' },
  { key: 3, label: 'Damage & Cost' },
  { key: 4, label: 'Review' },
];

const EMPTY_ITEM_DRAFT = { category: DAMAGE_CATEGORIES[0], room: '', description: '', cost: '', responsible_party: 'tenant' };

export default function ClearanceIndex({ clearances = [], eligibleLeases = [], canManage = false }) {
  const { props } = usePage();
  const user = props?.auth?.user;

  const [filter, setFilter] = useState('all');
  const [search, setSearch] = useState('');
  const [selected, setSelected] = useState(null);
  const [submitMessage, setSubmitMessage] = useState('');

  const [showWizard, setShowWizard] = useState(false);
  const [wizardStep, setWizardStep] = useState(1);
  const [wizardLeaseId, setWizardLeaseId] = useState('');
  const [wizardScheduledDate, setWizardScheduledDate] = useState(() => new Date().toISOString().slice(0, 10));
  const [wizardClearanceId, setWizardClearanceId] = useState(null);
  const [awaitingLeaseId, setAwaitingLeaseId] = useState(null);
  const [creating, setCreating] = useState(false);

  const [checklist, setChecklist] = useState({});
  const [checklistSaving, setChecklistSaving] = useState(false);

  const [itemDraft, setItemDraft] = useState(EMPTY_ITEM_DRAFT);
  const [itemImages, setItemImages] = useState([]);
  const [addingItem, setAddingItem] = useState(false);

  const [confirmChecked, setConfirmChecked] = useState(false);
  const [finalizing, setFinalizing] = useState(false);

  const [showCancelConfirm, setShowCancelConfirm] = useState(false);
  const [cancelReason, setCancelReason] = useState('');

  useEffect(() => {
    if (!submitMessage) return;
    const t = setTimeout(() => setSubmitMessage(''), 4000);
    return () => clearTimeout(t);
  }, [submitMessage]);

  // Open the wizard directly for a lease when arriving via "Start Clearance" from the Leases page.
  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const leaseId = params.get('lease_id');
    if (leaseId) {
      setWizardLeaseId(leaseId);
      setShowWizard(true);
      window.history.replaceState({}, '', window.location.pathname);
    }
  }, []);

  const wizardClearance = useMemo(
    () => clearances.find((c) => c.id === wizardClearanceId) || null,
    [clearances, wizardClearanceId],
  );

  // Once the record we just created shows up in the reloaded props, jump to step 2.
  useEffect(() => {
    if (!awaitingLeaseId) return;
    const created = clearances
      .filter((c) => c.lease_id === awaitingLeaseId && c.status === 'scheduled')
      .sort((a, b) => b.id - a.id)[0];
    if (created) {
      setWizardClearanceId(created.id);
      setAwaitingLeaseId(null);
      setCreating(false);
      setWizardStep(2);
    }
  }, [clearances, awaitingLeaseId]);

  // Hydrate the checklist editor once when a wizard record is loaded/created.
  useEffect(() => {
    if (!wizardClearance) return;
    const map = {};
    (wizardClearance.inspection_checklist || []).forEach((row) => {
      map[row.category] = { condition: row.condition, note: row.note || '' };
    });
    setChecklist(map);
  }, [wizardClearance?.id]);

  const counts = useMemo(() => {
    const next = { all: clearances.length, scheduled: 0, in_progress: 0, completed: 0, cancelled: 0 };
    clearances.forEach((c) => { if (next[c.status] !== undefined) next[c.status] += 1; });
    return next;
  }, [clearances]);

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    return clearances.filter((c) => {
      const matchFilter = filter === 'all' || c.status === filter;
      const haystack = [c.clearance_number, c.tenant?.name, c.unit?.unit_number, c.property?.name].filter(Boolean).join(' ').toLowerCase();
      const matchSearch = !q || haystack.includes(q);
      return matchFilter && matchSearch;
    });
  }, [clearances, filter, search]);

  const stats = useMemo(() => {
    const completed = clearances.filter((c) => c.status === 'completed');
    const now = new Date();
    const thisMonth = completed.filter((c) => {
      if (!c.finalized_at) return false;
      const d = new Date(c.finalized_at);
      return d.getMonth() === now.getMonth() && d.getFullYear() === now.getFullYear();
    }).length;
    const totalRefunded = completed.reduce((s, c) => s + Number(c.refund_amount || 0), 0);
    const totalRecovered = completed.reduce((s, c) => s + Number(c.total_deductions || 0), 0);
    return { thisMonth, totalRefunded, totalRecovered };
  }, [clearances]);

  const closeWizard = () => {
    setShowWizard(false);
    setWizardStep(1);
    setWizardLeaseId('');
    setWizardClearanceId(null);
    setAwaitingLeaseId(null);
    setChecklist({});
    setItemDraft(EMPTY_ITEM_DRAFT);
    setItemImages([]);
    setConfirmChecked(false);
  };

  const openNewWizard = () => {
    setWizardStep(1);
    setWizardLeaseId('');
    setWizardClearanceId(null);
    setShowWizard(true);
  };

  const openContinueWizard = (clearance) => {
    setWizardClearanceId(clearance.id);
    setWizardLeaseId(String(clearance.lease_id));
    setWizardStep(clearance.status === 'scheduled' ? 2 : 3);
    setSelected(null);
    setShowWizard(true);
  };

  const startClearance = () => {
    if (!wizardLeaseId) return;
    setCreating(true);
    router.post('/clearance', { lease_id: wizardLeaseId, scheduled_date: wizardScheduledDate }, {
      onSuccess: () => setAwaitingLeaseId(Number(wizardLeaseId)),
      onError: () => setCreating(false),
    });
  };

  const saveChecklist = () => {
    if (!wizardClearance) return;
    setChecklistSaving(true);
    const rows = CHECKLIST_CATEGORIES.filter((cat) => checklist[cat]?.condition).map((cat) => ({
      category: cat,
      condition: checklist[cat].condition,
      note: checklist[cat].note || '',
    }));
    router.patch(`/clearance/${wizardClearance.id}`, { inspection_checklist: rows }, {
      onSuccess: () => { setChecklistSaving(false); setSubmitMessage('Inspection saved.'); setWizardStep(3); },
      onError: () => setChecklistSaving(false),
    });
  };

  const setChecklistRow = (category, field, value) => {
    setChecklist((prev) => ({ ...prev, [category]: { ...prev[category], [field]: value } }));
  };

  const handleItemImages = (files) => {
    const list = Array.from(files || []).filter((f) => String(f.type || '').startsWith('image/'));
    if (!list.length) return;
    setItemImages((prev) => [...prev, ...list.map((file) => ({ file, preview: URL.createObjectURL(file) }))]);
  };

  const addItem = () => {
    if (!wizardClearance || !itemDraft.description.trim() || itemDraft.cost === '') return;
    setAddingItem(true);
    const formData = new FormData();
    formData.append('category', itemDraft.category);
    formData.append('room', itemDraft.room || '');
    formData.append('description', itemDraft.description);
    formData.append('cost', itemDraft.cost);
    formData.append('responsible_party', itemDraft.responsible_party);
    itemImages.forEach((img) => formData.append('images[]', img.file));

    router.post(`/clearance/${wizardClearance.id}/items`, formData, {
      forceFormData: true,
      onSuccess: () => {
        setItemDraft(EMPTY_ITEM_DRAFT);
        setItemImages([]);
        setAddingItem(false);
        setSubmitMessage('Damage item added.');
      },
      onError: () => setAddingItem(false),
    });
  };

  const removeItem = (itemId) => {
    if (!wizardClearance) return;
    router.delete(`/clearance/${wizardClearance.id}/items/${itemId}`, {
      onSuccess: () => setSubmitMessage('Item removed.'),
    });
  };

  const finalizeClearance = () => {
    if (!wizardClearance || !confirmChecked) return;
    setFinalizing(true);
    router.post(`/clearance/${wizardClearance.id}/finalize`, { confirm: true }, {
      onSuccess: () => { setFinalizing(false); setSubmitMessage('Clearance finalized. Lease terminated and deposit settled.'); },
      onError: () => setFinalizing(false),
    });
  };

  const cancelClearance = () => {
    if (!wizardClearance || !cancelReason.trim()) return;
    router.patch(`/clearance/${wizardClearance.id}`, { cancel: true, cancelled_reason: cancelReason }, {
      onSuccess: () => { setShowCancelConfirm(false); setCancelReason(''); closeWizard(); setSubmitMessage('Clearance cancelled.'); },
    });
  };

  const wizardCurrency = wizardClearance?.currency || eligibleLeases.find((l) => String(l.id) === String(wizardLeaseId))?.currency || 'TZS';
  const wizardDeposit = Number(wizardClearance?.deposit_amount ?? eligibleLeases.find((l) => String(l.id) === String(wizardLeaseId))?.deposit ?? 0);
  const wizardDeductions = Number(wizardClearance?.total_deductions || 0);
  const wizardRefund = Number(wizardClearance?.refund_amount ?? wizardDeposit);
  const wizardShortfall = Number(wizardClearance?.shortfall_amount || 0);
  const wizardIsCompleted = wizardClearance?.status === 'completed';

  return (
    <AppLayout title="Clearance" subtitle="— move-out inspections">
      <Head title="Clearance" />

      <div className="stats-grid" style={{ marginBottom: 20 }}>
        <div className="stat-card">
          <div className="stat-top"><div className="stat-icon amber"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div></div>
          <div className="stat-value">{counts.scheduled + counts.in_progress}</div>
          <div className="stat-label">Open Clearances</div>
        </div>
        <div className="stat-card">
          <div className="stat-top"><div className="stat-icon green"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="20 6 9 17 4 12"/></svg></div><span className="stat-delta up">this month</span></div>
          <div className="stat-value">{stats.thisMonth}</div>
          <div className="stat-label">Completed</div>
        </div>
        <div className="stat-card">
          <div className="stat-top"><div className="stat-icon blue"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></div></div>
          <div className="stat-value">{formatMoney(stats.totalRefunded, 'TZS')}</div>
          <div className="stat-label">Total Refunded</div>
        </div>
        <div className="stat-card">
          <div className="stat-top"><div className="stat-icon red"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"/></svg></div></div>
          <div className="stat-value">{formatMoney(stats.totalRecovered, 'TZS')}</div>
          <div className="stat-label">Damage Recovered</div>
        </div>
      </div>

      {submitMessage && (
        <div style={{ background: 'var(--green-dim)', color: 'var(--green)', border: '1px solid var(--green)', borderRadius: 9, padding: '10px 14px', fontSize: 13, fontWeight: 500, marginBottom: 14, display: 'flex', alignItems: 'center', gap: 8 }}>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><polyline points="20 6 9 17 4 12"/></svg>
          {submitMessage}
        </div>
      )}

      <div className="toolbar" style={{ marginBottom: 14 }}>
        <div className="filters" style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
          {FILTERS.map(([key, label]) => (
            <button key={key} className={`filter-pill ${filter === key ? 'active' : ''}`} onClick={() => setFilter(key)}>
              {label} <span className="pill-count">{counts[key] || 0}</span>
            </button>
          ))}
        </div>
        <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
          <div className="search-box" style={{ width: 200 }}>
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <input type="text" placeholder="Search tenant, unit…" value={search} onChange={(e) => setSearch(e.target.value)} />
          </div>
          {canManage && (
            <button className="btn-primary" style={{ whiteSpace: 'nowrap' }} onClick={openNewWizard} disabled={!eligibleLeases.length}>
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              Start Clearance
            </button>
          )}
        </div>
      </div>

      {canManage && !eligibleLeases.length && (
        <div style={{ background: 'var(--bg-elevated)', border: '1px solid var(--border)', borderRadius: 9, padding: '10px 14px', fontSize: 13, color: 'var(--text-muted)', marginBottom: 14 }}>
          No leases are currently eligible for clearance — a lease must have reached its end date, carry a deposit, and have no clearance already open.
        </div>
      )}

      <div className="card" style={{ overflow: 'hidden' }}>
        <table className="data-table">
          <thead>
            <tr>
              <th>Clearance</th>
              <th>Tenant</th>
              <th>Unit / Property</th>
              <th>Status</th>
              <th>Scheduled</th>
              <th>Deposit</th>
              <th>Deductions</th>
              <th>Refund</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {filtered.length === 0 && <tr><td colSpan={9} style={{ textAlign: 'center', padding: 40, color: 'var(--text-muted)' }}>No clearances match your filters</td></tr>}
            {filtered.map((c) => {
              const meta = STATUS_META[c.status] || STATUS_META.scheduled;
              return (
                <tr key={c.id} style={{ cursor: 'pointer' }} onClick={() => setSelected(c)}>
                  <td style={{ fontWeight: 700, color: 'var(--accent)', fontSize: 12.5 }}>{c.clearance_number}</td>
                  <td>{c.tenant?.name || '—'}</td>
                  <td><div style={{ fontWeight: 600 }}>{c.unit?.unit_number || '—'}</div><div style={{ fontSize: 12, color: 'var(--text-muted)' }}>{c.property?.name || '—'}</div></td>
                  <td><span style={{ fontSize: 12, fontWeight: 600, padding: '2px 9px', borderRadius: 20, background: meta.bg, color: meta.color }}>{meta.label}</span></td>
                  <td style={{ fontSize: 12.5, color: 'var(--text-muted)' }}>{formatDisplayDate(c.scheduled_date)}</td>
                  <td>{formatMoney(c.deposit_amount, c.currency)}</td>
                  <td>{c.total_deductions ? formatMoney(c.total_deductions, c.currency) : '—'}</td>
                  <td style={{ fontWeight: 700 }}>{formatMoney(c.refund_amount, c.currency)}</td>
                  <td><button className="btn-ghost" style={{ fontSize: 12, padding: '4px 8px' }} onClick={(e) => { e.stopPropagation(); setSelected(c); }}>View</button></td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      {/* ── Detail drawer ───────────────────────────────────────────── */}
      <div className={`drawer-overlay ${selected ? 'open' : ''}`} onClick={(e) => e.target === e.currentTarget && setSelected(null)}>
        <div className="drawer">
          {selected && (() => {
            const meta = STATUS_META[selected.status] || STATUS_META.scheduled;
            const docs = selected.documents || [];
            return (
              <>
                <div className="drawer-header">
                  <div>
                    <div style={{ fontSize: 17, fontWeight: 700, letterSpacing: '-.3px' }}>{selected.clearance_number}</div>
                    <div style={{ fontSize: 12, color: 'var(--text-muted)', marginTop: 5, display: 'flex', gap: 8, alignItems: 'center' }}>
                      <span style={{ fontWeight: 600, color: meta.color }}>{meta.label}</span><span>·</span><span>{selected.tenant?.name}</span><span>·</span><span>{selected.unit?.unit_number}</span>
                    </div>
                  </div>
                  <button className="drawer-close" onClick={() => setSelected(null)}>✕</button>
                </div>

                <div className="drawer-body">
                  <div className="drawer-section">
                    <div className="drawer-section-title">Summary</div>
                    <div className="kv-grid">
                      <div className="kv"><div className="kv-label">Property</div><div className="kv-value">{selected.property?.name || '—'}</div></div>
                      <div className="kv"><div className="kv-label">Scheduled</div><div className="kv-value" style={{ fontSize: 12.5 }}>{formatDisplayDate(selected.scheduled_date)}</div></div>
                      <div className="kv"><div className="kv-label">Inspector</div><div className="kv-value" style={{ fontSize: 13 }}>{selected.inspected_by?.name || '—'}</div></div>
                      <div className="kv"><div className="kv-label">Deposit</div><div className="kv-value">{formatMoney(selected.deposit_amount, selected.currency)}</div></div>
                    </div>
                  </div>

                  <div className="drawer-section">
                    <div className="drawer-section-title">Damage Items</div>
                    {selected.items?.length ? (
                      <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 12.5, marginBottom: 8 }}>
                        <thead><tr style={{ color: 'var(--text-muted)', fontSize: 11 }}><th style={{ textAlign: 'left', paddingBottom: 4 }}>Item</th><th style={{ textAlign: 'left', paddingBottom: 4 }}>Category</th><th style={{ textAlign: 'right', paddingBottom: 4 }}>Cost</th></tr></thead>
                        <tbody>{selected.items.map((it) => (
                          <tr key={it.id} style={{ borderTop: '1px solid var(--border-subtle)' }}>
                            <td style={{ padding: '4px 0' }}>{it.description}{it.responsible_party === 'landlord' && <span style={{ color: 'var(--text-muted)' }}> (landlord)</span>}</td>
                            <td>{it.category}</td>
                            <td style={{ textAlign: 'right', fontWeight: 600 }}>{formatMoney(it.cost, selected.currency)}</td>
                          </tr>
                        ))}</tbody>
                      </table>
                    ) : <div style={{ fontSize: 13, color: 'var(--text-muted)', padding: '8px 0' }}>No damage recorded</div>}
                    <div style={{ background: 'var(--bg-elevated)', borderRadius: 9, padding: '10px 12px', marginTop: 8, fontSize: 13 }}>
                      <div style={{ display: 'flex', justifyContent: 'space-between', padding: '3px 0' }}><span style={{ color: 'var(--text-muted)' }}>Deposit</span><strong>{formatMoney(selected.deposit_amount, selected.currency)}</strong></div>
                      <div style={{ display: 'flex', justifyContent: 'space-between', padding: '3px 0' }}><span style={{ color: 'var(--text-muted)' }}>Deductions</span><strong>{formatMoney(selected.total_deductions, selected.currency)}</strong></div>
                      <div style={{ display: 'flex', justifyContent: 'space-between', paddingTop: 8, marginTop: 6, borderTop: '1px solid var(--border)' }}><span style={{ fontWeight: 700 }}>Refund</span><strong style={{ color: 'var(--accent)' }}>{formatMoney(selected.refund_amount, selected.currency)}</strong></div>
                      {Number(selected.shortfall_amount) > 0 && (
                        <div style={{ display: 'flex', justifyContent: 'space-between', padding: '3px 0', color: 'var(--red)' }}><span>Shortfall (tenant owes)</span><strong>{formatMoney(selected.shortfall_amount, selected.currency)}</strong></div>
                      )}
                    </div>
                  </div>

                  {docs.length > 0 && (
                    <div className="drawer-section">
                      <div className="drawer-section-title">Photo Evidence</div>
                      <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                        {docs.map((doc) => (
                          <a key={doc.id} href={`/storage/${doc.file_path}`} target="_blank" rel="noreferrer">
                            <img src={`/storage/${doc.file_path}`} alt={doc.name} style={{ width: 80, height: 80, objectFit: 'cover', borderRadius: 8, border: '1px solid var(--border)', cursor: 'pointer' }} />
                          </a>
                        ))}
                      </div>
                    </div>
                  )}

                  {selected.manager_notes && (
                    <div className="drawer-section">
                      <div className="drawer-section-title">Manager Notes</div>
                      <div style={{ background: 'var(--bg-elevated)', borderRadius: 9, padding: '12px 13px', fontSize: 13, color: 'var(--text-secondary)', lineHeight: 1.6 }}>{selected.manager_notes}</div>
                    </div>
                  )}

                  {selected.status === 'cancelled' && selected.cancelled_reason && (
                    <div className="drawer-section">
                      <div className="drawer-section-title">Cancellation Reason</div>
                      <div style={{ fontSize: 13, color: 'var(--red)' }}>{selected.cancelled_reason}</div>
                    </div>
                  )}
                </div>

                <div className="drawer-footer">
                  {['scheduled', 'in_progress'].includes(selected.status) && canManage && (
                    <button className="btn-primary" style={{ flex: 1, justifyContent: 'center' }} onClick={() => openContinueWizard(selected)}>Continue Inspection</button>
                  )}
                  {selected.status === 'completed' && (
                    <button className="btn-secondary" style={{ flex: 1, justifyContent: 'center' }} onClick={() => window.open(`/clearance/${selected.id}/pdf`, '_blank')}>Download Certificate</button>
                  )}
                  {selected.status === 'scheduled' && canManage && (
                    <button className="btn-danger" onClick={() => { router.delete(`/clearance/${selected.id}`, { onSuccess: () => { setSelected(null); setSubmitMessage('Clearance deleted.'); } }); }}>
                      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
                    </button>
                  )}
                </div>
              </>
            );
          })()}
        </div>
      </div>

      {/* ── Creation / inspection wizard ────────────────────────────── */}
      <div className={`modal-overlay ${showWizard ? 'open' : ''}`} onClick={(e) => e.target === e.currentTarget && closeWizard()}>
        <div className="modal" style={{ width: 'min(720px, calc(100vw - 24px))', height: 'min(860px, 92dvh)', maxHeight: 'min(92vh, calc(100dvh - 20px))', display: 'flex', flexDirection: 'column' }}>
          <div className="modal-header" style={{ flexShrink: 0 }}>
            <div className="modal-title">{wizardClearance ? wizardClearance.clearance_number : 'Start Clearance'}</div>
            <button className="modal-close" onClick={closeWizard}>✕</button>
          </div>

          <div style={{ padding: '14px 24px 0', flexShrink: 0 }}>
            <div className="apv-stepper">
              {WIZARD_STEPS.map((step) => {
                const reachable = step.key === 1 || !!wizardClearance;
                const done = wizardClearance ? (step.key < wizardStep || (step.key === 4 && wizardIsCompleted)) : false;
                const active = wizardStep === step.key;
                return (
                  <div
                    key={step.key}
                    className={`apv-step ${done ? 'done' : ''} ${active ? 'current' : ''} ${reachable ? 'clickable' : ''}`}
                    onClick={() => reachable && setWizardStep(step.key)}
                  >
                    <div className="apv-dot">{done ? '✓' : step.key}</div>
                    <div className="apv-label">{step.label}</div>
                  </div>
                );
              })}
            </div>
          </div>

          <div className="modal-body" style={{ flex: 1, overflowY: 'auto' }}>
            {/* Step 1 — Lease & unit summary */}
            {wizardStep === 1 && (
              <div>
                {!wizardClearance ? (
                  <>
                    <div className="form-group">
                      <label className="form-label">Lease *</label>
                      <select className="form-input form-select" value={wizardLeaseId} onChange={(e) => setWizardLeaseId(e.target.value)}>
                        <option value="">Select a lease to clear…</option>
                        {eligibleLeases.map((l) => (
                          <option key={l.id} value={l.id}>{l.tenant?.name} — {l.unit?.unit_number} ({l.property?.name})</option>
                        ))}
                      </select>
                    </div>
                    <div className="form-group">
                      <label className="form-label">Scheduled Inspection Date</label>
                      <input className="form-input" type="date" value={wizardScheduledDate} onChange={(e) => setWizardScheduledDate(e.target.value)} />
                    </div>
                    {wizardLeaseId && (() => {
                      const lease = eligibleLeases.find((l) => String(l.id) === String(wizardLeaseId));
                      if (!lease) return null;
                      return (
                        <div className="kv-grid" style={{ marginTop: 16 }}>
                          <div className="kv"><div className="kv-label">Tenant</div><div className="kv-value">{lease.tenant?.name}</div></div>
                          <div className="kv"><div className="kv-label">Unit</div><div className="kv-value">{lease.unit?.unit_number}</div></div>
                          <div className="kv"><div className="kv-label">Property</div><div className="kv-value">{lease.property?.name}</div></div>
                          <div className="kv"><div className="kv-label">Monthly Rent</div><div className="kv-value">{formatMoney(lease.monthly_rent, lease.currency)}</div></div>
                          <div className="kv"><div className="kv-label">Security Deposit</div><div className="kv-value accent">{formatMoney(lease.deposit, lease.currency)}</div></div>
                          <div className="kv"><div className="kv-label">Lease Status</div><div className="kv-value" style={{ textTransform: 'capitalize' }}>{lease.status}</div></div>
                        </div>
                      );
                    })()}
                  </>
                ) : (
                  <div className="kv-grid">
                    <div className="kv"><div className="kv-label">Tenant</div><div className="kv-value">{wizardClearance.tenant?.name}</div></div>
                    <div className="kv"><div className="kv-label">Unit</div><div className="kv-value">{wizardClearance.unit?.unit_number}</div></div>
                    <div className="kv"><div className="kv-label">Property</div><div className="kv-value">{wizardClearance.property?.name}</div></div>
                    <div className="kv"><div className="kv-label">Scheduled</div><div className="kv-value">{formatDisplayDate(wizardClearance.scheduled_date)}</div></div>
                    <div className="kv"><div className="kv-label">Security Deposit</div><div className="kv-value accent">{formatMoney(wizardClearance.deposit_amount, wizardClearance.currency)}</div></div>
                    <div className="kv"><div className="kv-label">Status</div><div className="kv-value" style={{ textTransform: 'capitalize' }}>{wizardClearance.status.replace('_', ' ')}</div></div>
                  </div>
                )}
              </div>
            )}

            {/* Step 2 — Room-by-room condition checklist */}
            {wizardStep === 2 && wizardClearance && (
              <div>
                <div style={{ fontSize: 12.5, color: 'var(--text-muted)', marginBottom: 14 }}>Rate the condition of each area. Anything marked "Damaged" can be carried into the Damage &amp; Cost step next.</div>
                {CHECKLIST_CATEGORIES.map((cat) => {
                  const row = checklist[cat] || {};
                  return (
                    <div key={cat} style={{ borderBottom: '1px solid var(--border-subtle)', padding: '10px 0' }}>
                      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 10, marginBottom: row.condition === 'damaged' ? 8 : 0 }}>
                        <div style={{ fontSize: 13.5, fontWeight: 500 }}>{cat}</div>
                        <div style={{ display: 'flex', gap: 4, background: 'var(--bg-elevated)', borderRadius: 8, padding: 3 }}>
                          {Object.entries(CONDITION_META).map(([key, meta]) => (
                            <button
                              key={key}
                              type="button"
                              onClick={() => setChecklistRow(cat, 'condition', key)}
                              style={{ padding: '5px 10px', borderRadius: 6, border: 'none', fontSize: 11.5, fontWeight: 600, fontFamily: 'inherit', cursor: 'pointer', background: row.condition === key ? meta.color : 'none', color: row.condition === key ? '#fff' : 'var(--text-muted)' }}
                            >
                              {meta.label}
                            </button>
                          ))}
                        </div>
                      </div>
                      {row.condition === 'damaged' && (
                        <input
                          className="form-input"
                          type="text"
                          placeholder="Describe the damage…"
                          style={{ fontSize: 12.5 }}
                          value={row.note || ''}
                          onChange={(e) => setChecklistRow(cat, 'note', e.target.value)}
                        />
                      )}
                    </div>
                  );
                })}
              </div>
            )}

            {/* Step 3 — Damage items + cost + photo evidence */}
            {wizardStep === 3 && wizardClearance && (
              <div>
                {wizardClearance.items?.length > 0 && (
                  <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 12.5, marginBottom: 16 }}>
                    <thead><tr style={{ color: 'var(--text-muted)', fontSize: 11 }}><th style={{ textAlign: 'left', paddingBottom: 6 }}>Item</th><th style={{ textAlign: 'left', paddingBottom: 6 }}>Category</th><th style={{ textAlign: 'right', paddingBottom: 6 }}>Cost</th><th></th></tr></thead>
                    <tbody>{wizardClearance.items.map((it) => (
                      <tr key={it.id} style={{ borderTop: '1px solid var(--border-subtle)' }}>
                        <td style={{ padding: '6px 0' }}>{it.description}{it.responsible_party === 'landlord' && <span style={{ color: 'var(--text-muted)' }}> (landlord)</span>}</td>
                        <td>{it.category}</td>
                        <td style={{ textAlign: 'right', fontWeight: 600 }}>{formatMoney(it.cost, wizardClearance.currency)}</td>
                        <td style={{ textAlign: 'right' }}><button type="button" onClick={() => removeItem(it.id)} style={{ background: 'none', border: 'none', color: 'var(--red)', cursor: 'pointer', fontSize: 16 }}>×</button></td>
                      </tr>
                    ))}</tbody>
                  </table>
                )}

                <div style={{ background: 'var(--bg-elevated)', borderRadius: 10, padding: 14 }}>
                  <div style={{ fontSize: '10.5px', fontWeight: 700, letterSpacing: '.6px', textTransform: 'uppercase', color: 'var(--text-muted)', marginBottom: 10 }}>Add Damage Item</div>
                  <div className="form-row">
                    <div className="form-group"><label className="form-label">Category</label><select className="form-input form-select" value={itemDraft.category} onChange={(e) => setItemDraft((d) => ({ ...d, category: e.target.value }))}>{DAMAGE_CATEGORIES.map((c) => <option key={c} value={c}>{c}</option>)}</select></div>
                    <div className="form-group"><label className="form-label">Room / Area</label><input className="form-input" type="text" placeholder="e.g. Kitchen" value={itemDraft.room} onChange={(e) => setItemDraft((d) => ({ ...d, room: e.target.value }))} /></div>
                  </div>
                  <div className="form-group"><label className="form-label">Description *</label><input className="form-input" type="text" placeholder="e.g. Cracked kitchen tile" value={itemDraft.description} onChange={(e) => setItemDraft((d) => ({ ...d, description: e.target.value }))} /></div>
                  <div className="form-row">
                    <div className="form-group"><label className="form-label">Repair Cost *</label><input className="form-input" type="number" placeholder="0" min="0" value={itemDraft.cost} onChange={(e) => setItemDraft((d) => ({ ...d, cost: e.target.value }))} /></div>
                    <div className="form-group">
                      <label className="form-label">Responsible Party</label>
                      <select className="form-input form-select" value={itemDraft.responsible_party} onChange={(e) => setItemDraft((d) => ({ ...d, responsible_party: e.target.value }))}>
                        <option value="tenant">Tenant (deducts from deposit)</option>
                        <option value="landlord">Landlord (wear &amp; tear — no deduction)</option>
                      </select>
                    </div>
                  </div>

                  <div
                    style={{ border: '2px dashed var(--border)', borderRadius: 10, padding: 16, textAlign: 'center', cursor: 'pointer', marginTop: 6 }}
                    onClick={() => document.getElementById('clr-item-image-input')?.click()}
                    onDragOver={(e) => { e.preventDefault(); e.currentTarget.style.borderColor = 'var(--accent)'; }}
                    onDragLeave={(e) => { e.currentTarget.style.borderColor = 'var(--border)'; }}
                    onDrop={(e) => { e.preventDefault(); e.currentTarget.style.borderColor = 'var(--border)'; handleItemImages(e.dataTransfer?.files); }}
                  >
                    <div style={{ fontSize: 12.5, color: 'var(--text-muted)' }}>📷 Click or drag photo evidence here</div>
                    <input id="clr-item-image-input" type="file" accept="image/*" multiple style={{ display: 'none' }} onChange={(e) => handleItemImages(e.target.files)} />
                  </div>
                  {itemImages.length > 0 && (
                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, marginTop: 10 }}>
                      {itemImages.map((img, i) => (
                        <div key={i} style={{ position: 'relative' }}>
                          <img src={img.preview} alt="evidence" style={{ width: 60, height: 60, objectFit: 'cover', borderRadius: 6, border: '1px solid var(--border)' }} />
                          <button type="button" onClick={() => setItemImages((prev) => prev.filter((_, idx) => idx !== i))} style={{ position: 'absolute', top: -4, right: -4, width: 16, height: 16, borderRadius: '50%', background: 'var(--red)', color: '#fff', border: 'none', cursor: 'pointer', fontSize: 10, lineHeight: 1 }}>×</button>
                        </div>
                      ))}
                    </div>
                  )}

                  <button type="button" className="btn-primary" style={{ marginTop: 12, width: '100%', justifyContent: 'center' }} disabled={addingItem || !itemDraft.description.trim() || itemDraft.cost === ''} onClick={addItem}>
                    {addingItem ? 'Adding…' : '+ Add Item'}
                  </button>
                </div>

                <div className="nl-summary-card" style={{ marginTop: 16 }}>
                  <div className="nl-summary-row"><span>Deposit</span><strong>{formatMoney(wizardDeposit, wizardCurrency)}</strong></div>
                  <div className="nl-summary-row"><span>Deductions so far</span><strong>{formatMoney(wizardDeductions, wizardCurrency)}</strong></div>
                  <div className="nl-summary-row" style={{ borderTop: '1px solid var(--border)', paddingTop: 8, marginTop: 4 }}><span style={{ fontWeight: 600 }}>Refund So Far</span><strong style={{ fontSize: 15 }}>{formatMoney(wizardRefund, wizardCurrency)}</strong></div>
                </div>
              </div>
            )}

            {/* Step 4 — Review & finalize */}
            {wizardStep === 4 && wizardClearance && (
              <div>
                {wizardIsCompleted ? (
                  <div style={{ textAlign: 'center', padding: '30px 10px' }}>
                    <div style={{ width: 52, height: 52, borderRadius: '50%', background: 'var(--green-dim)', color: 'var(--green)', display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 14px' }}>
                      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    </div>
                    <div style={{ fontSize: 15, fontWeight: 700, marginBottom: 6 }}>Clearance Finalized</div>
                    <div style={{ fontSize: 13, color: 'var(--text-muted)', marginBottom: 20 }}>The lease has been terminated and the deposit settled.</div>
                    <div className="nl-summary-card" style={{ textAlign: 'left', marginBottom: 16 }}>
                      <div className="nl-summary-row"><span>Deposit</span><strong>{formatMoney(wizardDeposit, wizardCurrency)}</strong></div>
                      <div className="nl-summary-row"><span>Deductions</span><strong>{formatMoney(wizardDeductions, wizardCurrency)}</strong></div>
                      <div className="nl-summary-row" style={{ borderTop: '1px solid var(--border)', paddingTop: 8, marginTop: 4 }}><span style={{ fontWeight: 600 }}>Refunded to Tenant</span><strong style={{ fontSize: 15, color: 'var(--accent)' }}>{formatMoney(wizardRefund, wizardCurrency)}</strong></div>
                      {wizardShortfall > 0 && <div className="nl-summary-row" style={{ color: 'var(--red)' }}><span>Shortfall (tenant owes)</span><strong>{formatMoney(wizardShortfall, wizardCurrency)}</strong></div>}
                    </div>
                    <button className="btn-primary" onClick={() => window.open(`/clearance/${wizardClearance.id}/pdf`, '_blank')}>Download Clearance Certificate</button>
                  </div>
                ) : (
                  <>
                    <div className="kv-grid" style={{ marginBottom: 16 }}>
                      <div className="kv"><div className="kv-label">Tenant</div><div className="kv-value">{wizardClearance.tenant?.name}</div></div>
                      <div className="kv"><div className="kv-label">Unit</div><div className="kv-value">{wizardClearance.unit?.unit_number}</div></div>
                      <div className="kv"><div className="kv-label">Damage Items</div><div className="kv-value">{wizardClearance.items?.length || 0}</div></div>
                      <div className="kv"><div className="kv-label">Inspector</div><div className="kv-value" style={{ fontSize: 13 }}>{wizardClearance.inspected_by?.name || user?.name || '—'}</div></div>
                    </div>

                    <div className="nl-summary-card" style={{ marginBottom: 16 }}>
                      <div className="nl-summary-row"><span>Security Deposit</span><strong>{formatMoney(wizardDeposit, wizardCurrency)}</strong></div>
                      <div className="nl-summary-row"><span>Total Deductions</span><strong>{formatMoney(wizardDeductions, wizardCurrency)}</strong></div>
                      <div className="nl-summary-row" style={{ borderTop: '1px solid var(--border)', paddingTop: 8, marginTop: 4 }}><span style={{ fontWeight: 700 }}>Refund Due to Tenant</span><strong style={{ fontSize: 16, color: 'var(--accent)' }}>{formatMoney(wizardRefund, wizardCurrency)}</strong></div>
                    </div>

                    {wizardShortfall > 0 && (
                      <div style={{ background: 'var(--red-dim, #fef2f2)', color: 'var(--red)', border: '1px solid var(--red)', borderRadius: 9, padding: '12px 14px', fontSize: 13, marginBottom: 16 }}>
                        <strong>Damage costs exceed the deposit.</strong> The tenant still owes <strong>{formatMoney(wizardShortfall, wizardCurrency)}</strong>. This is not automatically invoiced — follow up separately.
                      </div>
                    )}

                    <label style={{ display: 'flex', alignItems: 'flex-start', gap: 10, fontSize: 13, background: 'var(--bg-elevated)', borderRadius: 9, padding: '12px 13px', cursor: 'pointer' }}>
                      <input type="checkbox" checked={confirmChecked} onChange={(e) => setConfirmChecked(e.target.checked)} style={{ marginTop: 2 }} />
                      <span>I confirm the inspection is complete and the figures above are correct. Finalizing will <strong>terminate the lease</strong> and post the deposit settlement — this cannot be undone.</span>
                    </label>

                    <button
                      className="btn-danger"
                      style={{ width: '100%', justifyContent: 'center', marginTop: 14 }}
                      disabled={!confirmChecked || finalizing}
                      onClick={finalizeClearance}
                    >
                      {finalizing ? 'Finalizing…' : 'Finalize & Terminate Lease'}
                    </button>
                  </>
                )}
              </div>
            )}

            {wizardStep > 1 && !wizardClearance && (
              <div style={{ padding: 30, textAlign: 'center', color: 'var(--text-muted)', fontSize: 13 }}>Start the clearance from Step 1 first.</div>
            )}
          </div>

          <div className="modal-footer" style={{ flexShrink: 0, justifyContent: 'space-between' }}>
            <div>
              {wizardClearance && !wizardIsCompleted && wizardClearance.status !== 'cancelled' && (
                <button type="button" className="btn-ghost" style={{ color: 'var(--red)' }} onClick={() => setShowCancelConfirm(true)}>Cancel Clearance</button>
              )}
            </div>
            <div style={{ display: 'flex', gap: 8 }}>
              <button type="button" className="btn-ghost" onClick={closeWizard}>{wizardIsCompleted ? 'Close' : 'Save & Close'}</button>
              {wizardStep === 1 && !wizardClearance && (
                <button type="button" className="btn-primary" disabled={!wizardLeaseId || creating} onClick={startClearance}>{creating ? 'Starting…' : 'Start Inspection'}</button>
              )}
              {wizardStep === 1 && wizardClearance && (
                <button type="button" className="btn-primary" onClick={() => setWizardStep(2)}>Continue</button>
              )}
              {wizardStep === 2 && (
                <button type="button" className="btn-primary" disabled={checklistSaving} onClick={saveChecklist}>{checklistSaving ? 'Saving…' : 'Save & Continue'}</button>
              )}
              {wizardStep === 3 && (
                <button type="button" className="btn-primary" onClick={() => setWizardStep(4)}>Continue to Review</button>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* Cancel confirmation */}
      <div className={`modal-overlay ${showCancelConfirm ? 'open' : ''}`} onClick={(e) => e.target === e.currentTarget && setShowCancelConfirm(false)}>
        <div className="modal" style={{ width: 420 }}>
          <div className="modal-header"><div className="modal-title">Cancel Clearance</div><button className="modal-close" onClick={() => setShowCancelConfirm(false)}>✕</button></div>
          <div className="modal-body">
            <p style={{ margin: '0 0 12px', fontSize: 14, color: 'var(--text-secondary)', lineHeight: 1.6 }}>This abandons the clearance without affecting the lease or deposit. Give a short reason.</p>
            <textarea className="form-input" rows={3} style={{ resize: 'vertical' }} placeholder="e.g. Tenant renewed instead of moving out" value={cancelReason} onChange={(e) => setCancelReason(e.target.value)} />
          </div>
          <div className="modal-footer">
            <button className="btn-ghost" onClick={() => setShowCancelConfirm(false)}>Keep Clearance</button>
            <button className="btn-danger" disabled={!cancelReason.trim()} onClick={cancelClearance}>Cancel Clearance</button>
          </div>
        </div>
      </div>
    </AppLayout>
  );
}
