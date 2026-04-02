import { useMemo, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm } from '@inertiajs/react';

// Sum of raw item amounts (net, VAT-exclusive)
const invoiceItemsNet = (inv) => (inv.items || []).reduce((sum, item) => sum + Number(item.total || 0), 0);

// Gross payable = net + VAT. Items are VAT-exclusive; VAT is added on top.
// For rent/SC invoices the lease carries the vat_rate. Electricity invoices have no VAT.
const invoiceTotal = (inv) => {
  const net = invoiceItemsNet(inv);
  const vatRate = Number(inv.lease?.vat_rate ?? 0);
  const hasLeaseItems = (inv.items || []).some((i) => {
    const t = String(i.item_type || '').toLowerCase();
    const d = String(i.description || '').toLowerCase();
    return t === 'rent' || t === 'service_charge' || d.includes('rent') || d.includes('service charge');
  });
  const vat = hasLeaseItems && vatRate > 0 ? Math.round(net * vatRate / 100) : 0;
  return net + vat;
};
const classifyInvoiceItem = (item = {}) => {
  const type = String(item.item_type || '').toLowerCase();
  const desc = String(item.description || '').toLowerCase();
  if (type === 'rent' || desc.includes('rent')) return 'rent';
  if (type === 'service_charge' || desc.includes('service charge')) return 'service_charge';
  if (type === 'electricity' || desc.includes('electricity') || desc.includes('generator') || desc.includes('submeter')) return 'electricity';
  return 'electricity';
};

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
  const normalizeCurrency = (value) => (String(value || '').toUpperCase() === 'TZS' ? 'TZS' : 'USD');

  const resolveInvoiceUnit = (invoice) => {
    if (!invoice) return null;
    if (invoice.unit_id) {
      const byId = units.find((u) => Number(u.id) === Number(invoice.unit_id));
      if (byId) return byId;
    }
    if (invoice.unit_ref) {
      const byRef = units.find(
        (u) => String(u.unit_number || '').trim().toLowerCase() === String(invoice.unit_ref || '').trim().toLowerCase()
      );
      if (byRef) return byRef;
    }
    return null;
  };

  const getInvoiceCurrency = (invoice, unitFallback = null) => {
    const unit = unitFallback || resolveInvoiceUnit(invoice);
    return normalizeCurrency(invoice?.currency || unit?.currency);
  };

  const invoicePaidTotal = (invoiceId) => {
    if (!invoiceId) return 0;
    return payments
      .filter((p) => Number(p.invoice_id) === Number(invoiceId) && p.status === 'paid')
      .reduce((sum, p) => sum + Number(p.amount || 0), 0);
  };

  const formatAmount = (amount, currency = 'USD', options = {}) => {
    const numeric = Number(amount || 0);
    if (Number.isNaN(numeric)) return '—';

    const money = normalizeCurrency(currency);
    const abs = Math.abs(numeric);

    if (options.compact) {
      if (abs >= 1_000_000) return `${money} ${(abs / 1_000_000).toFixed(1)}M`;
      if (abs >= 1_000) return `${money} ${(abs / 1_000).toFixed(0)}k`;
    }

    const decimals = money === 'USD' ? 2 : 0;
    return `${money} ${abs.toLocaleString('en-US', {
      minimumFractionDigits: decimals,
      maximumFractionDigits: decimals,
    })}`;
  };

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
    issue_receipt: false,
    wht_confirmed: false,
    wht_reference: '',
  });

  const paymentRows = useMemo(() => {
    const sorted = [...payments].sort((a, b) => {
      const ad = new Date(a.created_at || a.updated_at || a.paid_date || 0).getTime();
      const bd = new Date(b.created_at || b.updated_at || b.paid_date || 0).getTime();
      if (ad !== bd) return ad - bd;
      return Number(a.id || 0) - Number(b.id || 0);
    });

    const paidByInvoice = new Map();

    return sorted.map((p) => {
      const invoice = p.invoice_id
        ? invoices.find((inv) => Number(inv.id) === Number(p.invoice_id))
        : invoices.find((inv) =>
            Number(inv.tenant_id) === Number(p.tenant_id) &&
            Number(inv.unit_id) === Number(p.unit_id) &&
            String(inv.period || '') === String(p.month || '')
          );

      const invoiceId = invoice?.id ? Number(invoice.id) : null;
      const invoiceAmount = invoice ? invoiceTotal(invoice) : Number(p.amount || 0);
      const paidBefore = invoiceId ? Number(paidByInvoice.get(invoiceId) || 0) : 0;
      const balanceBefore = Math.max(invoiceAmount - paidBefore, 0);
      const paymentAmount = Number(p.amount || 0);
      const variance = paymentAmount - balanceBefore;

      let derivedStatus = p.status || 'pending';
      if (invoiceId && p.status === 'paid') {
        if (variance > 0) derivedStatus = 'overpaid';
        else if (variance < 0) derivedStatus = 'partially_paid';
        else derivedStatus = 'paid';
      }

      if (invoiceId && p.status === 'paid') {
        paidByInvoice.set(invoiceId, paidBefore + paymentAmount);
      }

      const tenant = p.tenant || tenants.find((t) => Number(t.id) === Number(p.tenant_id)) || null;
      const unit = p.unit || units.find((u) => Number(u.id) === Number(p.unit_id)) || null;
      const invoiceUnit = unit || resolveInvoiceUnit(invoice);
      const currency = getInvoiceCurrency(invoice, invoiceUnit);

      return {
        id: `p-${p.id}`,
        paymentId: p.id,
        invoiceId: invoiceId,
        invoiceNumber: invoice?.invoice_number || '—',
        tenant,
        unit,
        month: p.month || invoice?.period || '-',
        invoiceAmount,
        amountPaid: paymentAmount,
        variance: invoiceId ? variance : 0,
        currency,
        method: p.method || '—',
        status: derivedStatus,
        date: p.paid_date || p.date || p.created_at || '—',
        dueDate: invoice?.due_date || p.paid_date,
      };
    }).reverse();
  }, [invoices, payments, tenants, units]);

  const filteredRows = useMemo(() => {
    const q = search.trim().toLowerCase();
    return paymentRows.filter((row) => {
      const matchFilter = filter === 'all' || row.status === filter;
      const matchMonth = !month || row.month === month;
      const matchSearch =
        !q ||
        row.tenant?.name?.toLowerCase().includes(q) ||
        row.unit?.unit_number?.toLowerCase().includes(q) ||
        String(row.invoiceNumber || '').toLowerCase().includes(q);
      return matchFilter && matchMonth && matchSearch;
    });
  }, [paymentRows, filter, month, search]);

  const filterCounts = useMemo(() => {
    const counts = {
      all: paymentRows.length,
      paid: 0,
      overpaid: 0,
      partially_paid: 0,
      overdue: 0,
      pending: 0,
    };
    paymentRows.forEach((row) => {
      if (counts[row.status] !== undefined) counts[row.status] += 1;
    });
    return counts;
  }, [paymentRows]);

  const thisMonth = useMemo(() => {
    const now = new Date();
    const currentYear = now.getFullYear();
    const currentMonth = now.getMonth();

    return paymentRows.filter((row) => {
      const parsed = new Date(row.date);
      if (Number.isNaN(parsed.getTime())) return false;
      return parsed.getFullYear() === currentYear && parsed.getMonth() === currentMonth;
    });
  }, [paymentRows]);

  const toCurrencyTotals = (rows, getAmount) => rows.reduce((acc, row) => {
    const money = normalizeCurrency(row.currency);
    acc[money] += Number(getAmount(row) || 0);
    return acc;
  }, { USD: 0, TZS: 0 });

  const formatTotals = (totals, options = {}) => {
    const parts = ['USD', 'TZS']
      .filter((money) => totals[money] > 0)
      .map((money) => formatAmount(totals[money], money, options));
    return parts.length ? parts.join(' + ') : '—';
  };

  const paidByInvoice = useMemo(() => {
    const map = new Map();
    payments
      .filter((p) => p.invoice_id && p.status === 'paid')
      .forEach((p) => {
        const invoiceId = Number(p.invoice_id);
        const current = Number(map.get(invoiceId) || 0);
        map.set(invoiceId, current + Number(p.amount || 0));
      });
    return map;
  }, [payments]);

  const partialInvoiceBalances = useMemo(() => {
    return invoices
      .filter((inv) => inv.type !== 'proforma' && inv.status === 'partially_paid')
      .map((inv) => {
        const paid = Number(paidByInvoice.get(Number(inv.id)) || 0);
        const due = Math.max(invoiceTotal(inv) - paid, 0);
        const currency = getInvoiceCurrency(inv);

        return {
          id: inv.id,
          due,
          currency,
        };
      })
      .filter((entry) => entry.due > 0);
  }, [invoices, paidByInvoice]);

  const shortfallTotals = toCurrencyTotals(partialInvoiceBalances, (entry) => entry.due);
  const partialCt = partialInvoiceBalances.length;
  const overdueTotals = toCurrencyTotals(
    paymentRows.filter((r) => r.status === 'overdue'),
    (r) => Math.max(r.invoiceAmount - r.amountPaid, 0)
  );
  const overdueCt = paymentRows.filter((r) => r.status === 'overdue').length;
  const collectedTotals = toCurrencyTotals(
    thisMonth.filter((r) => ['paid', 'overpaid', 'partially_paid'].includes(r.status)),
    (r) => r.amountPaid
  );
  const paidCt = thisMonth.filter((r) => ['paid', 'overpaid'].includes(r.status)).length;
  const collectionRate = thisMonth.length ? Math.round((paidCt / thisMonth.length) * 100) : 0;

  const creditsByTenant = useMemo(() => {
    const map = new Map();
    paymentRows.forEach((row) => {
      if (row.variance <= 0 || !row.tenant?.id) return;
      const key = `${row.tenant.id}:${row.currency}`;
      const current = map.get(key) || {
        key,
        tenant: row.tenant,
        unit: row.unit,
        amount: 0,
        currency: row.currency,
      };
      current.amount += row.variance;
      map.set(key, current);
    });
    return Array.from(map.values());
  }, [paymentRows]);

  const totalCreditTotals = creditsByTenant.reduce((acc, c) => {
    const money = normalizeCurrency(c.currency);
    acc[money] += Number(c.amount || 0);
    return acc;
  }, { USD: 0, TZS: 0 });
  const creditCt = creditsByTenant.length;

  const creditHistory = useMemo(() => {
    return paymentRows
      .filter((row) => row.variance > 0)
      .map((row) => ({
        id: row.id,
        tenant: row.tenant,
        unit: row.unit,
        amount: row.variance,
        currency: row.currency,
        date: row.date,
        desc: `Overpayment on ${row.invoiceNumber}`,
        type: 'credit',
      }));
  }, [paymentRows]);

  const eligibleInvoices = invoices.filter((i) => ['unpaid', 'overdue', 'partially_paid'].includes(i.status) && i.type !== 'proforma');
  const selectedInvoice = eligibleInvoices.find((inv) => String(inv.id) === String(selectedInvoiceId));
  const selectedInvoiceUnit = useMemo(() => resolveInvoiceUnit(selectedInvoice), [selectedInvoice, units]);
  const selectedInvoiceCurrency = useMemo(() => getInvoiceCurrency(selectedInvoice, selectedInvoiceUnit), [selectedInvoice, selectedInvoiceUnit]);

  const selectedTenantCredit = useMemo(() => {
    if (!selectedInvoice) return 0;
    const credit = creditsByTenant.find(
      (c) => Number(c.tenant?.id) === Number(selectedInvoice.tenant_id) && normalizeCurrency(c.currency) === selectedInvoiceCurrency
    );
    return Number(credit?.amount || 0);
  }, [selectedInvoice, creditsByTenant, selectedInvoiceCurrency]);

  const selectedInvoiceNet = selectedInvoice ? invoiceTotal(selectedInvoice) : 0;
  const selectedInvoicePaid = selectedInvoice ? invoicePaidTotal(selectedInvoice.id) : 0;
  const selectedBalanceDue = Math.max(0, selectedInvoiceNet - selectedInvoicePaid - selectedTenantCredit);
  const received = Number(data.amount || 0);
  const reconcileVariance = received - selectedBalanceDue;

  const canSubmit = Boolean(selectedInvoiceId && received > 0 && data.paid_date);
  const invoiceBreakdown = useMemo(() => {
    if (!selectedInvoice) {
      return { rent: 0, service_charge: 0, electricity: 0, hasLeaseRelated: false, electricityOnly: false };
    }
    const totals = (selectedInvoice.items || []).reduce((acc, item) => {
      const bucket = classifyInvoiceItem(item);
      acc[bucket] += Number(item.total || 0);
      return acc;
    }, { rent: 0, service_charge: 0, electricity: 0 });
    const hasLeaseRelated = totals.rent > 0 || totals.service_charge > 0;
    const electricityOnly = !hasLeaseRelated && totals.electricity > 0;
    return { ...totals, hasLeaseRelated, electricityOnly };
  }, [selectedInvoice]);
  const isPartialPayment = selectedInvoice && received > 0 && received + 0.01 < selectedBalanceDue;
  const receiptValidationError = (() => {
    if (!data.issue_receipt) return '';
    if (isPartialPayment) return 'Receipt cannot be issued for partial payments.';
    if (invoiceBreakdown.hasLeaseRelated && !data.wht_confirmed) {
      return 'WHT confirmation is required to issue receipt for rent/service charge payments.';
    }
    return '';
  })();

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
    setData('issue_receipt', false);
    setData('wht_confirmed', false);
    setData('wht_reference', '');
  };

  const onSelectInvoice = (invoiceId) => {
    setSelectedInvoiceId(invoiceId);
    setData('invoice_id', invoiceId || '');
    if (!invoiceId) return;

    const inv = eligibleInvoices.find((x) => String(x.id) === String(invoiceId));
    if (!inv) return;

    const tenantFallback = tenants.find(
      (t) => String(t.name || '').trim().toLowerCase() === String(inv.tenant_name || '').trim().toLowerCase()
    );
    const unitFallback = units.find(
      (u) => String(u.unit_number || '').trim().toLowerCase() === String(inv.unit_ref || '').trim().toLowerCase()
    );

    const invoiceCurrency = getInvoiceCurrency(inv, unitFallback || null);
    const tenantCredit = creditsByTenant.find(
      (c) => Number(c.tenant?.id) === Number(inv.tenant_id) && normalizeCurrency(c.currency) === invoiceCurrency
    );
    const invoicePaid = invoicePaidTotal(inv.id);
    const due = Math.max(invoiceTotal(inv) - invoicePaid - Number(tenantCredit?.amount || 0), 0);

    setData('tenant_id', String(inv.tenant_id || tenantFallback?.id || ''));
    setData('unit_id', String(inv.unit_id || unitFallback?.id || ''));
    setData('month', String(inv.period || 'Mar 2026'));
    setData('amount', due ? String(due) : String(invoiceTotal(inv)));
    setData('reference', inv.invoice_number || '');
  };

  const submit = (e) => {
    e.preventDefault();
    if (!canSubmit) return;

    setSubmitError('');
    setSubmitMessage({ type: '', text: '' });

    const selectedInv = eligibleInvoices.find((inv) => String(inv.id) === String(selectedInvoiceId));
    const tenantFallback = selectedInv
      ? tenants.find(
          (t) => String(t.name || '').trim().toLowerCase() === String(selectedInv.tenant_name || '').trim().toLowerCase()
        )
      : null;
    const unitFallback = selectedInv
      ? units.find(
          (u) => String(u.unit_number || '').trim().toLowerCase() === String(selectedInv.unit_ref || '').trim().toLowerCase()
        )
      : null;

    const payload = {
      ...data,
      status: 'paid',
      invoice_id: data.invoice_id || selectedInvoiceId || '',
      tenant_id: data.tenant_id || selectedInv?.tenant_id || tenantFallback?.id || '',
      unit_id: data.unit_id || selectedInv?.unit_id || unitFallback?.id || '',
      month: data.month || selectedInv?.period || 'Mar 2026',
      reference: data.reference || selectedInv?.invoice_number || '',
    };

    if (data.issue_receipt && receiptValidationError) {
      setSubmitError(receiptValidationError);
      return;
    }

    post('/payments', {
      data: payload,
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
          <div className="stat-value">{formatTotals(collectedTotals, { compact: true })}</div>
          <div className="stat-label">Collected This Month</div>
        </div>

        <div className="stat-card">
          <div className="stat-top">
            <div className="stat-icon red"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" /></svg></div>
            <span className="stat-delta down">{overdueCt} tenant{overdueCt !== 1 ? 's' : ''}</span>
          </div>
          <div className="stat-value">{formatTotals(overdueTotals, { compact: true })}</div>
          <div className="stat-label">Overdue Balance</div>
        </div>

        <div className="stat-card">
          <div className="stat-top">
            <div className="stat-icon amber"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="12" y1="1" x2="12" y2="23" /><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6" /></svg></div>
            <span className="stat-delta">{partialCt} partial</span>
          </div>
          <div className="stat-value">{formatTotals(shortfallTotals, { compact: true })}</div>
          <div className="stat-label">Outstanding Shortfall</div>
        </div>

        <div className="stat-card">
          <div className="stat-top">
            <div className="stat-icon green"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="5" /><path d="M12 1v4M12 19v4M4.22 4.22l2.83 2.83M16.95 16.95l2.83 2.83M1 12h4M19 12h4M4.22 19.78l2.83-2.83M16.95 7.05l2.83-2.83" /></svg></div>
            <span className="stat-delta up">{creditCt} tenant{creditCt !== 1 ? 's' : ''}</span>
          </div>
          <div className="stat-value">{formatTotals(totalCreditTotals, { compact: true })}</div>
          <div className="stat-label">Credits on Account</div>
        </div>
      </div>

      <div className="team-tabs" style={{ marginBottom: 18 }}>
        <button className={`team-tab ${activeTab === 'pay-ledger' ? 'active' : ''}`} onClick={() => setActiveTab('pay-ledger')}>Payment Ledger</button>
        <button className={`team-tab ${activeTab === 'pay-credits' ? 'active' : ''}`} onClick={() => setActiveTab('pay-credits')}>Tenant Credits</button>
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
            {filteredRows.length === 0 ? (
              <div style={{ textAlign: 'center', padding: 40, color: 'var(--text-muted)', fontSize: 13 }}>No payments found</div>
            ) : (
              <table className="units-list-table" style={{ width: '100%', borderCollapse: 'collapse' }}>
                <thead>
                  <tr style={{ borderBottom: '1px solid var(--border-subtle)' }}>
                    <th style={{ textAlign: 'left', fontSize: 11, fontWeight: 600, letterSpacing: 0.5, textTransform: 'uppercase', color: 'var(--text-muted)', padding: '8px 14px', paddingLeft: 20, whiteSpace: 'nowrap' }}>TENANT</th>
                    <th style={{ textAlign: 'left', fontSize: 11, fontWeight: 600, letterSpacing: 0.5, textTransform: 'uppercase', color: 'var(--text-muted)', padding: '8px 14px', whiteSpace: 'nowrap' }}>UNIT</th>
                    <th style={{ textAlign: 'left', fontSize: 11, fontWeight: 600, letterSpacing: 0.5, textTransform: 'uppercase', color: 'var(--text-muted)', padding: '8px 14px', whiteSpace: 'nowrap' }}>INVOICE</th>
                    <th style={{ textAlign: 'left', fontSize: 11, fontWeight: 600, letterSpacing: 0.5, textTransform: 'uppercase', color: 'var(--text-muted)', padding: '8px 14px', whiteSpace: 'nowrap' }}>INVOICE AMOUNT</th>
                    <th style={{ textAlign: 'left', fontSize: 11, fontWeight: 600, letterSpacing: 0.5, textTransform: 'uppercase', color: 'var(--text-muted)', padding: '8px 14px', whiteSpace: 'nowrap' }}>AMOUNT PAID</th>
                    <th style={{ textAlign: 'left', fontSize: 11, fontWeight: 600, letterSpacing: 0.5, textTransform: 'uppercase', color: 'var(--text-muted)', padding: '8px 14px', whiteSpace: 'nowrap' }}>VARIANCE</th>
                    <th style={{ textAlign: 'left', fontSize: 11, fontWeight: 600, letterSpacing: 0.5, textTransform: 'uppercase', color: 'var(--text-muted)', padding: '8px 14px', whiteSpace: 'nowrap' }}>METHOD</th>
                    <th style={{ textAlign: 'left', fontSize: 11, fontWeight: 600, letterSpacing: 0.5, textTransform: 'uppercase', color: 'var(--text-muted)', padding: '8px 14px', whiteSpace: 'nowrap' }}>STATUS</th>
                    <th style={{ textAlign: 'left', fontSize: 11, fontWeight: 600, letterSpacing: 0.5, textTransform: 'uppercase', color: 'var(--text-muted)', padding: '8px 14px', whiteSpace: 'nowrap' }}>DATE</th>
                  </tr>
                </thead>
                <tbody>
                  {filteredRows.map((row) => {
                    const variance = row.amountPaid - row.invoiceAmount;
                    const varianceColor = variance > 0 ? 'var(--green)' : variance < 0 ? 'var(--red)' : 'var(--text-muted)';
                    const varianceText = variance > 0 ? `+${formatAmount(variance, row.currency)}` : variance < 0 ? `(${formatAmount(Math.abs(variance), row.currency)})` : '—';
                    const statusInfo = PAY_STATUS[row.status] || PAY_STATUS.pending;
                    const tenantPeriod = row.month || '—';
                    const paymentRef = `P${String(row.paymentId || '').padStart(3, '0')}`;
                    
                    let statusDisplay = statusInfo.label;
                    if (row.status === 'partially_paid') {
                      const shortfall = Math.max(0, row.invoiceAmount - row.amountPaid);
                      statusDisplay = `${statusInfo.label} — ${formatAmount(shortfall, row.currency, { compact: true })} short`;
                    }

                    const tenantColor = row.tenant?.color || 'rgba(59, 130, 246, 0.18)';
                    const tenantTextColor = row.tenant?.text_color || 'var(--accent)';
                    const tenantInitials = row.tenant?.initials || toInitials(row.tenant?.name);

                    return (
                      <tr key={row.id} style={{ borderBottom: '1px solid var(--border-subtle)', cursor: 'pointer', transition: 'background .1s' }} onMouseOver={(e) => e.currentTarget.style.background = 'var(--bg-hover)'} onMouseOut={(e) => e.currentTarget.style.background = ''}>
                        <td style={{ padding: '11px 14px', paddingLeft: 20, fontSize: 13.5, verticalAlign: 'middle' }}>
                          <div style={{ display: 'flex', alignItems: 'center', gap: 9 }}>
                            <div style={{ width: 26, height: 26, borderRadius: '50%', backgroundColor: tenantColor, color: tenantTextColor, fontSize: 10.5, fontWeight: 600, display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
                              {tenantInitials}
                            </div>
                            <div>
                              <div style={{ fontWeight: 600 }}>{row.tenant?.name || '—'}</div>
                              <div style={{ fontSize: 11.5, color: 'var(--text-muted)' }}>{paymentRef} · {tenantPeriod}</div>
                            </div>
                          </div>
                        </td>
                        <td style={{ padding: '11px 14px', fontSize: 13.5, verticalAlign: 'middle', fontWeight: 600 }}>{row.unit?.unit_number || '—'}</td>
                        <td style={{ padding: '11px 14px', fontSize: 12.5, verticalAlign: 'middle', color: 'var(--accent)', fontWeight: 500, cursor: 'pointer' }}>{row.invoiceNumber}</td>
                        <td style={{ padding: '11px 14px', fontSize: 13.5, verticalAlign: 'middle', fontVariantNumeric: 'tabular-nums' }}>{formatAmount(row.invoiceAmount, row.currency)}</td>
                        <td style={{ padding: '11px 14px', fontSize: 13.5, verticalAlign: 'middle', fontWeight: 700, fontVariantNumeric: 'tabular-nums' }}>{formatAmount(row.amountPaid, row.currency)}</td>
                        <td style={{ padding: '11px 14px', fontSize: 13.5, verticalAlign: 'middle', color: varianceColor, fontWeight: 600, fontVariantNumeric: 'tabular-nums' }}>{varianceText}</td>
                        <td style={{ padding: '11px 14px', fontSize: 12.5, verticalAlign: 'middle', color: 'var(--text-secondary)' }}>{row.method}</td>
                        <td style={{ padding: '11px 14px', fontSize: 13.5, verticalAlign: 'middle' }}>
                          <div style={{ background: statusInfo.bg, color: statusInfo.color, padding: '2px 9px', borderRadius: 20, fontSize: 12, fontWeight: 600, display: 'inline-block' }}>
                            {statusDisplay}
                          </div>
                        </td>
                        <td style={{ padding: '11px 14px', fontSize: 12, verticalAlign: 'middle', color: 'var(--text-muted)' }}>
                          {row.date || '—'}
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            )}
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
                    <div key={entry.key} style={{ display: 'flex', alignItems: 'center', gap: 10, padding: 12, borderBottom: '1px solid var(--border-subtle)' }}>
                      <div
                        className="t-avatar"
                        style={{
                          width: 32,
                          height: 32,
                          fontSize: 12,
                          background: entry.tenant?.color || 'var(--accent-dim)',
                          color: entry.tenant?.text_color || 'var(--accent)',
                        }}
                      >
                        {entry.tenant.initials || toInitials(entry.tenant.name)}
                      </div>
                      <div style={{ flex: 1 }}>
                        <div style={{ fontWeight: 600, fontSize: 13 }}>{entry.tenant.name}</div>
                        <div style={{ fontSize: '11.5px', color: 'var(--text-muted)' }}>{entry.unit?.unit_number || '—'} · Credit from overpayment</div>
                      </div>
                      <div style={{ textAlign: 'right' }}>
                        <div style={{ fontSize: 16, fontWeight: 700, color: 'var(--green)' }}>{formatAmount(entry.amount, entry.currency)}</div>
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
                  creditHistory.map((entry) => {
                    const deltaColor = entry.type === 'credit'
                      ? 'var(--green)'
                      : entry.type === 'apply'
                        ? 'var(--accent)'
                        : 'var(--text-muted)';
                    const deltaSign = entry.type === 'credit' ? '+' : '-';

                    return (
                    <div key={entry.id} style={{ padding: 12, borderBottom: '1px solid var(--border-subtle)' }}>
                      <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 3 }}>
                        <div style={{ fontSize: 13, fontWeight: 500 }}>{entry.tenant?.name || '—'}</div>
                        <div style={{ fontSize: 13, fontWeight: 700, color: deltaColor }}>{deltaSign}{formatAmount(entry.amount, entry.currency)}</div>
                      </div>
                      <div style={{ fontSize: 12, color: 'var(--text-muted)' }}>{entry.desc} · {entry.date || '—'}</div>
                    </div>
                    );
                  })
                )}
              </div>
            </div>
          </div>
        </>
      )}

      <div className={`modal-overlay ${showModal ? 'open' : ''}`} style={{ alignItems: 'center', padding: '12px', overflowY: 'auto' }} onClick={(e) => e.target === e.currentTarget && !processing && setShowModal(false)}>
        <div className="modal" style={{ width: 560, maxHeight: 'calc(100vh - 24px)', display: 'flex', flexDirection: 'column' }}>
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
                    {eligibleInvoices.map((inv) => {
                      const invoiceCurrency = getInvoiceCurrency(inv);
                      return (
                        <option key={inv.id} value={inv.id}>{`${inv.invoice_number} — ${inv.tenant_name} (${inv.unit_ref || 'Unit'}) — ${formatAmount(invoiceTotal(inv), invoiceCurrency)}`}</option>
                      );
                    })}
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
                      <div style={{ fontSize: 18, fontWeight: 700, color: 'var(--accent)' }}>{formatAmount(selectedInvoiceNet, selectedInvoiceCurrency)}</div>
                    </div>
                  </div>
                  {selectedTenantCredit > 0 && (
                    <div style={{ background: 'var(--green-dim)', border: '1px solid var(--green)', borderRadius: 8, padding: '10px 12px', fontSize: 13 }}>
                      <span style={{ color: 'var(--green)', fontWeight: 600 }}>Credit on account: </span>
                      <span>{formatAmount(selectedTenantCredit, selectedInvoiceCurrency)} will be applied automatically</span>
                    </div>
                  )}
                </div>
              )}

              <div className="form-row">
                <div className="form-group">
                  <label className="form-label">Amount Received ({selectedInvoiceCurrency}) *</label>
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

              <div style={{ background: 'var(--bg-elevated)', border: '1px solid var(--border)', borderRadius: 10, padding: 14, marginTop: 8 }}>
                <label style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 13.5, fontWeight: 600 }}>
                  <input type="checkbox" checked={!!data.issue_receipt} onChange={(e) => setData('issue_receipt', e.target.checked)} />
                  Issue Receipt
                </label>
                <div style={{ marginTop: 8, fontSize: 12, color: 'var(--text-muted)' }}>
                  {invoiceBreakdown.electricityOnly
                    ? 'Electricity-only payment: receipt can be issued without WHT confirmation.'
                    : 'Rent/service charge payments require WHT confirmation before receipt issuance.'}
                </div>
                <div style={{ marginTop: 10 }}>
                  <label style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 13 }}>
                    <input type="checkbox" checked={!!data.wht_confirmed} onChange={(e) => setData('wht_confirmed', e.target.checked)} />
                    Tenant confirmed WHT payment
                  </label>
                </div>
                <div style={{ marginTop: 10 }}>
                  <label className="form-label">WHT Reference / Note</label>
                  <input className="form-input" type="text" value={data.wht_reference} onChange={(e) => setData('wht_reference', e.target.value)} placeholder="Optional WHT reference number" />
                </div>
                {(errors.wht_confirmed || errors.issue_receipt) && (
                  <div style={{ marginTop: 6, fontSize: 12, color: 'var(--red)' }}>{errors.wht_confirmed || errors.issue_receipt}</div>
                )}
                {receiptValidationError && (
                  <div style={{ marginTop: 6, fontSize: 12, color: 'var(--red)' }}>{receiptValidationError}</div>
                )}
              </div>

              {selectedInvoice && received > 0 && (
                <div style={{ background: 'var(--bg-elevated)', border: '1px solid var(--border)', borderRadius: 10, padding: 16, marginTop: 4 }}>
                  <div style={{ fontSize: '10.5px', fontWeight: 700, letterSpacing: '.6px', textTransform: 'uppercase', color: 'var(--text-muted)', marginBottom: 12 }}>Reconciliation Preview</div>
                  <div style={{ display: 'flex', flexDirection: 'column', gap: 7, fontSize: 13 }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between' }}><span style={{ color: 'var(--text-secondary)' }}>Invoice gross (incl. VAT)</span><span>{formatAmount(selectedInvoiceNet, selectedInvoiceCurrency)}</span></div>
                    {selectedTenantCredit > 0 && <div style={{ display: 'flex', justifyContent: 'space-between' }}><span style={{ color: 'var(--green)' }}>Less: credit on account</span><span style={{ color: 'var(--green)' }}>({formatAmount(selectedTenantCredit, selectedInvoiceCurrency)})</span></div>}
                    <div style={{ display: 'flex', justifyContent: 'space-between' }}><span style={{ color: 'var(--text-secondary)' }}>Balance due</span><span>{formatAmount(selectedBalanceDue, selectedInvoiceCurrency)}</span></div>
                    <div style={{ display: 'flex', justifyContent: 'space-between' }}><span style={{ color: 'var(--text-secondary)' }}>Amount received</span><span>{formatAmount(received, selectedInvoiceCurrency)}</span></div>
                    <div style={{ borderTop: '1px solid var(--border)', paddingTop: 8, marginTop: 2, display: 'flex', justifyContent: 'space-between', fontSize: 14, fontWeight: 700 }}>
                      <span>{reconcileVariance > 0 ? 'Overpayment' : reconcileVariance < 0 ? 'Shortfall' : 'Variance'}</span>
                      <span style={{ color: reconcileVariance > 0 ? '#a78bfa' : reconcileVariance < 0 ? 'var(--red)' : 'var(--green)' }}>{reconcileVariance === 0 ? `Exact — ${selectedInvoiceCurrency} 0` : fmtVariance(reconcileVariance, (value) => formatAmount(value, selectedInvoiceCurrency))}</span>
                    </div>
                  </div>
                  <div style={{ marginTop: 12, borderRadius: 8, padding: '10px 14px', fontSize: 13, fontWeight: 600, background: reconcileVariance > 0 ? 'rgba(167,139,250,.12)' : reconcileVariance < 0 ? 'var(--red-dim)' : 'var(--green-dim)', color: reconcileVariance > 0 ? '#a78bfa' : reconcileVariance < 0 ? 'var(--red)' : 'var(--green)' }}>
                    {reconcileVariance === 0 && '✓ Exact payment — invoice will be marked PAID'}
                    {reconcileVariance > 0 && `Overpaid by ${formatAmount(reconcileVariance, selectedInvoiceCurrency)} — invoice marked PAID, excess credited to tenant account`}
                    {reconcileVariance < 0 && `Underpayment of ${formatAmount(Math.abs(reconcileVariance), selectedInvoiceCurrency)} — invoice marked PARTIALLY PAID, shortfall remains outstanding`}
                  </div>
                </div>
              )}
            </div>

            <div className="modal-footer" style={{ flexShrink: 0 }}>
              <button type="button" className="btn-ghost" onClick={() => setShowModal(false)} disabled={processing}>Cancel</button>
              <button type="submit" className="btn-primary" disabled={!canSubmit || processing || (data.issue_receipt && !!receiptValidationError)}>
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
