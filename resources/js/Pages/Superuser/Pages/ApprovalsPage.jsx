import { useState } from 'react';
import { router } from '@inertiajs/react';
import { formatDisplayDate } from '@/utils/dateFormat';

function formatDate(ts) {
    return formatDisplayDate(ts);
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

function invoiceTotal(items = []) {
    return (items || []).reduce((sum, item) => sum + Number(item.total || 0), 0);
}

function parseApprovalLog(raw) {
    if (Array.isArray(raw)) return raw;
    if (typeof raw === 'string' && raw.trim()) {
        try {
            const parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : [];
        } catch {
            return [];
        }
    }
    return [];
}

function addMonthsDate(date, months) {
    const d = new Date(date);
    d.setMonth(d.getMonth() + Number(months || 0));
    return d;
}

function toLocalIsoDate(date) {
    if (!(date instanceof Date) || Number.isNaN(date.getTime())) return '';
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function calcLeaseProgress(lease) {
    const start = new Date(`${lease?.start_date}T00:00:00`);
    const end = new Date(`${lease?.end_date}T00:00:00`);
    const now = new Date();

    if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime()) || end <= start) {
        return { pct: 0, daysLeft: 0 };
    }

    const totalMs = end.getTime() - start.getTime();
    const elapsedMs = Math.min(Math.max(now.getTime() - start.getTime(), 0), totalMs);
    return {
        pct: (elapsedMs / totalMs) * 100,
        daysLeft: Math.ceil((end.getTime() - now.getTime()) / 86_400_000),
    };
}

function buildPaymentSchedule(lease) {
    if (Array.isArray(lease?.installments) && lease.installments.length > 0) {
        return lease.installments.map((inst, idx) => ({
            installNum: Number(inst.sequence) || idx + 1,
            dueDate: formatDate(inst.due_date),
            period: `${formatDate(inst.period_start)} - ${formatDate(inst.period_end)}`,
            amount: Number(inst.amount || 0),
            status: inst.status || 'unpaid',
        }));
    }

    const start = new Date(`${lease?.rent_start_date || lease?.start_date}T00:00:00`);
    const end = new Date(`${lease?.end_date}T00:00:00`);
    const cycle = Number(lease?.payment_cycle) || 3;
    const monthlyRent = Number(lease?.monthly_rent) || 0;

    if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime()) || end <= start) {
        return [];
    }

    const rows = [];
    let cursor = new Date(start);
    let index = 1;

    while (cursor < end && index <= 60) {
        const periodStart = new Date(cursor);
        const nextCursor = addMonthsDate(periodStart, cycle);
        const periodEnd = new Date(Math.min(nextCursor.getTime(), end.getTime()));
        periodEnd.setDate(periodEnd.getDate() - 1);
        const monthsInPeriod = Math.max(1, Math.round((nextCursor.getTime() - periodStart.getTime()) / (1000 * 60 * 60 * 24 * 30.4375)));

        rows.push({
            installNum: index,
            dueDate: formatDate(toLocalIsoDate(periodStart)),
            period: `${formatDate(toLocalIsoDate(periodStart))} - ${formatDate(toLocalIsoDate(periodEnd))}`,
            amount: monthlyRent * monthsInPeriod,
            status: 'scheduled',
        });

        cursor = nextCursor;
        index += 1;
    }

    return rows;
}

function leaseFinancials(lease) {
    const rent = Number(lease?.monthly_rent || 0);
    const serviceCharge = Number(lease?.unit?.service_charge || 0);
    const vatRate = Number(lease?.vat_rate ?? 18);
    const rentWhtRate = Number(lease?.wht_rate ?? 10);
    const serviceChargeWhtRate = Number(lease?.service_charge_rate ?? 5);
    const subtotal = rent + serviceCharge;
    const vat = Math.round(subtotal * (vatRate / 100));
    const gross = subtotal + vat;
    const rentWht = Math.round(rent * (rentWhtRate / 100));
    const serviceChargeWht = Math.round(serviceCharge * (serviceChargeWhtRate / 100));
    const wht = rentWht + serviceChargeWht;
    const fitoutDays = Number(lease?.fitout_days || 0);
    const fitoutExtraSC = fitoutDays > 0 ? Math.round(serviceCharge * fitoutDays / 30) : 0;
    const fitoutExtraVAT = fitoutDays > 0 ? Math.round(fitoutExtraSC * (vatRate / 100)) : 0;

    return {
        rent,
        serviceCharge,
        subtotal,
        vat,
        gross,
        rentWht,
        serviceChargeWht,
        wht,
        net: gross - wht,
        instalment: (gross - wht) * Number(lease?.payment_cycle || 0),
        annual: gross * 12,
        fitoutExtraSC,
        fitoutExtraVAT,
        vatRate,
        rentWhtRate,
        serviceChargeWhtRate,
    };
}

function StatusBadge({ status }) {
    const map = {
        pending_accountant: { label: 'Pending Accountant', color: 'rgba(245,158,11,0.15)', text: 'rgb(180,120,0)', border: 'rgba(245,158,11,0.35)' },
        pending_pm:         { label: 'Pending PM', color: 'rgba(99,102,241,0.12)', text: 'rgb(99,102,241)', border: 'rgba(99,102,241,0.3)' },
        pending_approval:   { label: 'Pending Approval', color: 'rgba(245,158,11,0.15)', text: 'rgb(180,120,0)', border: 'rgba(245,158,11,0.35)' },
        approved:           { label: 'Approved', color: 'rgba(34,197,94,0.12)', text: 'rgb(22,163,74)', border: 'rgba(34,197,94,0.25)' },
        rejected:           { label: 'Rejected', color: 'rgba(239,68,68,0.12)', text: 'rgb(220,38,38)', border: 'rgba(239,68,68,0.3)' },
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
        high:   { color: 'rgba(239,68,68,0.12)', text: 'rgb(220,38,38)', label: 'High' },
        medium: { color: 'rgba(245,158,11,0.12)', text: 'rgb(180,120,0)', label: 'Medium' },
        med:    { color: 'rgba(245,158,11,0.12)', text: 'rgb(180,120,0)', label: 'Medium' },
        low:    { color: 'rgba(34,197,94,0.12)', text: 'rgb(22,163,74)', label: 'Low' },
    };
    const p = map[String(priority).toLowerCase()] ?? { color: 'rgba(100,100,100,0.1)', text: 'var(--text-muted)', label: priority ?? '—' };
    return (
        <span style={{ display: 'inline-block', padding: '2px 9px', borderRadius: 20, fontSize: 11.5, fontWeight: 600, background: p.color, color: p.text, border: `1px solid ${p.color}` }}>
            {p.label}
        </span>
    );
}

function Detail({ label, value, sub }) {
    return (
        <div>
            <div style={{ fontSize: 10.5, fontWeight: 700, color: 'var(--text-muted)', textTransform: 'uppercase', letterSpacing: '0.04em', marginBottom: 2 }}>{label}</div>
            <div style={{ fontSize: 13, fontWeight: 600, color: 'var(--text-primary)' }}>{value}</div>
            {sub && <div style={{ fontSize: 11.5, color: 'var(--text-muted)' }}>{sub}</div>}
        </div>
    );
}

function DecisionModal({ open, action, subject, context, onClose, onConfirm, submitting }) {
    const [message, setMessage] = useState('');
    const isReject = action === 'reject';
    const isValid = isReject ? message.trim().length >= 5 : true;

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
                            ? <><strong style={{ color: 'var(--danger)' }}>Rejecting</strong> {context}. Provide a clear reason so the requester can act on it.</>
                            : <><strong style={{ color: 'var(--green)' }}>Approving</strong> {context}. The requester will be notified where notifications are configured.</>
                        }
                    </p>
                    <div className="form-group">
                        <label className="form-label" style={{ marginBottom: 5 }}>
                            {isReject ? 'Reason for rejection *' : 'Approval note (optional)'}
                        </label>
                        <textarea
                            className="form-input"
                            rows={3}
                            value={message}
                            onChange={(e) => setMessage(e.target.value)}
                            placeholder={isReject ? 'Explain clearly why this request is being rejected…' : 'Optional note'}
                            style={{ resize: 'vertical', minHeight: 80 }}
                        />
                    </div>
                </div>
                <div className="modal-footer">
                    <button type="button" className="btn-ghost" onClick={handleClose} disabled={submitting}>Cancel</button>
                    <button type="button" className={isReject ? 'btn-danger' : 'btn-primary'} disabled={!isValid || submitting} onClick={handleConfirm}>
                        {submitting ? (isReject ? 'Rejecting…' : 'Approving…') : (isReject ? 'Reject' : 'Approve')}
                    </button>
                </div>
            </div>
        </div>
    );
}

function DetailDrawer({ drawer, onClose, onApprove, onReject, submitting }) {
    const open = !!drawer;
    const type = drawer?.type;
    const item = drawer?.item;
    const isLease = type === 'lease';
    const isMaintenance = type === 'maintenance';
    const isTeam = type === 'team';
    const isUnit = type === 'unit';
    const isInvoice = type === 'invoice';

    const title = !item ? '' : isLease
        ? (item.lease_number ?? `Lease #${item.id}`)
        : isMaintenance
            ? (item.ticket_number ?? `TK #${item.id}`)
            : isTeam
                ? item.name
                : isUnit
                    ? item.unit_number
                    : item.invoice_number;

    const log = (() => {
        if (!item || !isLease) return [];
        return parseApprovalLog(item.approval_log);
    })();

    const leaseMoney = isLease ? leaseFinancials(item) : null;
    const leaseProgress = isLease ? calcLeaseProgress(item) : null;
    const leaseSchedule = isLease ? buildPaymentSchedule(item) : [];
    const leaseCurrency = item?.currency || item?.unit?.currency || 'TZS';

    return (
        <div className={`drawer-overlay ${open ? 'open' : ''}`} onClick={(e) => e.target === e.currentTarget && onClose()}>
            <div className="drawer" style={isLease ? { width: 680, maxWidth: 'calc(100vw - 24px)' } : undefined}>
                <div className="drawer-header">
                    <div>
                        <div style={{ fontWeight: 700, fontSize: 15 }}>{title}</div>
                        {item && (
                            <div style={{ fontSize: 12, color: 'var(--text-muted)', marginTop: 2 }}>
                                {isLease ? 'Lease Request' : isMaintenance ? 'Maintenance Request' : isTeam ? 'Team Request' : isUnit ? 'Unit Request' : 'Invoice Approval'}
                                {daysAgo(item.created_at) && ` · Submitted ${daysAgo(item.created_at)}`}
                            </div>
                        )}
                    </div>
                    <button type="button" className="drawer-close" onClick={onClose}>×</button>
                </div>

                <div className="drawer-body">
                    {item && isLease && (
                        <>
                            <div style={{ borderBottom: '1px solid var(--border)', paddingBottom: 16, marginBottom: 16 }}>
                                <div style={{ fontSize: 10.5, fontWeight: 700, color: 'var(--text-muted)', textTransform: 'uppercase', letterSpacing: '0.04em', marginBottom: 10 }}>Approval Workflow</div>
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10, marginBottom: 12 }}>
                                    <div style={{ background: 'var(--bg-elevated)', borderRadius: 9, padding: '11px 12px' }}>
                                        <div style={{ fontSize: 12, fontWeight: 700, color: 'var(--green)' }}>Submitted</div>
                                        <div style={{ fontSize: 11.5, color: 'var(--text-muted)', marginTop: 2 }}>Manager / Staff</div>
                                    </div>
                                    <div style={{ background: 'var(--bg-elevated)', borderRadius: 9, padding: '11px 12px', border: '1px solid var(--accent)' }}>
                                        <div style={{ fontSize: 12, fontWeight: 700, color: 'var(--accent)' }}>Superuser Approval</div>
                                        <div style={{ fontSize: 11.5, color: 'var(--text-muted)', marginTop: 2 }}>Awaiting decision</div>
                                    </div>
                                </div>
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px 20px' }}>
                                    <Detail label="Status" value={<StatusBadge status={item.status} />} />
                                    <Detail label="Submitted" value={formatDate(item.created_at)} sub={daysAgo(item.created_at) ?? null} />
                                </div>
                            </div>

                            <div style={{ borderBottom: '1px solid var(--border)', paddingBottom: 16, marginBottom: 16, opacity: ['pending_accountant', 'pending_pm', 'rejected'].includes(item.status) ? 0.75 : 1 }}>
                                <div style={{ fontSize: 10.5, fontWeight: 700, color: 'var(--text-muted)', textTransform: 'uppercase', letterSpacing: '0.04em', marginBottom: 10 }}>Lease Duration</div>
                                <div style={{ background: 'var(--bg-elevated)', borderRadius: 9, padding: '13px 14px' }}>
                                    <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 11.5, color: 'var(--text-muted)', marginBottom: 8 }}>
                                        <span>{formatDate(item.start_date)}</span>
                                        <span>{formatDate(item.end_date)}</span>
                                    </div>
                                    <div style={{ height: 8, background: 'var(--bg-base)', borderRadius: 999, overflow: 'hidden' }}>
                                        <div style={{ width: `${['pending_accountant', 'pending_pm', 'rejected'].includes(item.status) ? 0 : leaseProgress.pct}%`, height: '100%', background: leaseProgress.pct >= 90 ? 'var(--red)' : leaseProgress.pct >= 70 ? 'var(--amber)' : 'var(--green)', borderRadius: 999 }} />
                                    </div>
                                    <div style={{ fontSize: 11.5, color: 'var(--text-muted)', marginTop: 8 }}>
                                        {['pending_accountant', 'pending_pm', 'rejected'].includes(item.status)
                                            ? 'Lease not yet active - pending approval'
                                            : `${leaseProgress.pct.toFixed(0)}% elapsed - ${leaseProgress.daysLeft <= 0 ? 'Expired' : `${leaseProgress.daysLeft} days remaining`}`}
                                    </div>
                                </div>
                            </div>

                            <div style={{ borderBottom: '1px solid var(--border)', paddingBottom: 16, marginBottom: 16 }}>
                                <div style={{ fontSize: 10.5, fontWeight: 700, color: 'var(--text-muted)', textTransform: 'uppercase', letterSpacing: '0.04em', marginBottom: 10 }}>Lease Details</div>
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px 20px' }}>
                                    <Detail label="Payment Cycle" value={`${item.payment_cycle ?? '—'} month(s)`} />
                                    <Detail label="Duration" value={`${item.duration_months ?? '—'} months`} />
                                    <Detail label="Monthly Rent" value={fmt(item.monthly_rent, leaseCurrency)} />
                                    <Detail label="Instalment" value={fmt(leaseMoney.instalment, leaseCurrency)} sub={`Every ${item.payment_cycle ?? '—'} month(s)`} />
                                    <Detail label="Annual Value" value={fmt(leaseMoney.annual, leaseCurrency)} sub="Including VAT" />
                                    <Detail label="Security Deposit" value={item.deposit ? fmt(item.deposit, leaseCurrency) : '—'} />
                                    <Detail label="Possession Date" value={formatDate(item.possession_date)} />
                                    <Detail label="Rent Start Date" value={formatDate(item.rent_start_date ?? item.start_date)} />
                                    <Detail label="Lease Start" value={formatDate(item.start_date)} />
                                    <Detail label="Lease End" value={formatDate(item.end_date)} />
                                </div>
                            </div>

                            <div style={{ borderBottom: '1px solid var(--border)', paddingBottom: 16, marginBottom: 16 }}>
                                <div style={{ fontSize: 10.5, fontWeight: 700, color: 'var(--text-muted)', textTransform: 'uppercase', letterSpacing: '0.04em', marginBottom: 10 }}>Charges & Taxes</div>
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px 20px' }}>
                                    <Detail label="Service Charge" value={fmt(leaseMoney.serviceCharge, leaseCurrency)} sub="Unit flat rate" />
                                    <Detail label="Subtotal" value={fmt(leaseMoney.subtotal, leaseCurrency)} />
                                    <Detail label={`VAT (${leaseMoney.vatRate}%)`} value={fmt(leaseMoney.vat, leaseCurrency)} />
                                    <Detail label="Gross Total / Month" value={fmt(leaseMoney.gross, leaseCurrency)} />
                                    <Detail label={`Rent WHT (${leaseMoney.rentWhtRate}%)`} value={fmt(leaseMoney.rentWht, leaseCurrency)} />
                                    <Detail label={`Service Charge WHT (${leaseMoney.serviceChargeWhtRate}%)`} value={fmt(leaseMoney.serviceChargeWht, leaseCurrency)} />
                                    <Detail label="WHT Remittable / Month" value={fmt(leaseMoney.wht, leaseCurrency)} />
                                    <Detail label="Net Payable / Month" value={fmt(leaseMoney.net, leaseCurrency)} />
                                </div>
                            </div>

                            <div style={{ borderBottom: '1px solid var(--border)', paddingBottom: 16, marginBottom: 16 }}>
                                <div style={{ fontSize: 10.5, fontWeight: 700, color: 'var(--text-muted)', textTransform: 'uppercase', letterSpacing: '0.04em', marginBottom: 10 }}>Fit-Out Period</div>
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px 20px' }}>
                                    <Detail label="Fit-Out" value={item.fitout_enabled ? 'Enabled' : 'None'} />
                                    <Detail label="Fit-Out Until" value={item.fitout_enabled ? formatDate(item.fitout_to_date) : '—'} />
                                    <Detail label="Fit-Out Days" value={item.fitout_enabled ? `${item.fitout_days || 0} days` : '—'} />
                                    <Detail label="Service Charge During Fit-Out" value={item.fitout_enabled ? fmt(leaseMoney.fitoutExtraSC, leaseCurrency) : '—'} sub={item.fitout_enabled ? `VAT ${fmt(leaseMoney.fitoutExtraVAT, leaseCurrency)}` : null} />
                                </div>
                            </div>

                            <div style={{ borderBottom: '1px solid var(--border)', paddingBottom: 16, marginBottom: 16 }}>
                                <div style={{ fontSize: 10.5, fontWeight: 700, color: 'var(--text-muted)', textTransform: 'uppercase', letterSpacing: '0.04em', marginBottom: 10 }}>Property & Unit</div>
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px 20px' }}>
                                    <Detail label="Property" value={item.unit?.property?.name ?? '—'} sub={item.unit?.property?.manager?.name ? `Manager: ${item.unit.property.manager.name}` : null} />
                                    <Detail label="Unit" value={item.unit?.unit_number ?? '—'} sub={item.unit?.floor ? `Floor ${item.unit.floor}` : null} />
                                    <Detail label="Type" value={item.unit?.type ?? '—'} />
                                    <Detail label="Electricity" value={item.unit?.electricity_type === 'submeter' ? 'Submeter' : item.unit?.electricity_type === 'direct' ? 'Direct' : '—'} />
                                </div>
                            </div>

                            {leaseSchedule.length > 0 && (
                                <div style={{ borderBottom: '1px solid var(--border)', paddingBottom: 16, marginBottom: 16 }}>
                                    <div style={{ fontSize: 10.5, fontWeight: 700, color: 'var(--text-muted)', textTransform: 'uppercase', letterSpacing: '0.04em', marginBottom: 10 }}>Payment Schedule</div>
                                    <div style={{ border: '1px solid var(--border)', borderRadius: 10, overflow: 'hidden' }}>
                                        <table className="data-table" style={{ margin: 0 }}>
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Due Date</th>
                                                    <th>Period Covered</th>
                                                    <th>Amount</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {leaseSchedule.map((row) => (
                                                    <tr key={row.installNum}>
                                                        <td>{row.installNum}</td>
                                                        <td>{row.dueDate}</td>
                                                        <td>{row.period}</td>
                                                        <td style={{ fontWeight: 600 }}>{fmt(row.amount, leaseCurrency)}</td>
                                                        <td style={{ textTransform: 'capitalize' }}>{String(row.status || 'scheduled').replace('_', ' ')}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            )}

                            <div style={{ borderBottom: '1px solid var(--border)', paddingBottom: 16, marginBottom: 16 }}>
                                <div style={{ fontSize: 10.5, fontWeight: 700, color: 'var(--text-muted)', textTransform: 'uppercase', letterSpacing: '0.04em', marginBottom: 10 }}>Tenant</div>
                                <div style={{ background: 'var(--bg-elevated)', borderRadius: 9, padding: '13px 14px', display: 'flex', alignItems: 'center', gap: 12 }}>
                                    <div style={{ width: 40, height: 40, borderRadius: '50%', background: item.tenant?.color || 'var(--accent-dim)', color: item.tenant?.text_color || 'var(--accent)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 14, fontWeight: 700, flexShrink: 0 }}>{item.tenant?.initials || '—'}</div>
                                    <div>
                                        <div style={{ fontSize: 14, fontWeight: 600 }}>{item.tenant?.name || '—'}</div>
                                        <div style={{ fontSize: 12, color: 'var(--text-muted)' }}>{item.tenant?.email || '—'}</div>
                                        <div style={{ fontSize: 12, color: 'var(--text-muted)' }}>{item.tenant?.phone || '—'}</div>
                                    </div>
                                </div>
                            </div>

                            <div style={{ borderBottom: log.length > 0 ? '1px solid var(--border)' : 'none', paddingBottom: log.length > 0 ? 16 : 0, marginBottom: log.length > 0 ? 16 : 0 }}>
                                <div style={{ fontSize: 10.5, fontWeight: 700, color: 'var(--text-muted)', textTransform: 'uppercase', letterSpacing: '0.04em', marginBottom: 10 }}>Lease Terms</div>
                                <div style={{ background: 'var(--bg-elevated)', borderRadius: 9, padding: '13px 14px', fontSize: 13, color: 'var(--text-secondary)', lineHeight: 1.7, whiteSpace: 'pre-wrap' }}>{item.terms || 'No special lease terms.'}</div>
                            </div>

                            {log.length > 0 && (
                                <div>
                                    <div style={{ fontSize: 10.5, fontWeight: 700, color: 'var(--text-muted)', textTransform: 'uppercase', letterSpacing: '0.04em', marginBottom: 10 }}>Approval History</div>
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

                    {item && isMaintenance && (
                        <>
                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px 20px', marginBottom: 20 }}>
                                <Detail label="Priority" value={<PriorityBadge priority={item.priority} />} />
                                <Detail label="Unit" value={item.unit?.unit_number ?? item.unit_ref ?? '—'} sub={item.unit?.property?.name ?? item.property?.name} />
                                <Detail label="Category" value={item.category ?? '—'} />
                                <Detail label="Reported By" value={item.reported_by ?? '—'} />
                                <Detail label="Reported Date" value={formatDate(item.reported_date ?? item.created_at)} />
                            </div>
                            {item.description && (
                                <div style={{ borderTop: '1px solid var(--border)', paddingTop: 16 }}>
                                    <div style={{ fontSize: 10.5, fontWeight: 700, color: 'var(--text-muted)', textTransform: 'uppercase', letterSpacing: '0.04em', marginBottom: 8 }}>Description</div>
                                    <p style={{ fontSize: 13, color: 'var(--text-secondary)', lineHeight: 1.65, margin: 0, whiteSpace: 'pre-wrap' }}>{item.description}</p>
                                </div>
                            )}
                        </>
                    )}

                    {item && isTeam && (
                        <>
                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px 20px', marginBottom: 20 }}>
                                <Detail label="Status" value={<StatusBadge status={item.status} />} />
                                <Detail label="Name" value={item.name ?? '—'} />
                                <Detail label="Email" value={item.email ?? '—'} />
                                <Detail label="Phone" value={item.phone ?? '—'} />
                                <Detail label="Role" value={String(item.role || '').replace('_', ' ')} />
                                <Detail label="Property" value={item.property?.name ?? '—'} />
                                <Detail label="Requested By" value={item.requested_by?.name ?? '—'} sub={item.requested_by?.email} />
                                <Detail label="Submitted" value={formatDate(item.approval_requested_at ?? item.created_at)} />
                            </div>
                            {item.approval_note && (
                                <div style={{ borderTop: '1px solid var(--border)', paddingTop: 16 }}>
                                    <div style={{ fontSize: 10.5, fontWeight: 700, color: 'var(--text-muted)', textTransform: 'uppercase', letterSpacing: '0.04em', marginBottom: 8 }}>Current Note</div>
                                    <p style={{ fontSize: 13, color: 'var(--text-secondary)', lineHeight: 1.65, margin: 0, whiteSpace: 'pre-wrap' }}>{item.approval_note}</p>
                                </div>
                            )}
                        </>
                    )}

                    {item && isUnit && (
                        <>
                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px 20px', marginBottom: 20 }}>
                                <Detail label="Status" value={<StatusBadge status={item.approval_status} />} />
                                <Detail label="Unit" value={item.unit_number ?? '—'} sub={item.property?.name ?? '—'} />
                                <Detail label="Type" value={item.type ?? '—'} />
                                <Detail label="Floor" value={item.floor ?? '—'} />
                                <Detail label="Monthly Rent" value={fmt(item.rent, item.currency)} />
                                <Detail label="Service Charge" value={fmt(item.service_charge, item.currency)} />
                                <Detail label="Deposit" value={fmt(item.deposit, item.currency)} />
                                <Detail label="Electricity" value={item.electricity_type === 'submeter' ? 'Submeter' : 'Direct'} />
                                <Detail label="Requested By" value={item.requested_by?.name ?? '—'} sub={item.requested_by?.email} />
                                <Detail label="Submitted" value={formatDate(item.approval_requested_at ?? item.created_at)} />
                            </div>
                            {item.notes && (
                                <div style={{ borderTop: '1px solid var(--border)', paddingTop: 16, marginBottom: 16 }}>
                                    <div style={{ fontSize: 10.5, fontWeight: 700, color: 'var(--text-muted)', textTransform: 'uppercase', letterSpacing: '0.04em', marginBottom: 8 }}>Notes</div>
                                    <p style={{ fontSize: 13, color: 'var(--text-secondary)', lineHeight: 1.65, margin: 0, whiteSpace: 'pre-wrap' }}>{item.notes}</p>
                                </div>
                            )}
                            {item.approval_note && (
                                <div style={{ borderTop: '1px solid var(--border)', paddingTop: 16 }}>
                                    <div style={{ fontSize: 10.5, fontWeight: 700, color: 'var(--text-muted)', textTransform: 'uppercase', letterSpacing: '0.04em', marginBottom: 8 }}>Current Note</div>
                                    <p style={{ fontSize: 13, color: 'var(--text-secondary)', lineHeight: 1.65, margin: 0, whiteSpace: 'pre-wrap' }}>{item.approval_note}</p>
                                </div>
                            )}
                        </>
                    )}

                    {item && isInvoice && (
                        <>
                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px 20px', marginBottom: 20 }}>
                                <Detail label="Status" value={<StatusBadge status={item.approval_status} />} />
                                <Detail label="Total" value={fmt(invoiceTotal(item.items), item.currency)} />
                                <Detail label="Tenant" value={item.tenant_name ?? '—'} sub={item.tenant_email ?? '—'} />
                                <Detail label="Property / Unit" value={item.property?.name ?? item.lease?.unit?.property?.name ?? '—'} sub={`Unit ${item.unit_ref ?? '—'}`} />
                                <Detail label="Issued" value={formatDate(item.issued_date)} />
                                <Detail label="Due" value={formatDate(item.due_date)} />
                                <Detail label="Period" value={item.period ?? '—'} />
                                <Detail label="Requested By" value={item.requested_by?.name ?? '—'} sub={item.requested_by?.email ?? '—'} />
                                <Detail label="Lease" value={item.lease?.lease_number ?? 'Manual invoice'} sub={item.lease ? `${fmt(item.lease?.monthly_rent, item.lease?.currency)} · Cycle ${item.lease?.payment_cycle ?? '—'} month(s)` : null} />
                                <Detail label="Submitted" value={formatDate(item.approval_requested_at ?? item.created_at)} />
                            </div>

                            <div style={{ borderTop: '1px solid var(--border)', paddingTop: 16, marginBottom: 16 }}>
                                <div style={{ fontSize: 10.5, fontWeight: 700, color: 'var(--text-muted)', textTransform: 'uppercase', letterSpacing: '0.04em', marginBottom: 8 }}>Line Items</div>
                                <div style={{ border: '1px solid var(--border)', borderRadius: 10, overflow: 'hidden' }}>
                                    <table className="data-table" style={{ margin: 0 }}>
                                        <thead>
                                            <tr>
                                                <th>Description</th>
                                                <th>Qty</th>
                                                <th>Unit Price</th>
                                                <th>Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {(item.items || []).map((line) => (
                                                <tr key={line.id ?? `${line.description}-${line.quantity}-${line.unit_price}`}>
                                                    <td>
                                                        <div style={{ fontWeight: 500 }}>{line.description}</div>
                                                        {line.sub_description && <div style={{ fontSize: 11.5, color: 'var(--text-muted)' }}>{line.sub_description}</div>}
                                                    </td>
                                                    <td>{line.quantity}</td>
                                                    <td>{fmt(line.unit_price, item.currency)}</td>
                                                    <td style={{ fontWeight: 600 }}>{fmt(line.total, item.currency)}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            {item.notes && (
                                <div style={{ borderTop: '1px solid var(--border)', paddingTop: 16, marginBottom: 16 }}>
                                    <div style={{ fontSize: 10.5, fontWeight: 700, color: 'var(--text-muted)', textTransform: 'uppercase', letterSpacing: '0.04em', marginBottom: 8 }}>Notes / Payment Instructions</div>
                                    <p style={{ fontSize: 13, color: 'var(--text-secondary)', lineHeight: 1.65, margin: 0, whiteSpace: 'pre-wrap' }}>{item.notes}</p>
                                </div>
                            )}

                            {item.approval_note && (
                                <div style={{ borderTop: '1px solid var(--border)', paddingTop: 16 }}>
                                    <div style={{ fontSize: 10.5, fontWeight: 700, color: 'var(--text-muted)', textTransform: 'uppercase', letterSpacing: '0.04em', marginBottom: 8 }}>Current Note</div>
                                    <p style={{ fontSize: 13, color: 'var(--text-secondary)', lineHeight: 1.65, margin: 0, whiteSpace: 'pre-wrap' }}>{item.approval_note}</p>
                                </div>
                            )}
                        </>
                    )}
                </div>

                {item && (
                    <div className="drawer-footer">
                        <button type="button" className="btn-danger" style={{ flex: 1 }} disabled={submitting} onClick={() => onReject(type, item)}>✕ Reject</button>
                        <button type="button" className="btn-primary" style={{ flex: 1 }} disabled={submitting} onClick={() => onApprove(type, item)}>✓ Approve</button>
                    </div>
                )}
            </div>
        </div>
    );
}

export default function ApprovalsPage({ pendingLeases = [], pendingMaintenance = [], pendingTeamMembers = [], pendingUnits = [], pendingInvoices = [] }) {
    const [drawer, setDrawer] = useState(null);
    const [modal, setModal] = useState(null);
    const [submitting, setSubmitting] = useState(false);
    const [activeTab, setActiveTab] = useState(() => {
        if (pendingInvoices.length > 0) return 'invoices';
        if (pendingUnits.length > 0) return 'units';
        if (pendingTeamMembers.length > 0) return 'team';
        if (pendingLeases.length > 0) return 'leases';
        return 'maintenance';
    });

    const openDrawer = (type, item) => setDrawer({ type, item });
    const closeDrawer = () => { if (!submitting) setDrawer(null); };
    const openModal = (action, type, item) => setModal({ action, type, item });
    const closeModal = () => { if (!submitting) setModal(null); };

    const handleConfirm = (message) => {
        if (!modal || submitting) return;
        setSubmitting(true);
        const { action, type, item } = modal;
        const url = type === 'lease'
            ? `/superuser/leases/${item.id}/${action}`
            : type === 'maintenance'
                ? `/superuser/maintenance/${item.id}/${action}`
                : type === 'team'
                    ? `/superuser/team/${item.id}/${action}`
                    : type === 'unit'
                        ? `/superuser/units/${item.id}/${action}`
                        : `/superuser/invoices/${item.id}/${action}`;

        router.post(url, { message }, {
            preserveScroll: true,
            onSuccess: () => { setModal(null); setDrawer(null); setSubmitting(false); },
            onError: () => { setSubmitting(false); },
        });
    };

    const totalPending = pendingLeases.length + pendingMaintenance.length + pendingTeamMembers.length + pendingUnits.length + pendingInvoices.length;
    const tabs = [
        { key: 'invoices', label: 'Invoices', count: pendingInvoices.length },
        { key: 'units', label: 'Units', count: pendingUnits.length },
        { key: 'team', label: 'Team', count: pendingTeamMembers.length },
        { key: 'leases', label: 'Leases', count: pendingLeases.length },
        { key: 'maintenance', label: 'Maintenance', count: pendingMaintenance.length },
    ];

    const modalSubject = modal ? (modal.type === 'lease' ? 'Lease' : modal.type === 'maintenance' ? 'Maintenance Ticket' : modal.type === 'team' ? 'Team Member' : modal.type === 'unit' ? 'Unit' : 'Invoice') : '';
    const modalContext = modal ? (() => {
        const item = modal.item;
        if (modal.type === 'lease') {
            return `lease ${item.lease_number ?? `Lease #${item.id}`} for ${item.tenant?.name ?? 'the tenant'}`;
        }
        if (modal.type === 'maintenance') {
            return `ticket ${item.ticket_number ?? `TK #${item.id}`} — "${item.title}"`;
        }
        if (modal.type === 'team') return `team request for ${item.name} (${item.email})`;
        if (modal.type === 'unit') return `unit request for ${item.unit_number} at ${item.property?.name ?? 'the property'}`;
        return `proforma invoice ${item.invoice_number} for ${item.tenant_name ?? 'the tenant'}`;
    })() : '';

    const renderEmptyState = (message) => (
        <div style={{ background: 'var(--bg-surface)', border: '1px solid var(--border)', borderRadius: 12, padding: '32px 20px', textAlign: 'center', color: 'var(--text-muted)', fontSize: 13 }}>
            {message}
        </div>
    );

    const renderTeamTable = () => {
        if (pendingTeamMembers.length === 0) {
            return renderEmptyState('No team requests pending approval');
        }

        return (
            <div className="card">
                <table className="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Property</th>
                            <th>Requested By</th>
                            <th>Submitted</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {pendingTeamMembers.map((member) => (
                            <tr key={member.id} style={{ cursor: 'pointer' }} onClick={() => openDrawer('team', member)}>
                                <td>
                                    <div style={{ fontWeight: 500 }}>{member.name}</div>
                                    <div style={{ fontSize: 11.5, color: 'var(--text-muted)' }}>{member.email}</div>
                                </td>
                                <td style={{ textTransform: 'capitalize' }}>{String(member.role || '').replace('_', ' ')}</td>
                                <td>{member.property?.name ?? '—'}</td>
                                <td>{member.requested_by?.name ?? '—'}</td>
                                <td style={{ color: 'var(--text-muted)', fontSize: 12.5 }}>{daysAgo(member.approval_requested_at ?? member.created_at) ?? '—'}</td>
                                <td style={{ color: 'var(--text-muted)', fontSize: 16, textAlign: 'right' }}>›</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        );
    };

    const renderUnitTable = () => {
        if (pendingUnits.length === 0) {
            return renderEmptyState('No unit requests pending approval');
        }

        return (
            <div className="card">
                <table className="data-table">
                    <thead>
                        <tr>
                            <th>Unit</th>
                            <th>Type</th>
                            <th>Property</th>
                            <th>Rent</th>
                            <th>Requested By</th>
                            <th>Submitted</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {pendingUnits.map((unit) => (
                            <tr key={unit.id} style={{ cursor: 'pointer' }} onClick={() => openDrawer('unit', unit)}>
                                <td><div style={{ fontWeight: 600 }}>{unit.unit_number}</div><div style={{ fontSize: 11.5, color: 'var(--text-muted)' }}>{unit.floor}</div></td>
                                <td>{unit.type}</td>
                                <td>{unit.property?.name ?? '—'}</td>
                                <td>{fmt(unit.rent, unit.currency)}</td>
                                <td><div style={{ fontWeight: 500 }}>{unit.requested_by?.name ?? '—'}</div><div style={{ fontSize: 11.5, color: 'var(--text-muted)' }}>{unit.requested_by?.email}</div></td>
                                <td style={{ color: 'var(--text-muted)', fontSize: 12.5 }}>{daysAgo(unit.approval_requested_at ?? unit.created_at) ?? '—'}</td>
                                <td style={{ color: 'var(--text-muted)', fontSize: 16, textAlign: 'right' }}>›</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        );
    };

    const renderInvoiceTable = () => {
        if (pendingInvoices.length === 0) {
            return renderEmptyState('No proforma invoices pending approval');
        }

        return (
            <div className="card">
                <table className="data-table">
                    <thead>
                        <tr>
                            <th>Invoice</th>
                            <th>Tenant</th>
                            <th>Property / Unit</th>
                            <th>Total</th>
                            <th>Requested By</th>
                            <th>Submitted</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {pendingInvoices.map((invoice) => (
                            <tr key={invoice.id} style={{ cursor: 'pointer' }} onClick={() => openDrawer('invoice', invoice)}>
                                <td>
                                    <div style={{ fontWeight: 600 }}>{invoice.invoice_number}</div>
                                    <div style={{ fontSize: 11.5, color: 'var(--text-muted)' }}><StatusBadge status={invoice.approval_status} /></div>
                                </td>
                                <td>
                                    <div style={{ fontWeight: 500 }}>{invoice.tenant_name}</div>
                                    <div style={{ fontSize: 11.5, color: 'var(--text-muted)' }}>{invoice.tenant_email ?? '—'}</div>
                                </td>
                                <td>
                                    <div>{invoice.property?.name ?? invoice.lease?.unit?.property?.name ?? '—'}</div>
                                    <div style={{ fontSize: 11.5, color: 'var(--text-muted)' }}>Unit {invoice.unit_ref ?? '—'}</div>
                                </td>
                                <td>{fmt(invoiceTotal(invoice.items), invoice.currency)}</td>
                                <td>
                                    <div style={{ fontWeight: 500 }}>{invoice.requested_by?.name ?? '—'}</div>
                                    <div style={{ fontSize: 11.5, color: 'var(--text-muted)' }}>{invoice.requested_by?.email ?? '—'}</div>
                                </td>
                                <td style={{ color: 'var(--text-muted)', fontSize: 12.5 }}>{daysAgo(invoice.approval_requested_at ?? invoice.created_at) ?? '—'}</td>
                                <td style={{ color: 'var(--text-muted)', fontSize: 16, textAlign: 'right' }}>›</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        );
    };

    const renderLeaseTable = () => {
        if (pendingLeases.length === 0) {
            return renderEmptyState('No leases pending approval');
        }

        return (
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
                            const unit = lease.unit?.unit_number ?? '—';
                            const property = lease.unit?.property?.name ?? '—';
                            return (
                                <tr key={lease.id} style={{ cursor: 'pointer' }} onClick={() => openDrawer('lease', lease)}>
                                    <td style={{ fontWeight: 600 }}>{lease.lease_number ?? `Lease #${lease.id}`}</td>
                                    <td><div style={{ fontWeight: 500 }}>{lease.tenant?.name ?? '—'}</div><div style={{ fontSize: 11.5, color: 'var(--text-muted)' }}>{lease.tenant?.email}</div></td>
                                    <td><div>{property}</div><div style={{ fontSize: 11.5, color: 'var(--text-muted)' }}>Unit {unit}</div></td>
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
        );
    };

    const renderMaintenanceTable = () => {
        if (pendingMaintenance.length === 0) {
            return renderEmptyState('No maintenance tickets pending approval');
        }

        return (
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
                            const unit = ticket.unit?.unit_number ?? ticket.unit_ref ?? '—';
                            const property = ticket.unit?.property?.name ?? ticket.property?.name ?? '—';
                            return (
                                <tr key={ticket.id} style={{ cursor: 'pointer' }} onClick={() => openDrawer('maintenance', ticket)}>
                                    <td style={{ fontWeight: 600, fontSize: 12.5, color: 'var(--text-muted)' }}>{ticket.ticket_number ?? `TK #${ticket.id}`}</td>
                                    <td style={{ fontWeight: 500 }}>{ticket.title}</td>
                                    <td><div>{property}</div><div style={{ fontSize: 11.5, color: 'var(--text-muted)' }}>Unit {unit}</div></td>
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
        );
    };

    return (
        <>
            <DetailDrawer drawer={drawer} onClose={closeDrawer} onApprove={(type, item) => openModal('approve', type, item)} onReject={(type, item) => openModal('reject', type, item)} submitting={submitting} />
            <DecisionModal open={!!modal} action={modal?.action} subject={modalSubject} context={modalContext} onClose={closeModal} onConfirm={handleConfirm} submitting={submitting} />

            <div className="page-header">
                <div>
                    <div className="page-title">Pending Approvals</div>
                    <div className="page-sub">
                        {totalPending === 0 ? 'All caught up — no pending requests' : `${totalPending} request${totalPending !== 1 ? 's' : ''} awaiting your decision`}
                    </div>
                </div>
            </div>

            <div className="grid-3" style={{ marginBottom: 20 }}>
                <div style={{ background: 'var(--bg-surface)', border: '1px solid var(--border)', borderRadius: 13, padding: '16px 18px' }}>
                <div style={{ fontSize: 12, color: 'var(--text-muted)', marginBottom: 5 }}>Total Pending</div>
                <div style={{ fontSize: 28, fontWeight: 800 }}>{totalPending}</div>
            </div>
            <div style={{ background: 'var(--bg-surface)', border: '1px solid var(--border)', borderRadius: 13, padding: '16px 18px' }}>
                <div style={{ fontSize: 12, color: 'var(--text-muted)', marginBottom: 5 }}>Pending Invoices</div>
                <div style={{ fontSize: 28, fontWeight: 800, color: 'var(--accent)' }}>{pendingInvoices.length}</div>
            </div>
            <div style={{ background: 'var(--bg-surface)', border: '1px solid var(--border)', borderRadius: 13, padding: '16px 18px' }}>
                <div style={{ fontSize: 12, color: 'var(--text-muted)', marginBottom: 5 }}>Pending Leases</div>
                <div style={{ fontSize: 28, fontWeight: 800, color: 'var(--amber)' }}>{pendingLeases.length}</div>
            </div>
        </div>

            <div className="team-tabs" style={{ marginBottom: 18, display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                {tabs.map((tab) => (
                    <button
                        key={tab.key}
                        type="button"
                        className={`filter-pill ${activeTab === tab.key ? 'active' : ''}`}
                        onClick={() => setActiveTab(tab.key)}
                    >
                        {tab.label} <span className="pill-count">{tab.count}</span>
                    </button>
                ))}
            </div>

            <div style={{ marginBottom: 12, fontSize: 13, fontWeight: 700, color: 'var(--text-secondary)', textTransform: 'uppercase', letterSpacing: '0.05em' }}>
                {activeTab === 'invoices'
                    ? `Invoice Approvals · ${pendingInvoices.length}`
                    : activeTab === 'units'
                    ? `Unit Approvals · ${pendingUnits.length}`
                    : activeTab === 'team'
                        ? `Team Approvals · ${pendingTeamMembers.length}`
                        : activeTab === 'leases'
                            ? `Lease Approvals · ${pendingLeases.length}`
                            : `Maintenance Approvals · ${pendingMaintenance.length}`}
            </div>

            {activeTab === 'invoices' && renderInvoiceTable()}
            {activeTab === 'units' && renderUnitTable()}
            {activeTab === 'team' && renderTeamTable()}
            {activeTab === 'leases' && renderLeaseTable()}
            {activeTab === 'maintenance' && renderMaintenanceTable()}
        </>
    );
}
