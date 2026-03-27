import { useState } from 'react';
import { router } from '@inertiajs/react';

/* ─── helpers ──────────────────────────────────────────────────── */
function formatDate(ts) {
    if (!ts) return '—';
    return new Date(ts).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
}

function daysAgo(ts) {
    if (!ts) return null;
    const diff = Math.floor((Date.now() - new Date(ts).getTime()) / 86_400_000);
    if (diff === 0) return 'Today';
    if (diff === 1) return '1 day ago';
    return `${diff} days ago`;
}

function fmt(amount, currency = 'TZS') {
    if (amount == null) return '—';
    return currency + ' ' + Number(amount).toLocaleString();
}

function StatusBadge({ status }) {
    const map = {
        pending_accountant: { label: 'Pending Accountant', color: 'rgba(245,158,11,0.15)', text: 'rgb(180,120,0)', border: 'rgba(245,158,11,0.35)' },
        pending_pm:         { label: 'Pending PM',          color: 'rgba(99,102,241,0.12)',  text: 'rgb(99,102,241)', border: 'rgba(99,102,241,0.3)' },
    };
    const s = map[status] ?? { label: status, color: 'rgba(100,100,100,0.1)', text: 'var(--text-muted)', border: 'transparent' };
    return (
        <span style={{ display: 'inline-block', padding: '2px 9px', borderRadius: 20, fontSize: 11.5, fontWeight: 600, background: s.color, color: s.text, border: `1px solid ${s.border}` }}>
            {s.label}
        </span>
    );
}

function PriorityBadge({ priority }) {
    const map = {
        high:   { color: 'rgba(239,68,68,0.12)',   text: 'rgb(220,38,38)',   label: 'High' },
        medium: { color: 'rgba(245,158,11,0.12)',  text: 'rgb(180,120,0)',   label: 'Medium' },
        med:    { color: 'rgba(245,158,11,0.12)',  text: 'rgb(180,120,0)',   label: 'Medium' },
        low:    { color: 'rgba(34,197,94,0.12)',   text: 'rgb(22,163,74)',   label: 'Low' },
    };
    const p = map[String(priority).toLowerCase()] ?? { color: 'rgba(100,100,100,0.1)', text: 'var(--text-muted)', label: priority ?? '—' };
    return (
        <span style={{ display: 'inline-block', padding: '2px 9px', borderRadius: 20, fontSize: 11.5, fontWeight: 600, background: p.color, color: p.text, border: `1px solid ${p.color}` }}>
            {p.label}
        </span>
    );
}

/* ─── Small label+value block ───────────────────────────────────── */
function Detail({ label, value, sub }) {
    return (
        <div>
            <div style={{ fontSize: 10.5, fontWeight: 700, color: 'var(--text-muted)', textTransform: 'uppercase', letterSpacing: '0.04em', marginBottom: 2 }}>{label}</div>
            <div style={{ fontSize: 13, fontWeight: 600, color: 'var(--text-primary)' }}>{value}</div>
            {sub && <div style={{ fontSize: 11.5, color: 'var(--text-muted)' }}>{sub}</div>}
        </div>
    );
}

/* ─── Decision Modal ────────────────────────────────────────────── */
function DecisionModal({ open, action, subject, context, onClose, onConfirm, submitting }) {
    const [message, setMessage] = useState('');
    const isReject   = action === 'reject';
    const isValid    = isReject ? message.trim().length >= 5 : true;

    const handleClose = () => { setMessage(''); onClose(); };
    const handleConfirm = () => { onConfirm(message.trim()); setMessage(''); };

    return (
        <div className={`modal-overlay ${open ? 'open' : ''}`} onClick={(e) => e.target === e.currentTarget && handleClose()}>
            <div className="modal" style={{ maxWidth: 480 }} onClick={(e) => e.stopPropagation()}>
                <div className="modal-header">
                    <h3>{isReject ? 'Reject' : 'Approve'} {subject}</h3>
                    <button type="button" className="modal-close" onClick={handleClose}>×</button>
                </div>
                <div className="modal-body">
                    <p style={{ marginBottom: 16, lineHeight: 1.65, color: 'var(--text-secondary)' }}>
                        {isReject
                            ? <><strong style={{ color: 'var(--danger)' }}>Rejecting</strong> {context}. Provide a clear reason so the manager can act on it.</>
                            : <><strong style={{ color: 'var(--green)' }}>Approving</strong> {context}. An optional note will be included in the notification to the manager.</>
                        }
                    </p>
                    <div className="form-group">
                        <label className="form-label" style={{ marginBottom: 5 }}>
                            {isReject ? 'Reason for rejection *' : 'Note to manager (optional)'}
                        </label>
                        <textarea
                            className="form-input"
                            rows={3}
                            value={message}
                            onChange={(e) => setMessage(e.target.value)}
                            placeholder={isReject
                                ? 'Explain clearly why this is being rejected…'
                                : 'e.g. Everything looks good. Coordinate with the tenant for move-in.'}
                            style={{ resize: 'vertical', minHeight: 80 }}
                        />
                        {isReject && message.trim().length > 0 && message.trim().length < 5 && (
                            <p style={{ color: 'var(--danger)', fontSize: 11.5, marginTop: 4 }}>Please enter at least 5 characters.</p>
                        )}
                    </div>
                </div>
                <div className="modal-footer">
                    <button type="button" className="btn-ghost" onClick={handleClose} disabled={submitting}>Cancel</button>
                    <button
                        type="button"
                        className={isReject ? 'btn-danger' : 'btn-primary'}
                        disabled={!isValid || submitting}
                        onClick={handleConfirm}
                    >
                        {submitting ? (isReject ? 'Rejecting…' : 'Approving…') : (isReject ? 'Reject' : 'Approve')}
                    </button>
                </div>
            </div>
        </div>
    );
}

/* ─── Detail Drawer ─────────────────────────────────────────────── */
function DetailDrawer({ drawer, onClose, onApprove, onReject, submitting }) {
    const open = !!drawer;
    const type = drawer?.type;
    const item = drawer?.item;
    const isLease = type === 'lease';

    const title = !item ? '' : isLease
        ? (item.lease_number ?? `Lease #${item.id}`)
        : (item.ticket_number ?? `TK #${item.id}`);

    const log = (() => {
        if (!item || !isLease) return [];
        try { return JSON.parse(item.approval_log ?? '[]'); }
        catch { return []; }
    })();

    return (
        <div
            className={`drawer-overlay ${open ? 'open' : ''}`}
            onClick={(e) => e.target === e.currentTarget && onClose()}
        >
            <div className="drawer">
                {/* Drawer header */}
                <div className="drawer-header">
                    <div>
                        <div style={{ fontWeight: 700, fontSize: 15 }}>{title}</div>
                        {item && (
                            <div style={{ fontSize: 12, color: 'var(--text-muted)', marginTop: 2 }}>
                                {isLease ? 'Lease Request' : 'Maintenance Request'}
                                {daysAgo(item.created_at) && ` · Submitted ${daysAgo(item.created_at)}`}
                            </div>
                        )}
                    </div>
                    <button type="button" className="drawer-close" onClick={onClose}>×</button>
                </div>

                {/* Drawer body */}
                <div className="drawer-body">
                    {item && isLease && (
                        <>
                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px 20px', marginBottom: 20 }}>
                                <Detail label="Status" value={<StatusBadge status={item.status} />} />
                                <Detail label="Tenant" value={item.tenant?.name ?? '—'} sub={item.tenant?.email} />
                                <Detail label="Phone" value={item.tenant?.phone ?? '—'} />
                                <Detail label="Unit" value={[item.unit?.unit_number, item.unit?.floor ? `Floor ${item.unit.floor}` : null].filter(Boolean).join(' · ')} sub={item.unit?.property?.name} />
                                <Detail label="Monthly Rent" value={fmt(item.monthly_rent, item.currency)} />
                                <Detail label="Deposit" value={item.deposit ? fmt(item.deposit, item.currency) : '—'} />
                                <Detail label="Payment Cycle" value={item.payment_cycle ?? '—'} />
                                <Detail label="Start Date" value={formatDate(item.start_date)} />
                                <Detail label="End Date" value={formatDate(item.end_date)} />
                                {item.unit?.property?.manager?.name && (
                                    <Detail label="Property Manager" value={item.unit.property.manager.name} />
                                )}
                            </div>

                            {log.length > 0 && (
                                <div style={{ borderTop: '1px solid var(--border)', paddingTop: 16 }}>
                                    <div style={{ fontSize: 10.5, fontWeight: 700, color: 'var(--text-muted)', textTransform: 'uppercase', letterSpacing: '0.04em', marginBottom: 10 }}>
                                        Approval History
                                    </div>
                                    <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                                        {log.map((entry, i) => (
                                            <div key={i} style={{ fontSize: 12.5, color: 'var(--text-secondary)', display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                                                <span style={{ color: entry.action === 'approved' ? 'var(--green)' : 'var(--danger)', fontWeight: 600, textTransform: 'capitalize' }}>{entry.action}</span>
                                                <span>by {entry.by}</span>
                                                <span style={{ color: 'var(--text-muted)' }}>{entry.date}</span>
                                                {(entry.text || entry.reason) && <span>— {entry.text ?? entry.reason}</span>}
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </>
                    )}

                    {item && !isLease && (
                        <>
                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px 20px', marginBottom: 20 }}>
                                <Detail label="Priority" value={<PriorityBadge priority={item.priority} />} />
                                <Detail label="Unit" value={item.unit?.unit_number ?? item.unit_ref ?? '—'} sub={item.unit?.property?.name ?? item.property?.name} />
                                <Detail label="Category" value={item.category ?? '—'} />
                                <Detail label="Reported By" value={item.reported_by ?? '—'} />
                                <Detail label="Reported Date" value={formatDate(item.reported_date ?? item.created_at)} />
                                {(item.unit?.property?.manager?.name ?? item.property?.manager?.name) && (
                                    <Detail label="Property Manager" value={item.unit?.property?.manager?.name ?? item.property?.manager?.name} />
                                )}
                            </div>

                            {item.description && (
                                <div style={{ borderTop: '1px solid var(--border)', paddingTop: 16 }}>
                                    <div style={{ fontSize: 10.5, fontWeight: 700, color: 'var(--text-muted)', textTransform: 'uppercase', letterSpacing: '0.04em', marginBottom: 8 }}>Description</div>
                                    <p style={{ fontSize: 13, color: 'var(--text-secondary)', lineHeight: 1.65, margin: 0, whiteSpace: 'pre-wrap' }}>
                                        {item.description}
                                    </p>
                                </div>
                            )}
                        </>
                    )}
                </div>

                {/* Drawer footer */}
                {item && (
                    <div className="drawer-footer">
                        <button
                            type="button"
                            className="btn-danger"
                            style={{ flex: 1 }}
                            disabled={submitting}
                            onClick={() => onReject(type, item)}
                        >
                            ✕ Reject
                        </button>
                        <button
                            type="button"
                            className="btn-primary"
                            style={{ flex: 1 }}
                            disabled={submitting}
                            onClick={() => onApprove(type, item)}
                        >
                            ✓ Approve
                        </button>
                    </div>
                )}
            </div>
        </div>
    );
}

/* ─── Main Component ────────────────────────────────────────────── */
export default function ApprovalsPage({ pendingLeases = [], pendingMaintenance = [] }) {
    const [drawer, setDrawer]         = useState(null); // { type: 'lease'|'maintenance', item }
    const [modal, setModal]           = useState(null); // { action: 'approve'|'reject', type, item }
    const [submitting, setSubmitting] = useState(false);

    const openDrawer  = (type, item) => setDrawer({ type, item });
    const closeDrawer = () => { if (!submitting) setDrawer(null); };

    const openModal  = (action, type, item) => setModal({ action, type, item });
    const closeModal = () => { if (!submitting) setModal(null); };

    const handleConfirm = (message) => {
        if (!modal || submitting) return;
        setSubmitting(true);
        const { action, type, item } = modal;
        const url = type === 'lease'
            ? `/superuser/leases/${item.id}/${action}`
            : `/superuser/maintenance/${item.id}/${action}`;

        router.post(url, { message }, {
            preserveScroll: true,
            onSuccess: () => { setModal(null); setDrawer(null); setSubmitting(false); },
            onError:   () => { setSubmitting(false); },
        });
    };

    const totalPending = pendingLeases.length + pendingMaintenance.length;

    const modalSubject = modal ? (modal.type === 'lease' ? 'Lease' : 'Maintenance Ticket') : '';
    const modalContext = modal ? (() => {
        const item = modal.item;
        if (modal.type === 'lease') {
            const ref      = item.lease_number ?? `Lease #${item.id}`;
            const tenant   = item.tenant?.name ?? 'the tenant';
            const property = item.unit?.property?.name ?? 'the property';
            return `lease ${ref} for ${tenant} at ${property}`;
        }
        const ref      = item.ticket_number ?? `TK #${item.id}`;
        const property = item.unit?.property?.name ?? item.property?.name ?? 'the property';
        return `ticket ${ref} — "${item.title}" at ${property}`;
    })() : '';

    return (
        <>
            <DetailDrawer
                drawer={drawer}
                onClose={closeDrawer}
                onApprove={(type, item) => openModal('approve', type, item)}
                onReject={(type, item)  => openModal('reject',  type, item)}
                submitting={submitting}
            />

            <DecisionModal
                open={!!modal}
                action={modal?.action}
                subject={modalSubject}
                context={modalContext}
                onClose={closeModal}
                onConfirm={handleConfirm}
                submitting={submitting}
            />

            {/* Page header */}
            <div className="page-header">
                <div>
                    <div className="page-title">Pending Approvals</div>
                    <div className="page-sub">
                        {totalPending === 0
                            ? 'All caught up — no pending requests'
                            : `${totalPending} request${totalPending !== 1 ? 's' : ''} awaiting your decision`}
                    </div>
                </div>
            </div>

            {/* Stats bar */}
            <div className="grid-3" style={{ marginBottom: 20 }}>
                <div style={{ background: 'var(--bg-surface)', border: '1px solid var(--border)', borderRadius: 13, padding: '16px 18px' }}>
                    <div style={{ fontSize: 12, color: 'var(--text-muted)', marginBottom: 5 }}>Total Pending</div>
                    <div style={{ fontSize: 28, fontWeight: 800 }}>{totalPending}</div>
                </div>
                <div style={{ background: 'var(--bg-surface)', border: '1px solid var(--border)', borderRadius: 13, padding: '16px 18px' }}>
                    <div style={{ fontSize: 12, color: 'var(--text-muted)', marginBottom: 5 }}>Pending Leases</div>
                    <div style={{ fontSize: 28, fontWeight: 800, color: 'var(--amber)' }}>{pendingLeases.length}</div>
                </div>
                <div style={{ background: 'var(--bg-surface)', border: '1px solid var(--border)', borderRadius: 13, padding: '16px 18px' }}>
                    <div style={{ fontSize: 12, color: 'var(--text-muted)', marginBottom: 5 }}>Pending Maintenance</div>
                    <div style={{ fontSize: 28, fontWeight: 800, color: 'var(--primary)' }}>{pendingMaintenance.length}</div>
                </div>
            </div>

            {/* Lease section */}
            <div style={{ marginBottom: 28 }}>
                <div style={{ fontSize: 13, fontWeight: 700, color: 'var(--text-secondary)', textTransform: 'uppercase', letterSpacing: '0.05em', marginBottom: 12 }}>
                    Lease Approvals · {pendingLeases.length}
                </div>
                {pendingLeases.length === 0 ? (
                    <div style={{ background: 'var(--bg-surface)', border: '1px solid var(--border)', borderRadius: 12, padding: '24px 20px', textAlign: 'center', color: 'var(--text-muted)', fontSize: 13 }}>
                        No leases pending approval
                    </div>
                ) : (
                    <div className="card">
                        <table className="data-table">
                            <thead>
                                <tr>
                                    <th>Reference</th>
                                    <th>Tenant</th>
                                    <th>Property / Unit</th>
                                    <th>Monthly Rent</th>
                                    <th>Status</th>
                                    <th>Submitted</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                {pendingLeases.map((lease) => {
                                    const unit     = lease.unit?.unit_number ?? '—';
                                    const property = lease.unit?.property?.name ?? '—';
                                    return (
                                        <tr
                                            key={lease.id}
                                            style={{ cursor: 'pointer' }}
                                            onClick={() => openDrawer('lease', lease)}
                                        >
                                            <td style={{ fontWeight: 600 }}>{lease.lease_number ?? `Lease #${lease.id}`}</td>
                                            <td>
                                                <div style={{ fontWeight: 500 }}>{lease.tenant?.name ?? '—'}</div>
                                                <div style={{ fontSize: 11.5, color: 'var(--text-muted)' }}>{lease.tenant?.email}</div>
                                            </td>
                                            <td>
                                                <div>{property}</div>
                                                <div style={{ fontSize: 11.5, color: 'var(--text-muted)' }}>Unit {unit}</div>
                                            </td>
                                            <td>{fmt(lease.monthly_rent, lease.currency)}</td>
                                            <td><StatusBadge status={lease.status} /></td>
                                            <td style={{ color: 'var(--text-muted)', fontSize: 12.5 }}>{daysAgo(lease.created_at) ?? '—'}</td>
                                            <td style={{ color: 'var(--text-muted)', fontSize: 16, textAlign: 'right' }}>›</td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            {/* Maintenance section */}
            <div>
                <div style={{ fontSize: 13, fontWeight: 700, color: 'var(--text-secondary)', textTransform: 'uppercase', letterSpacing: '0.05em', marginBottom: 12 }}>
                    Maintenance Approvals · {pendingMaintenance.length}
                </div>
                {pendingMaintenance.length === 0 ? (
                    <div style={{ background: 'var(--bg-surface)', border: '1px solid var(--border)', borderRadius: 12, padding: '24px 20px', textAlign: 'center', color: 'var(--text-muted)', fontSize: 13 }}>
                        No maintenance tickets pending approval
                    </div>
                ) : (
                    <div className="card">
                        <table className="data-table">
                            <thead>
                                <tr>
                                    <th>Ticket</th>
                                    <th>Title</th>
                                    <th>Property / Unit</th>
                                    <th>Category</th>
                                    <th>Priority</th>
                                    <th>Submitted</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                {pendingMaintenance.map((ticket) => {
                                    const unit     = ticket.unit?.unit_number ?? ticket.unit_ref ?? '—';
                                    const property = ticket.unit?.property?.name ?? ticket.property?.name ?? '—';
                                    return (
                                        <tr
                                            key={ticket.id}
                                            style={{ cursor: 'pointer' }}
                                            onClick={() => openDrawer('maintenance', ticket)}
                                        >
                                            <td style={{ fontWeight: 600, fontSize: 12.5, color: 'var(--text-muted)' }}>{ticket.ticket_number ?? `TK #${ticket.id}`}</td>
                                            <td style={{ fontWeight: 500 }}>{ticket.title}</td>
                                            <td>
                                                <div>{property}</div>
                                                <div style={{ fontSize: 11.5, color: 'var(--text-muted)' }}>Unit {unit}</div>
                                            </td>
                                            <td style={{ fontSize: 13, color: 'var(--text-secondary)' }}>{ticket.category ?? '—'}</td>
                                            <td><PriorityBadge priority={ticket.priority} /></td>
                                            <td style={{ color: 'var(--text-muted)', fontSize: 12.5 }}>{daysAgo(ticket.created_at) ?? '—'}</td>
                                            <td style={{ color: 'var(--text-muted)', fontSize: 16, textAlign: 'right' }}>›</td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </>
    );
}
