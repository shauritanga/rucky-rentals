import { useMemo, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm } from '@inertiajs/react';
import useExchangeRate from '@/hooks/useExchangeRate';

const invoiceTotal = (inv) => (inv.items || []).reduce((sum, item) => sum + Number(item.total || 0), 0);

const toInitials = (name = '') =>
  String(name)
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join('') || 'T';

const PAY_STATUS = {
  paid: { label: 'Paid', color: 'var(--green)', bg: 'var(--green-dim)' },
  overpaid: { label: 'Overpaid', color: '#a78bfa', bg: 'rgba(167,139,250,.12)' },
  partially_paid: { label: 'Partial', color: 'var(--amber)', bg: 'var(--amber-dim)' },
  overdue: { label: 'Overdue', color: 'var(--red)', bg: 'var(--red-dim)' },
  pending: { label: 'Pending', color: 'var(--text-muted)', bg: 'var(--bg-elevated)' },
};

const fmtVariance = (amount, formatter) => {
  if (amount === 0) return '—';
  if (amount > 0) return `+${formatter(amount)}`;
  return `(${formatter(Math.abs(amount))})`;
};

export default function PaymentsIndex({ payments, invoices = [], tenants, units }) {
  const { formatTzsFromUsd, formatCompactTzsFromUsd } = useExchangeRate();

  const [activeTab, setActiveTab] = useState('pay-ledger');
  const [filter, setFilter] = useState('all');
  const [search, setSearch] = useState('');
  const [month, setMonth] = useState('');
  const [showModal, setShowModal] = useState(false);
  const [selectedInvoiceId, setSelectedInvoiceId] = useState('');
  const [submitMessage, setSubmitMessage] = useState({ type: '', text: '' });
  const [submitError, setSubmitError] = useState('');

  const { data, setData, post, processing, reset, errors, clearErrors } = useForm({
    invoice_id: '',
    tenant_id: '',
    unit_id: '',
    month: 'Mar 2026',
    amount: '',
    method: 'Bank Transfer',
    status: 'paid',
    paid_date: '',
    reference: '',
    notes: '',
  });

  const invoiceLedger = useMemo(() => {
    return invoices
      .filter((inv) => inv.type !== 'proforma')
      .map((inv) => {
        const total = invoiceTotal(inv);
        const linkedPayments = payments.filter((p) => {
          if (p.invoice_id && Number(p.invoice_id) === Number(inv.id)) return true;
          return (
            Number(p.tenant_id) === Number(inv.tenant_id) &&
            Number(p.unit_id) === Number(inv.unit_id) &&
            String(p.month || '') === String(inv.period || '')
          );
        });

        const paidAmount = linkedPayments
          .filter((p) => p.status === 'paid')
          .reduce((sum, p) => sum + Number(p.amount || 0), 0);

        const variance = paidAmount - total;
        let status = inv.status || 'pending';
        if (variance > 0) status = 'overpaid';
        else if (variance < 0 && paidAmount > 0) status = 'partially_paid';
        else if (paidAmount > 0 && variance === 0) status = 'paid';

        const tenant = tenants.find((t) => Number(t.id) === Number(inv.tenant_id)) || null;
        const unit = units.find((u) => Number(u.id) === Number(inv.unit_id)) || null;

        return {
          id: `inv-${inv.id}`,
          invoiceId: inv.id,
          invoiceNumber: inv.invoice_number || `INV-${inv.id}`,
          tenant,
          unit,
          month: inv.period || '-',
          invoiceAmount: total,
          amountPaid: paidAmount,
          variance,
          method: linkedPayments[0]?.method || '—',
          status,
          date: linkedPayments[0]?.paid_date || inv.issued_date || '—',
          dueDate: inv.due_date,
        };
      });
  }, [invoices, payments, tenants, units]);

  const standalonePayments = useMemo(() => {
    return payments
      .filter((p) => !p.invoice_id)
      .map((p) => ({
        id: `p-${p.id}`,
        invoiceId: null,
        invoiceNumber: '—',
        tenant: p.tenant,
        unit: p.unit,
        month: p.month || '-',
        invoiceAmount: Number(p.amount || 0),
        amountPaid: Number(p.amount || 0),
        variance: 0,
        method: p.method || '—',
        status: p.status || 'paid',
        date: p.paid_date || '—',
        dueDate: p.paid_date,
      }));
  }, [payments]);

  const ledgerRows = [...invoiceLedger, ...standalonePayments];

  const filteredRows = useMemo(() => {
    const q = search.trim().toLowerCase();
    return ledgerRows.filter((row) => {
      const matchFilter = filter === 'all' || row.status === filter;
      const matchMonth = !month || row.month === month;
      const matchSearch =
        !q ||
        row.tenant?.name?.toLowerCase().includes(q) ||
        row.unit?.unit_number?.toLowerCase().includes(q) ||
        String(row.invoiceNumber || '').toLowerCase().includes(q);
      return matchFilter && matchMonth && matchSearch;
    });
  }, [ledgerRows, filter, month, search]);

  const filterCounts = useMemo(() => {
    const counts = {
      all: ledgerRows.length,
      paid: 0,
      overpaid: 0,
      partially_paid: 0,
      overdue: 0,
      pending: 0,
    };
    ledgerRows.forEach((row) => {
      if (counts[row.status] !== undefined) counts[row.status] += 1;
    });
    return counts;
  }, [ledgerRows]);

  const thisMonth = useMemo(() => ledgerRows.filter((r) => r.month === 'Mar 2026'), [ledgerRows]);

  const shortfall = ledgerRows
    .filter((r) => r.status === 'partially_paid')
    .reduce((sum, r) => sum + Math.abs(Math.min(r.variance, 0)), 0);

  const partialCt = ledgerRows.filter((r) => r.status === 'partially_paid').length;
  const overdueAmt = ledgerRows
    .filter((r) => r.status === 'overdue')
    .reduce((sum, r) => sum + Math.max(r.invoiceAmount - r.amountPaid, 0), 0);
  const overdueCt = ledgerRows.filter((r) => r.status === 'overdue').length;
  const collected = thisMonth
    .filter((r) => ['paid', 'overpaid', 'partially_paid'].includes(r.status))
    .reduce((sum, r) => sum + r.amountPaid, 0);
  const paidCt = thisMonth.filter((r) => ['paid', 'overpaid'].includes(r.status)).length;
  const collectionRate = thisMonth.length ? Math.round((paidCt / thisMonth.length) * 100) : 0;

  const creditsByTenant = useMemo(() => {
    const map = new Map();
    invoiceLedger.forEach((row) => {
      if (row.variance <= 0 || !row.tenant?.id) return;
      const key = row.tenant.id;
      const current = map.get(key) || {
        tenant: row.tenant,
        unit: row.unit,
        amount: 0,
      };
      current.amount += row.variance;
      map.set(key, current);
    });
    return Array.from(map.values());
  }, [invoiceLedger]);

  const totalCredits = creditsByTenant.reduce((sum, c) => sum + c.amount, 0);
  const creditCt = creditsByTenant.length;

  const creditHistory = useMemo(() => {
    return invoiceLedger
      .filter((row) => row.variance > 0)
      .map((row) => ({
        id: row.id,
        tenant: row.tenant,
        unit: row.unit,
        amount: row.variance,
        date: row.date,
        desc: `Overpayment on ${row.invoiceNumber}`,
        type: 'credit',
      }));
  }, [invoiceLedger]);

  const eligibleInvoices = invoices.filter((i) => ['unpaid', 'overdue', 'partially_paid'].includes(i.status) && i.type !== 'proforma');
  const selectedInvoice = eligibleInvoices.find((inv) => String(inv.id) === String(selectedInvoiceId));

  const selectedTenantCredit = useMemo(() => {
    if (!selectedInvoice) return 0;
    const credit = creditsByTenant.find((c) => Number(c.tenant?.id) === Number(selectedInvoice.tenant_id));
    return Number(credit?.amount || 0);
  }, [selectedInvoice, creditsByTenant]);

  const selectedInvoiceNet = selectedInvoice ? invoiceTotal(selectedInvoice) : 0;
  const selectedBalanceDue = Math.max(0, selectedInvoiceNet - selectedTenantCredit);
  const received = Number(data.amount || 0);
  const reconcileVariance = received - selectedBalanceDue;

  const canSubmit = Boolean(selectedInvoiceId && received > 0 && data.paid_date);

  const openRecordPaymentModal = () => {
    const today = new Date().toISOString().split('T')[0];
    setSubmitError('');
    clearErrors();
    setShowModal(true);
    setSelectedInvoiceId('');
    reset();
    setData('method', 'Bank Transfer');
    setData('paid_date', today);
    setData('status', 'paid');
  };

  const onSelectInvoice = (invoiceId) => {
    setSelectedInvoiceId(invoiceId);
    setData('invoice_id', invoiceId || '');
    if (!invoiceId) return;

    const inv = eligibleInvoices.find((x) => String(x.id) === String(invoiceId));
    if (!inv) return;

    const tenantCredit = creditsByTenant.find((c) => Number(c.tenant?.id) === Number(inv.tenant_id));
    const due = Math.max(invoiceTotal(inv) - Number(tenantCredit?.amount || 0), 0);

    setData('tenant_id', String(inv.tenant_id || ''));
    setData('unit_id', String(inv.unit_id || ''));
    setData('month', String(inv.period || 'Mar 2026'));
    setData('amount', due ? String(due) : String(invoiceTotal(inv)));
    setData('reference', inv.invoice_number || '');
  };

  const submit = (e) => {
    e.preventDefault();
    if (!canSubmit) return;

    setSubmitError('');
    setSubmitMessage({ type: '', text: '' });

    post('/payments', {
      data: {
        ...data,
        status: 'paid',
      },
      preserveScroll: true,
      onSuccess: () => {
        setSubmitMessage({ type: 'success', text: 'Payment recorded successfully.' });
        reset();
        setSelectedInvoiceId('');
        setShowModal(false);
      },
      onError: (formErrors) => {
        const firstError = Object.values(formErrors || {})
          .flat()
          .find((value) => typeof value === 'string' && value.trim().length > 0);
        setSubmitError(firstError || 'Payment failed. Please review the form and try again.');
      },
    });
  };

  return (
    <AppLayout title="Payments" subtitle="Mar 2026">
      <Head title="Payments" />

      <div className="stats-grid" style={{ marginBottom: 20 }}>
        <div className="stat-card">
          <div className="stat-top">
            <div className="stat-icon green"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="20 6 9 17 4 12" /></svg></div>
            <span className="stat-delta up">{collectionRate}% collection rate</span>
          </div>
          <div className="stat-value">{formatCompactTzsFromUsd(collected)}</div>
          <div className="stat-label">Collected This Month</div>
        </div>

        <div className="stat-card">
          <div className="stat-top">
            <div className="stat-icon red"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" /></svg></div>
            <span className="stat-delta down">{overdueCt} tenant{overdueCt !== 1 ? 's' : ''}</span>
          </div>
          <div className="stat-value">{formatCompactTzsFromUsd(overdueAmt)}</div>
          <div className="stat-label">Overdue Balance</div>
        </div>

        <div className="stat-card">
          <div className="stat-top">
            <div className="stat-icon amber"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="12" y1="1" x2="12" y2="23" /><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6" /></svg></div>
            <span className="stat-delta">{partialCt} partial</span>
          </div>
          <div className="stat-value">{formatCompactTzsFromUsd(shortfall)}</div>
          <div className="stat-label">Outstanding Shortfall</div>
        </div>

        <div className="stat-card">
          <div className="stat-top">
            <div className="stat-icon green"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="5" /><path d="M12 1v4M12 19v4M4.22 4.22l2.83 2.83M16.95 16.95l2.83 2.83M1 12h4M19 12h4M4.22 19.78l2.83-2.83M16.95 7.05l2.83-2.83" /></svg></div>
            <span className="stat-delta up">{creditCt} tenant{creditCt !== 1 ? 's' : ''}</span>
          </div>
          <div className="stat-value">{formatCompactTzsFromUsd(totalCredits)}</div>
          <div className="stat-label">Credits on Account</div>
        </div>
      </div>

      <div style={{ display: 'flex', gap: 0, background: 'var(--bg-elevated)', border: '1px solid var(--border)', borderRadius: 11, padding: 3, marginBottom: 18, width: 'fit-content' }}>
        <button className={`prof-tab ${activeTab === 'pay-ledger' ? 'active' : ''}`} onClick={() => setActiveTab('pay-ledger')} style={{ padding: '7px 18px' }}>Payment Ledger</button>
        <button className={`prof-tab ${activeTab === 'pay-credits' ? 'active' : ''}`} onClick={() => setActiveTab('pay-credits')} style={{ padding: '7px 18px' }}>Tenant Credits</button>
      </div>

      {submitMessage.type === 'success' && (
        <div style={{ marginBottom: 14, background: 'var(--green-dim)', border: '1px solid var(--green)', color: 'var(--green)', borderRadius: 10, padding: '10px 12px', fontSize: 13, fontWeight: 600 }}>
          {submitMessage.text}
        </div>
      )}

      {activeTab === 'pay-ledger' && (
        <>
          <div className="toolbar" style={{ marginBottom: 14 }}>
            <div className="filters">
              {[
                ['all', 'All'],
                ['paid', 'Paid'],
                ['overpaid', 'Overpaid'],
                ['partially_paid', 'Partial'],
                ['overdue', 'Overdue'],
                ['pending', 'Pending'],
              ].map(([key, label]) => (
                <button key={key} className={`filter-pill ${filter === key ? 'active' : ''}`} onClick={() => setFilter(key)}>
                  {label} <span className="pill-count">{filterCounts[key] || 0}</span>
                </button>
              ))}
            </div>
            <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
              <div className="search-box" style={{ width: 190 }}>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="11" cy="11" r="8" /><path d="m21 21-4.35-4.35" /></svg>
                <input type="text" placeholder="Search payments..." value={search} onChange={(e) => setSearch(e.target.value)} />
              </div>
              <select className="form-input form-select" value={month} onChange={(e) => setMonth(e.target.value)} style={{ width: 140, padding: '6px 28px 6px 10px', fontSize: '12.5px' }}>
                <option value="">All Months</option>
                <option value="Mar 2026">March 2026</option>
                <option value="Feb 2026">February 2026</option>
                <option value="Jan 2026">January 2026</option>
              </select>
              <button className="btn-primary" style={{ whiteSpace: 'nowrap' }} onClick={openRecordPaymentModal}>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><line x1="12" y1="5" x2="12" y2="19" /><line x1="5" y1="12" x2="19" y2="12" /></svg>
                Record Payment
              </button>
            </div>
          </div>

          <div className="card" style={{ overflow: 'hidden' }}>
            <table className="ledger-table">
              <thead><tr><th style={{ paddingLeft: 20 }}>Tenant</th><th>Unit</th><th>Invoice</th><th>Invoice Amount</th><th>Amount Paid</th><th>Variance</th><th>Method</th><th>Status</th><th>Date</th></tr></thead>
              <tbody>
                {filteredRows.length === 0 ? (
                  <tr><td colSpan={9} style={{ textAlign: 'center', padding: 40, color: 'var(--text-muted)' }}>No payments found</td></tr>
                ) : (
                  filteredRows.map((row) => {
                    const statusConfig = PAY_STATUS[row.status] || PAY_STATUS.pending;
                    const varianceColor = row.variance > 0 ? 'var(--green)' : row.variance < 0 ? 'var(--red)' : 'var(--text-muted)';
                    return (
                      <tr key={row.id}>
                        <td>
                          <div className="tenant-cell">
                            <div className="t-avatar">{row.tenant?.initials || toInitials(row.tenant?.name)}</div>
                            <div>
                              <div style={{ fontWeight: 600 }}>{row.tenant?.name || '—'}</div>
                              <div style={{ fontSize: '11.5px', color: 'var(--text-muted)' }}>{row.invoiceNumber || row.id} · {row.month}</div>
                            </div>
                          </div>
                        </td>
                        <td style={{ fontWeight: 600 }}>{row.unit?.unit_number || '—'}</td>
                        <td style={{ fontSize: '12.5px', color: 'var(--accent)', fontWeight: 500 }}>{row.invoiceNumber || '—'}</td>
                        <td style={{ fontVariantNumeric: 'tabular-nums' }}>{formatTzsFromUsd(row.invoiceAmount)}</td>
                        <td style={{ fontWeight: 700, fontVariantNumeric: 'tabular-nums' }}>{formatTzsFromUsd(row.amountPaid)}</td>
                        <td style={{ fontWeight: 600, color: varianceColor, fontVariantNumeric: 'tabular-nums' }}>{fmtVariance(row.variance, formatTzsFromUsd)}</td>
                        <td style={{ fontSize: '12.5px', color: 'var(--text-secondary)' }}>{row.method || '—'}</td>
                        <td>
                          <span style={{ fontSize: 12, fontWeight: 600, padding: '2px 9px', borderRadius: 20, background: statusConfig.bg, color: statusConfig.color }}>
                            {statusConfig.label}
                            {row.status === 'partially_paid' ? ` — ${formatCompactTzsFromUsd(Math.abs(row.variance))} short` : ''}
                          </span>
                        </td>
                        <td style={{ color: 'var(--text-muted)', fontSize: 12 }}>{row.date || row.dueDate || '—'}</td>
                      </tr>
                    );
                  })
                )}
              </tbody>
            </table>
          </div>
        </>
      )}

      {activeTab === 'pay-credits' && (
        <>
          <div style={{ fontSize: 13, color: 'var(--text-muted)', marginBottom: 16 }}>Credits are overpayments sitting on a tenant account. They are automatically applied when the next invoice is recorded.</div>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16 }}>
            <div className="card">
              <div className="card-header">
                <div>
                  <div className="card-title">Credit Balances</div>
                  <div className="card-sub">Tenants with outstanding credits</div>
                </div>
              </div>
              <div style={{ padding: '0 8px 8px' }}>
                {creditsByTenant.length === 0 ? (
                  <div style={{ padding: 24, textAlign: 'center', fontSize: 13, color: 'var(--text-muted)' }}>No credit balances on account</div>
                ) : (
                  creditsByTenant.map((entry) => (
                    <div key={entry.tenant.id} style={{ display: 'flex', alignItems: 'center', gap: 10, padding: 12, borderBottom: '1px solid var(--border-subtle)' }}>
                      <div className="t-avatar" style={{ width: 32, height: 32, fontSize: 12 }}>{entry.tenant.initials || toInitials(entry.tenant.name)}</div>
                      <div style={{ flex: 1 }}>
                        <div style={{ fontWeight: 600, fontSize: 13 }}>{entry.tenant.name}</div>
                        <div style={{ fontSize: '11.5px', color: 'var(--text-muted)' }}>{entry.unit?.unit_number || '—'} · Credit from overpayment</div>
                      </div>
                      <div style={{ textAlign: 'right' }}>
                        <div style={{ fontSize: 16, fontWeight: 700, color: 'var(--green)' }}>{formatTzsFromUsd(entry.amount)}</div>
                        <div style={{ fontSize: 11, color: 'var(--text-muted)' }}>will apply to next invoice</div>
                      </div>
                    </div>
                  ))
                )}
              </div>
            </div>

            <div className="card">
              <div className="card-header">
                <div>
                  <div className="card-title">Credit History</div>
                  <div className="card-sub">How credits were created and applied</div>
                </div>
              </div>
              <div style={{ padding: '0 8px 8px' }}>
                {creditHistory.length === 0 ? (
                  <div style={{ padding: 24, textAlign: 'center', fontSize: 13, color: 'var(--text-muted)' }}>No credit events yet</div>
                ) : (
                  creditHistory.map((entry) => (
                    <div key={entry.id} style={{ padding: 12, borderBottom: '1px solid var(--border-subtle)' }}>
                      <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 3 }}>
                        <div style={{ fontSize: 13, fontWeight: 500 }}>{entry.tenant?.name || '—'}</div>
                        <div style={{ fontSize: 13, fontWeight: 700, color: 'var(--green)' }}>+{formatTzsFromUsd(entry.amount)}</div>
                      </div>
                      <div style={{ fontSize: 12, color: 'var(--text-muted)' }}>{entry.desc} · {entry.date || '—'}</div>
                    </div>
                  ))
                )}
              </div>
            </div>
          </div>
        </>
      )}

      <div className={`modal-overlay ${showModal ? 'open' : ''}`} style={{ alignItems: 'flex-start', padding: '12px 0', overflowY: 'auto' }} onClick={(e) => e.target === e.currentTarget && !processing && setShowModal(false)}>
        <div className="modal" style={{ width: 560, maxHeight: 'calc(100vh - 24px)', display: 'flex', flexDirection: 'column', margin: '0 auto' }}>
          <div className="modal-header" style={{ flexShrink: 0 }}><div className="modal-title">Record Payment</div><button className="modal-close" onClick={() => setShowModal(false)}>X</button></div>
          <form onSubmit={submit} style={{ display: 'flex', flexDirection: 'column', minHeight: 0 }}>
            <div className="modal-body" style={{ flex: 1, overflowY: 'auto', WebkitOverflowScrolling: 'touch' }}>
              {submitError && (
                <div style={{ marginBottom: 12, background: 'var(--red-dim)', border: '1px solid var(--red)', color: 'var(--red)', borderRadius: 9, padding: '9px 11px', fontSize: 13, fontWeight: 600 }}>
                  {submitError}
                </div>
              )}

              <div className="form-row">
                <div className="form-group">
                  <label className="form-label">Invoice *</label>
                  <select className="form-input form-select" value={selectedInvoiceId} onChange={(e) => onSelectInvoice(e.target.value)} required>
                    <option value="">Select invoice...</option>
                    {eligibleInvoices.map((inv) => (
                      <option key={inv.id} value={inv.id}>{`${inv.invoice_number} — ${inv.tenant_name} (${inv.unit_ref || 'Unit'}) — ${formatTzsFromUsd(invoiceTotal(inv))}`}</option>
                    ))}
                  </select>
                  {errors.invoice_id && <div style={{ marginTop: 5, fontSize: 12, color: 'var(--red)' }}>{errors.invoice_id}</div>}
                </div>
                <div className="form-group">
                  <label className="form-label">Payment Method *</label>
                  <select className="form-input form-select" value={data.method} onChange={(e) => setData('method', e.target.value)}>
                    <option value="Bank Transfer">Bank Transfer</option>
                    <option value="Mobile Money">Mobile Money</option>
                    <option value="Cash">Cash</option>
                    <option value="Cheque">Cheque</option>
                  </select>
                  {errors.method && <div style={{ marginTop: 5, fontSize: 12, color: 'var(--red)' }}>{errors.method}</div>}
                </div>
              </div>

              {selectedInvoice && (
                <div style={{ background: 'var(--bg-elevated)', border: '1px solid var(--border)', borderRadius: 10, padding: '14px 16px', marginBottom: 16 }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 8 }}>
                    <div>
                      <div style={{ fontSize: 13, fontWeight: 700 }}>{selectedInvoice.invoice_number} — {selectedInvoice.tenant_name}</div>
                      <div style={{ fontSize: 12, color: 'var(--text-muted)', marginTop: 2 }}>Unit {selectedInvoice.unit_ref || '—'} · {selectedInvoice.period || '—'}</div>
                    </div>
                    <div style={{ textAlign: 'right' }}>
                      <div style={{ fontSize: 11, color: 'var(--text-muted)' }}>Net Payable</div>
                      <div style={{ fontSize: 18, fontWeight: 700, color: 'var(--accent)' }}>{formatTzsFromUsd(selectedInvoiceNet)}</div>
                    </div>
                  </div>
                  {selectedTenantCredit > 0 && (
                    <div style={{ background: 'var(--green-dim)', border: '1px solid var(--green)', borderRadius: 8, padding: '10px 12px', fontSize: 13 }}>
                      <span style={{ color: 'var(--green)', fontWeight: 600 }}>Credit on account: </span>
                      <span>{formatTzsFromUsd(selectedTenantCredit)} will be applied automatically</span>
                    </div>
                  )}
                </div>
              )}

              <div className="form-row">
                <div className="form-group">
                  <label className="form-label">Amount Received (TZS) *</label>
                  <input className="form-input" type="number" value={data.amount} onChange={(e) => setData('amount', e.target.value)} placeholder="Enter amount" required />
                  {errors.amount && <div style={{ marginTop: 5, fontSize: 12, color: 'var(--red)' }}>{errors.amount}</div>}
                </div>
                <div className="form-group">
                  <label className="form-label">Payment Date *</label>
                  <input className="form-input" type="date" value={data.paid_date} onChange={(e) => setData('paid_date', e.target.value)} required />
                  {errors.paid_date && <div style={{ marginTop: 5, fontSize: 12, color: 'var(--red)' }}>{errors.paid_date}</div>}
                </div>
              </div>

              <div className="form-group">
                <label className="form-label">Reference / Transaction ID</label>
                <input className="form-input" type="text" value={data.reference} onChange={(e) => setData('reference', e.target.value)} placeholder="Bank ref, M-Pesa code, etc." />
                {errors.reference && <div style={{ marginTop: 5, fontSize: 12, color: 'var(--red)' }}>{errors.reference}</div>}
              </div>

              <div className="form-group">
                <label className="form-label">Notes</label>
                <input className="form-input" type="text" value={data.notes} onChange={(e) => setData('notes', e.target.value)} placeholder="Optional" />
                {errors.notes && <div style={{ marginTop: 5, fontSize: 12, color: 'var(--red)' }}>{errors.notes}</div>}
              </div>

              {selectedInvoice && received > 0 && (
                <div style={{ background: 'var(--bg-elevated)', border: '1px solid var(--border)', borderRadius: 10, padding: 16, marginTop: 4 }}>
                  <div style={{ fontSize: '10.5px', fontWeight: 700, letterSpacing: '.6px', textTransform: 'uppercase', color: 'var(--text-muted)', marginBottom: 12 }}>Reconciliation Preview</div>
                  <div style={{ display: 'flex', flexDirection: 'column', gap: 7, fontSize: 13 }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between' }}><span style={{ color: 'var(--text-secondary)' }}>Invoice net payable</span><span>{formatTzsFromUsd(selectedInvoiceNet)}</span></div>
                    {selectedTenantCredit > 0 && <div style={{ display: 'flex', justifyContent: 'space-between' }}><span style={{ color: 'var(--green)' }}>Less: credit on account</span><span style={{ color: 'var(--green)' }}>({formatTzsFromUsd(selectedTenantCredit)})</span></div>}
                    <div style={{ display: 'flex', justifyContent: 'space-between' }}><span style={{ color: 'var(--text-secondary)' }}>Balance due</span><span>{formatTzsFromUsd(selectedBalanceDue)}</span></div>
                    <div style={{ display: 'flex', justifyContent: 'space-between' }}><span style={{ color: 'var(--text-secondary)' }}>Amount received</span><span>{formatTzsFromUsd(received)}</span></div>
                    <div style={{ borderTop: '1px solid var(--border)', paddingTop: 8, marginTop: 2, display: 'flex', justifyContent: 'space-between', fontSize: 14, fontWeight: 700 }}>
                      <span>{reconcileVariance > 0 ? 'Overpayment' : reconcileVariance < 0 ? 'Shortfall' : 'Variance'}</span>
                      <span style={{ color: reconcileVariance > 0 ? '#a78bfa' : reconcileVariance < 0 ? 'var(--red)' : 'var(--green)' }}>{reconcileVariance === 0 ? 'Exact — TZS 0' : fmtVariance(reconcileVariance, formatTzsFromUsd)}</span>
                    </div>
                  </div>
                  <div style={{ marginTop: 12, borderRadius: 8, padding: '10px 14px', fontSize: 13, fontWeight: 600, background: reconcileVariance > 0 ? 'rgba(167,139,250,.12)' : reconcileVariance < 0 ? 'var(--red-dim)' : 'var(--green-dim)', color: reconcileVariance > 0 ? '#a78bfa' : reconcileVariance < 0 ? 'var(--red)' : 'var(--green)' }}>
                    {reconcileVariance === 0 && '✓ Exact payment — invoice will be marked PAID'}
                    {reconcileVariance > 0 && `Overpaid by ${formatTzsFromUsd(reconcileVariance)} — invoice marked PAID, excess credited to tenant account`}
                    {reconcileVariance < 0 && `Underpayment of ${formatTzsFromUsd(Math.abs(reconcileVariance))} — invoice marked PARTIALLY PAID, shortfall remains outstanding`}
                  </div>
                </div>
              )}
            </div>

            <div className="modal-footer" style={{ flexShrink: 0 }}>
              <button type="button" className="btn-ghost" onClick={() => setShowModal(false)} disabled={processing}>Cancel</button>
              <button type="submit" className="btn-primary" disabled={!canSubmit || processing}>
                {processing ? <span className="btn-spinner" /> : <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><polyline points="20 6 9 17 4 12" /></svg>}
                {processing ? 'Processing...' : 'Confirm Payment'}
              </button>
            </div>
          </form>
        </div>
      </div>
    </AppLayout>
  );
}
