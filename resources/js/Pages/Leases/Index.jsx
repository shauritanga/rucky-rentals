import { useEffect, useMemo, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm, router, usePage } from '@inertiajs/react';

const fmt = (n) => Number(n).toLocaleString();
const CURRENCY_FALLBACK = 'USD';
const CYCLE_LABELS = { 3:'Quarterly · 3mo', 4:'4-Month', 6:'Semi-Annual · 6mo', 12:'Annual' };
const CYCLE_PAYMENTS = { 3:'Quarterly', 4:'4-Month', 6:'Semi-Annual', 12:'Annual' };
const STATUS_BADGE = { active:'active', expiring:'expiring', overdue:'overdue', pending_accountant:'pending_accountant', pending_pm:'pending_accountant', rejected:'rejected', terminated:'pending' };
const STATUS_KV_MAP = { active:'green', expiring:'amber', overdue:'red', pending_accountant:'amber', pending_pm:'amber', rejected:'red' };
const STATUS_LABEL_MAP = { active:'Active', expiring:'Expiring Soon', overdue:'Overdue', pending_accountant:'Pending Approval', pending_pm:'Pending Approval', rejected:'Rejected' };
const DURATION_OPTIONS = Array.from({ length: 15 }, (_, i) => {
  const years = i + 1;
  return { months: years * 12, label: `${years} Year${years > 1 ? 's' : ''} (${years * 12} months)` };
});

function addMonthsISO(startDate, months) {
  if (!startDate) return '';
  const d = new Date(`${startDate}T00:00:00`);
  if (Number.isNaN(d.getTime())) return '';
  d.setMonth(d.getMonth() + Number(months || 0));
  return d.toISOString().slice(0, 10);
}

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

function formatCompactMoney(amount, currency = CURRENCY_FALLBACK) {
  if (amount == null || Number.isNaN(Number(amount))) return '—';
  const code = resolveCurrency(currency);
  const value = Number(amount);
  const abs = Math.abs(value);

  if (abs >= 1_000_000_000) return `${code} ${(value / 1_000_000_000).toFixed(1)}B`;
  if (abs >= 1_000_000) return `${code} ${(value / 1_000_000).toFixed(1)}M`;
  if (abs >= 1_000) return `${code} ${(value / 1_000).toFixed(0)}k`;

  return formatMoney(value, code);
}

function fmtDateShort(dateStr) {
  if (!dateStr) return '—';
  const d = new Date(`${dateStr}T00:00:00`);
  if (Number.isNaN(d.getTime())) return dateStr;
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

function fmtDateLong(dateStr) {
  if (!dateStr) return '—';
  const d = new Date(`${dateStr}T00:00:00`);
  if (Number.isNaN(d.getTime())) return dateStr;
  return d.toLocaleDateString('en-US', { day: 'numeric', month: 'short', year: 'numeric' });
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

function calcLeaseProgress(lease) {
  const start = new Date(`${lease?.start_date}T00:00:00`);
  const end = new Date(`${lease?.end_date}T00:00:00`);
  const now = new Date();

  if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime()) || end <= start) {
    return { pct: 0, daysLeft: 0 };
  }

  const totalMs = end.getTime() - start.getTime();
  const elapsedMs = Math.min(Math.max(now.getTime() - start.getTime(), 0), totalMs);
  const pct = (elapsedMs / totalMs) * 100;
  const daysLeft = Math.ceil((end.getTime() - now.getTime()) / (1000 * 60 * 60 * 24));

  return { pct, daysLeft };
}

function buildPaymentSchedule(lease, isPending) {
  if (Array.isArray(lease?.installments) && lease.installments.length > 0) {
    return lease.installments.map((inst, idx) => ({
      installNum: Number(inst.sequence) || idx + 1,
      dueDate: fmtDateLong(inst.due_date),
      period: `${fmtDateShort(inst.period_start)} - ${fmtDateShort(inst.period_end)}`,
      amount: Number(inst.amount || 0),
      status: inst.status || 'unpaid',
    }));
  }

  const start = new Date(`${lease?.start_date}T00:00:00`);
  const end = new Date(`${lease?.end_date}T00:00:00`);
  const cycle = Number(lease?.payment_cycle) || 3;
  const monthlyRent = Number(lease?.monthly_rent) || 0;

  if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime()) || end <= start) {
    return [];
  }

  const rows = [];
  const now = new Date();
  const upcomingLimit = addMonthsDate(now, 1);
  let cursor = new Date(start);
  let index = 1;

  while (cursor < end && index <= 60) {
    const periodStart = new Date(cursor);
    const nextCursor = addMonthsDate(periodStart, cycle);
    const periodEnd = new Date(Math.min(nextCursor.getTime(), end.getTime()));
    periodEnd.setDate(periodEnd.getDate() - 1);

    const dueDate = new Date(periodStart);
    let status = 'scheduled';

    if (!isPending) {
      if (lease.status === 'overdue' && dueDate <= now && nextCursor > now) {
        status = 'overdue';
      } else if (dueDate < now) {
        status = 'paid';
      } else if (dueDate <= upcomingLimit) {
        status = 'upcoming';
      }
    }

    const monthsInPeriod = Math.max(1, Math.round((nextCursor.getTime() - periodStart.getTime()) / (1000 * 60 * 60 * 24 * 30.4375)));

    rows.push({
      installNum: index,
      dueDate: fmtDateLong(periodStart.toISOString().slice(0, 10)),
      period: `${fmtDateShort(periodStart.toISOString().slice(0, 10))} - ${fmtDateShort(periodEnd.toISOString().slice(0, 10))}`,
      amount: monthlyRent * monthsInPeriod,
      status,
    });

    cursor = nextCursor;
    index += 1;
  }

  return rows;
}

export default function LeasesIndex({ leases, tenants, units, settings = {} }) {
  const { props } = usePage();
  const user = props?.auth?.user;
  const [filter, setFilter] = useState('all');
  const [search, setSearch] = useState('');
  const [selected, setSelected] = useState(null);
  const [showModal, setShowModal] = useState(false);
  const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
  const [tenantSearch, setTenantSearch] = useState('');
  const [showTenantDropdown, setShowTenantDropdown] = useState(false);
  const [possessionDate, setPossessionDate] = useState('2026-04-01');
  const [rentStartDate, setRentStartDate] = useState('2026-04-01');
  const [fitoutEnabled, setFitoutEnabled] = useState(false);
  const [fitoutToDate, setFitoutToDate] = useState('');
  const [whtRate, setWhtRate] = useState(10);
  const [editingLeaseId, setEditingLeaseId] = useState(null);
  const [editingFitout, setEditingFitout] = useState(false);
  const [fitoutEditEnabled, setFitoutEditEnabled] = useState(false);
  const [fitoutEditToDate, setFitoutEditToDate] = useState('');
  const [vatRate, setVatRate] = useState(18);

  const depositRentMonths = Number(settings.deposit_rent_months ?? 1);
  const depositScMonths   = Number(settings.deposit_service_charge_months ?? 1);

  const { data, setData, post, processing, reset } = useForm({
    tenant_id:'', unit_id:'', start_date:'2026-04-01', end_date:'2027-04-01',
    duration_months:12, payment_cycle:3, monthly_rent:'', deposit:'', terms:'',
    possession_date:'2026-04-01', rent_start_date:'2026-04-01', fitout_enabled:false, fitout_to_date:'', fitout_days:0,
    wht_rate:10, vat_rate:18,
  });

  useEffect(() => {
    if (!possessionDate) return;
    if (!fitoutEnabled) {
      setRentStartDate(possessionDate);
      return;
    }

    if (!fitoutToDate) return;
    const fitoutEnd = new Date(`${fitoutToDate}T00:00:00`);
    if (Number.isNaN(fitoutEnd.getTime())) return;
    fitoutEnd.setDate(fitoutEnd.getDate() + 1);
    setRentStartDate(fitoutEnd.toISOString().slice(0, 10));
  }, [possessionDate, fitoutEnabled, fitoutToDate]);

  useEffect(() => {
    const nextEndDate = addMonthsISO(rentStartDate, data.duration_months);
    if (nextEndDate && nextEndDate !== data.end_date) {
      setData('end_date', nextEndDate);
    }
    if (rentStartDate && rentStartDate !== data.start_date) {
      setData('start_date', rentStartDate);
    }
  }, [rentStartDate, data.duration_months, data.end_date, data.start_date, setData]);

  const summary = useMemo(() => {
    const rent = Number(data.monthly_rent) || 0;
    const cycle = Number(data.payment_cycle) || 0;
    const duration = Number(data.duration_months) || 0;
    const serviceCharge = units.find(u => String(u.id) === String(data.unit_id))?.service_charge ?? 0;
    const subtotal = rent + serviceCharge;
    const vat = Math.round(subtotal * (Number(vatRate || 0) / 100));
    const gross = subtotal + vat;
    // WHT base: rent + service charge, VAT-exclusive.
    const wht = Math.round(subtotal * (Number(whtRate || 0) / 100));
    const net = gross - wht;
    const instalment = net * cycle;
    const unitSC = units.find(u => String(u.id) === String(data.unit_id))?.service_charge ?? 0;
    const deposit = Number(data.deposit) || (rent > 0
      ? (rent * depositRentMonths) + (unitSC * depositScMonths)
      : 0);
    const annual = gross * 12;
    const fitoutDays = fitoutEnabled && possessionDate && fitoutToDate
      ? Math.max(0, Math.round((new Date(`${fitoutToDate}T00:00:00`) - new Date(`${possessionDate}T00:00:00`)) / 86400000) + 1)
      : 0;
    const fitoutExtraSC = fitoutDays > 0 ? Math.round(serviceCharge * fitoutDays / 30) : 0;
    const fitoutExtraVAT = fitoutDays > 0 ? Math.round(fitoutExtraSC * (Number(vatRate || 0) / 100)) : 0;

    return {
      rent,
      cycle,
      duration,
      serviceCharge,
      subtotal,
      vat,
      gross,
      wht,
      net,
      instalment,
      deposit,
      annual,
      fitoutDays,
      fitoutExtraSC,
      fitoutExtraVAT,
      period: `${fmtDateShort(rentStartDate)} -> ${fmtDateShort(data.end_date)} (${duration} months)`,
    };
  }, [data.monthly_rent, data.payment_cycle, data.duration_months, data.deposit, data.end_date, data.unit_id, rentStartDate, vatRate, whtRate, fitoutEnabled, possessionDate, fitoutToDate, units, depositRentMonths, depositScMonths]);

  const filtered = leases.filter(l => {
    const matchFilter = filter === 'all' || l.status === filter;
    const q = search.toLowerCase();
    const matchSearch = !q || l.tenant?.name?.toLowerCase().includes(q) || l.unit?.unit_number?.toLowerCase().includes(q);
    return matchFilter && matchSearch;
  });

  const counts = {};
  ['all','active','expiring','overdue','pending_accountant','rejected'].forEach(s => {
    counts[s] = s === 'all'
      ? leases.length
      : s === 'pending_accountant'
        ? leases.filter(l => l.status==='pending_accountant' || l.status==='pending_pm').length
        : leases.filter(l => l.status===s).length;
  });
  const monthlyRevenueByCurrency = useMemo(() => {
    return leases.reduce((acc, lease) => {
      const currency = resolveCurrency(lease.currency || lease.unit?.currency);
      acc[currency] = (acc[currency] || 0) + Number(lease.monthly_rent || 0);
      return acc;
    }, { USD: 0, TZS: 0 });
  }, [leases]);

  const annualContractByCurrency = useMemo(() => ({
    USD: monthlyRevenueByCurrency.USD * 12,
    TZS: monthlyRevenueByCurrency.TZS * 12,
  }), [monthlyRevenueByCurrency]);

  const currentTenant = tenants.find(t => String(t.id) === String(data.tenant_id));
  const filteredTenants = tenants.filter(t => {
    const q = tenantSearch.toLowerCase().trim();
    if (!q) return true;
    return t.name?.toLowerCase().includes(q) || t.email?.toLowerCase().includes(q) || t.phone?.toLowerCase().includes(q);
  });

  const unitsByFloor = useMemo(() => {
    const activeUnitIds = new Set(
      leases
        .filter(l => ['active', 'expiring', 'overdue'].includes(l.status) && String(l.id) !== String(editingLeaseId))
        .map(l => String(l.unit_id))
    );
    const grouped = {};
    units
      .filter(u => !activeUnitIds.has(String(u.id)))
      .forEach((u) => {
        const floor = Number(u.floor) || 0;
        if (!grouped[floor]) grouped[floor] = [];
        grouped[floor].push(u);
      });
    return Object.entries(grouped).sort((a, b) => Number(a[0]) - Number(b[0]));
  }, [units, leases, editingLeaseId]);

  const selectedUnit = useMemo(
    () => units.find((u) => String(u.id) === String(data.unit_id)),
    [units, data.unit_id]
  );
  const selectedUnitCurrency = resolveCurrency(selectedUnit?.currency);

  const openLeaseModal = () => {
    reset();
    setTenantSearch('');
    setShowTenantDropdown(false);
    setPossessionDate('2026-04-01');
    setRentStartDate('2026-04-01');
    setFitoutEnabled(false);
    setFitoutToDate('');
    setWhtRate(10);
    setVatRate(18);
    setData('start_date', '2026-04-01');
    setData('duration_months', 12);
    setData('payment_cycle', 3);
    setData('unit_id', '');
    setData('tenant_id', '');
    setData('monthly_rent', '');
    setData('deposit', '');
    setData('terms', '');
    setEditingLeaseId(null);
    setShowModal(true);
  };

  const openEditModal = (lease) => {
    reset();
    setEditingLeaseId(lease.id);
    setTenantSearch(lease.tenant?.name || '');
    setWhtRate(Number(lease.wht_rate ?? 10));
    setVatRate(Number(lease.vat_rate ?? 18));
    setPossessionDate(lease.possession_date || lease.start_date || '2026-04-01');
    setRentStartDate(lease.rent_start_date || lease.start_date || '2026-04-01');
    setFitoutEnabled(!!lease.fitout_enabled);
    setFitoutToDate(lease.fitout_to_date || '');
    setData(d => ({
      ...d,
      tenant_id: String(lease.tenant_id || lease.tenant?.id || ''),
      unit_id: String(lease.unit_id || lease.unit?.id || ''),
      start_date: lease.start_date || '',
      end_date: lease.end_date || '',
      duration_months: lease.duration_months || 12,
      payment_cycle: lease.payment_cycle || 3,
      monthly_rent: lease.monthly_rent ?? '',
      deposit: lease.deposit ?? '',
      wht_rate: lease.wht_rate ?? 10,
      vat_rate: lease.vat_rate ?? 18,
      terms: lease.terms ?? '',
      possession_date: lease.possession_date || lease.start_date || '',
      rent_start_date: lease.rent_start_date || lease.start_date || '',
      fitout_enabled: !!lease.fitout_enabled,
      fitout_to_date: lease.fitout_to_date || '',
      fitout_days: lease.fitout_days || 0,
    }));
    setShowModal(true);
  };

  const selectTenant = (tenant) => {
    setData('tenant_id', tenant.id);
    setTenantSearch('');
    setShowTenantDropdown(false);
  };

  const onUnitChange = (unitId) => {
    setData('unit_id', unitId);
    const u = units.find(x => String(x.id) === String(unitId));
    if (u) {
      const autoDeposit = (u.rent * depositRentMonths) + ((u.service_charge ?? 0) * depositScMonths);
      setData(d => ({ ...d, monthly_rent: u.rent, deposit: autoDeposit }));
    }
  };

  const approve = (lease, action) => router.patch(`/leases/${lease.id}`, { action }, { onSuccess: () => setSelected(s => s ? {...s, status: action==='approve_superuser'?'active':action==='reject'?'rejected':s.status} : null) });

  const openFitoutEdit = () => {
    setFitoutEditEnabled(!!selected?.fitout_enabled);
    setFitoutEditToDate(selected?.fitout_to_date || '');
    setEditingFitout(true);
  };

  const saveFitout = () => {
    const days = fitoutEditEnabled && selected?.possession_date && fitoutEditToDate
      ? Math.max(0, Math.round(
          (new Date(`${fitoutEditToDate}T00:00:00`) - new Date(`${selected.possession_date}T00:00:00`)) / 86400000
        ) + 1)
      : 0;
    router.patch(`/leases/${selected.id}`, {
      action: 'update_fitout',
      fitout_enabled: fitoutEditEnabled,
      fitout_to_date: fitoutEditEnabled ? fitoutEditToDate : null,
      fitout_days: days,
    }, { onSuccess: () => setEditingFitout(false) });
  };
  const submit = (e) => {
    e.preventDefault();
    const closeModal = () => {
      reset();
      setShowModal(false);
      setEditingLeaseId(null);
      setTenantSearch('');
      setShowTenantDropdown(false);
    };
    if (editingLeaseId) {
      router.patch(`/leases/${editingLeaseId}`, {
        action: 'edit',
        tenant_id: data.tenant_id,
        unit_id: data.unit_id,
        start_date: data.start_date,
        end_date: data.end_date,
        duration_months: data.duration_months,
        payment_cycle: data.payment_cycle,
        possession_date: possessionDate || data.start_date,
        rent_start_date: rentStartDate || data.start_date,
        fitout_enabled: fitoutEnabled,
        fitout_to_date: fitoutEnabled ? fitoutToDate : null,
        fitout_days: summary.fitoutDays || 0,
        monthly_rent: data.monthly_rent,
        deposit: data.deposit,
        wht_rate: Number(whtRate || 0),
        vat_rate: Number(vatRate || 0),
        terms: data.terms,
      }, { onSuccess: closeModal });
      return;
    }
    post('/leases', {
      data: {
        ...data,
        possession_date: possessionDate || data.start_date,
        rent_start_date: rentStartDate || data.start_date,
        fitout_enabled: fitoutEnabled,
        fitout_to_date: fitoutEnabled ? fitoutToDate : null,
        fitout_days: summary.fitoutDays || 0,
        tenant_mode: 'existing',
        wht_rate: Number(whtRate || 0),
        service_charge_rate: 0,
        vat_rate: Number(vatRate || 0),
      },
      onSuccess: closeModal,
    });
  };

  useEffect(() => { setEditingFitout(false); }, [selected?.id]);

  const isPending = selected ? ['pending_accountant', 'pending_pm', 'rejected'].includes(selected.status) : false;
  const approvalLog = useMemo(() => parseApprovalLog(selected?.approval_log), [selected]);
  const leaseProgress = useMemo(() => (selected ? calcLeaseProgress(selected) : { pct: 0, daysLeft: 0 }), [selected]);
  const paymentSchedule = useMemo(() => (selected ? buildPaymentSchedule(selected, isPending) : []), [selected, isPending]);
  const selectedLeaseCurrency = resolveCurrency(selected?.currency || selected?.unit?.currency);

  const renewFromDrawer = () => {
    if (!selected) return;
    const lease = selected;
    openLeaseModal();
    setData('tenant_id', lease.tenant_id || lease.tenant?.id || '');
    setData('unit_id', lease.unit_id || lease.unit?.id || '');
    setPossessionDate(lease.end_date || lease.start_date || '2026-04-01');
    setRentStartDate(lease.end_date || lease.start_date || '2026-04-01');
    setData('start_date', lease.end_date || lease.start_date || '2026-04-01');
    setData('duration_months', lease.duration_months || 12);
    setData('payment_cycle', lease.payment_cycle || 3);
    setData('monthly_rent', lease.monthly_rent || '');
    setData('deposit', lease.deposit || '');
    setData('terms', lease.terms || '');
    setEditingLeaseId(null);
    setSelected(null);
  };

  return (
    <AppLayout title="Leases" subtitle={`${leases.length} active`}>
      <Head title="Leases" />

      <div className="tn-stats-row">
        <div className="tn-stat"><div className="tn-stat-value">{leases.length}</div><div className="tn-stat-label">Active Leases</div></div>
        <div className="tn-stat-divider"></div>
        <div className="tn-stat"><div className="tn-stat-value" style={{color:'var(--amber)'}}>{counts.expiring}</div><div className="tn-stat-label">Expiring in 60 Days</div></div>
        <div className="tn-stat-divider"></div>
        <div className="tn-stat"><div className="tn-stat-value" style={{color:'var(--red)'}}>{counts.overdue}</div><div className="tn-stat-label">Payment Overdue</div></div>
        <div className="tn-stat-divider"></div>
        <div className="tn-stat"><div className="tn-stat-value" style={{color:'var(--green)'}}>{`${formatCompactMoney(monthlyRevenueByCurrency.USD, 'USD')} · ${formatCompactMoney(monthlyRevenueByCurrency.TZS, 'TZS')}`}</div><div className="tn-stat-label">Monthly Revenue</div></div>
        <div className="tn-stat-divider"></div>
        <div className="tn-stat"><div className="tn-stat-value" style={{color:'var(--accent)'}}>{`${formatCompactMoney(annualContractByCurrency.USD, 'USD')} · ${formatCompactMoney(annualContractByCurrency.TZS, 'TZS')}`}</div><div className="tn-stat-label">Annual Contract Value</div></div>
      </div>

      <div className="toolbar">
        <div className="filters">
          {[['all','All'],['active','Active'],['pending_accountant','Pending Approval'],['expiring','Expiring'],['overdue','Overdue'],['rejected','Rejected']].map(([f,l])=>(
            <button key={f} className={`filter-pill ${filter===f?'active':''}`} onClick={()=>setFilter(f)}>{l} <span className="pill-count">{counts[f]||0}</span></button>
          ))}
        </div>
        <div style={{display:'flex',gap:8}}>
          <div className="search-box" style={{width:190}}>
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <input type="text" placeholder="Search leases…" value={search} onChange={e=>setSearch(e.target.value)} />
          </div>
          <button className="btn-primary" onClick={openLeaseModal}>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            New Lease
          </button>
        </div>
      </div>

      <div className="card">
        <table className="data-table">
          <thead><tr><th>Tenant</th><th>Unit</th><th>Start</th><th>End</th><th>Cycle</th><th>Rent/mo</th><th>Approval</th><th>Status</th><th></th></tr></thead>
          <tbody>
            {filtered.map(l => (
              <tr key={l.id} onClick={()=>setSelected(l)}>
                <td><div className="tenant-cell"><div className="t-avatar" style={{background:l.tenant?.color,color:l.tenant?.text_color}}>{l.tenant?.initials}</div><div><div style={{fontWeight:600}}>{l.tenant?.name}</div><div style={{fontSize:'11.5px',color:'var(--text-muted)'}}>{l.tenant?.email}</div></div></div></td>
                <td style={{fontWeight:700,color:'var(--accent)'}}>{l.unit?.unit_number}</td>
                <td style={{fontSize:'12.5px',color:'var(--text-secondary)'}}>{l.start_date}</td>
                <td style={{fontSize:'12.5px',color:'var(--text-secondary)'}}>{l.end_date}</td>
                <td><span className={`lease-cycle-pill c${l.payment_cycle}`}>{CYCLE_LABELS[l.payment_cycle]}</span></td>
                <td style={{fontWeight:600}}>{formatMoney(l.monthly_rent, l.currency || l.unit?.currency)}</td>
                <td style={{fontSize:'11.5px',color:l.status==='active'?'var(--green)':l.status==='rejected'?'var(--red)':'var(--amber)',fontWeight:600}}>
                  {l.status==='active'||l.status==='expiring'||l.status==='overdue'?'✓ Approved':(l.status==='pending_accountant'||l.status==='pending_pm')?'⏳ Pending':l.status==='rejected'?'✕ Rejected':'—'}
                </td>
                <td><span className={`badge ${STATUS_BADGE[l.status]||'pending'}`}>{STATUS_LABEL_MAP[l.status] ?? l.status?.replace('_',' ')}</span></td>
                <td><button className="action-dots" onClick={e=>{e.stopPropagation();setSelected(l)}}>···</button></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Lease Drawer */}
      <div className={`drawer-overlay lease-drawer-overlay ${selected?'open':''}`} onClick={e=>e.target===e.currentTarget&&setSelected(null)}>
        <div className="drawer lease-drawer" style={{width:500}}>
          {selected && <>
            <div className="drawer-header ldr-header">
              <div>
                <div className="ldr-unit-badge">{selected.unit?.unit_number || '—'}</div>
                <div className="ldr-tenant-name">{selected.tenant?.name || '—'}</div>
                <div className="ldr-sub">{selected.duration_months}-month lease · {(CYCLE_PAYMENTS[selected.payment_cycle] || `${selected.payment_cycle}-mo`)} payments</div>
              </div>
              <button className="drawer-close" onClick={()=>setSelected(null)}>✕</button>
            </div>
            <div className="drawer-body ldr-body">
              {isPending && (
                <div className="drawer-section ldr-section">
                  <div className="drawer-section-title ldr-section-title">Approval Workflow</div>

                  <div className="apv-stepper">
                    {[{ label: 'Submitted', role: 'Manager / Staff' }, { label: 'Superuser Approval', role: 'Superuser' }].map((step, i) => {
                      const isRejected = selected.status === 'rejected';
                      const isPendingStep = ['pending_accountant', 'pending_pm'].includes(selected.status);
                      const isDone = !isRejected && i === 0 && isPendingStep;
                      const isActive = !isRejected && i === 1 && isPendingStep;
                      const cls = isDone ? 'done' : isActive ? 'active' : isRejected && i === 1 ? 'rejected' : '';
                      const icon = isDone ? '✓' : isRejected && i === 1 ? '✕' : String(i + 1);

                      return (
                        <div key={step.label} className={`apv-step ${cls}`}>
                          <div className="apv-dot">{icon}</div>
                          <div className="apv-label">{step.label}</div>
                          <div className="apv-sublabel">{step.role}</div>
                        </div>
                      );
                    })}
                  </div>

                  <div className={`apv-action-banner ${selected.status === 'rejected' ? 'rejected' : 'can-approve'}`}>
                    {['pending_accountant', 'pending_pm'].includes(selected.status) && (
                      <>
                        <div className="apv-banner-title">Awaiting Superuser Approval</div>
                        <div className="apv-banner-sub">This lease is pending approval. Only the superuser can approve or reject leases.</div>
                        {user?.role === 'superuser' && (
                          <div className="apv-banner-actions">
                            <button className="apv-btn-approve" onClick={() => approve(selected, 'approve_superuser')}>Approve</button>
                            <button className="apv-btn-reject" onClick={() => approve(selected, 'reject')}>Reject</button>
                          </div>
                        )}
                      </>
                    )}

                    {selected.status === 'rejected' && (
                      <>
                        <div className="apv-banner-title" style={{color:'var(--red)'}}>Lease Rejected</div>
                        <div className="apv-banner-sub">This lease was rejected and needs to be resubmitted for review.</div>
                        <div className="apv-banner-actions">
                          <button className="apv-btn-approve" style={{background:'var(--amber)',color:'#fff'}} onClick={()=>approve(selected,'resubmit')}>Resubmit for Review</button>
                        </div>
                      </>
                    )}
                  </div>
                </div>
              )}

              <div className="drawer-section ldr-section" style={{opacity: isPending ? 0.45 : 1}}>
                <div className="drawer-section-title ldr-section-title">Lease Duration</div>
                <div className="ldr-timeline-bar">
                  <div className="ldr-timeline-labels"><span>{selected.start_date}</span><span>{selected.end_date}</span></div>
                  <div className="ldr-timeline-track">
                    <div className={`ldr-timeline-fill ${leaseProgress.pct >= 90 ? 'danger' : leaseProgress.pct >= 70 ? 'warn' : ''}`} style={{width:`${isPending ? 0 : leaseProgress.pct}%`}}></div>
                  </div>
                  <div className="ldr-timeline-caption">
                    {isPending
                      ? 'Lease not yet active — pending approval'
                      : `${leaseProgress.pct.toFixed(0)}% elapsed — ${leaseProgress.daysLeft <= 0 ? 'Expired' : `${leaseProgress.daysLeft} days remaining`}`}
                  </div>
                </div>
              </div>

              <div className="drawer-section ldr-section">
                <div className="drawer-section-title ldr-section-title">Lease Details</div>
                <div className="kv-grid ldr-kv-grid">
                  <div className="kv ldr-kv"><div className="kv-label ldr-kv-label">Status</div><div className={`kv-value ldr-kv-value ${STATUS_KV_MAP[selected.status] || ''}`}>{STATUS_LABEL_MAP[selected.status] || selected.status}</div></div>
                  <div className="kv ldr-kv"><div className="kv-label ldr-kv-label">Payment Cycle</div><div className="kv-value ldr-kv-value" style={{fontSize:'12.5px'}}>{CYCLE_PAYMENTS[selected.payment_cycle] || `${selected.payment_cycle} months`}</div></div>
                  <div className="kv ldr-kv"><div className="kv-label ldr-kv-label">Monthly Rent</div><div className="kv-value ldr-kv-value">{formatMoney(selected.monthly_rent, selectedLeaseCurrency)}</div></div>
                  <div className="kv ldr-kv"><div className="kv-label ldr-kv-label">Instalment</div><div className="kv-value ldr-kv-value accent">{formatMoney(selected.monthly_rent * selected.payment_cycle, selectedLeaseCurrency)}</div></div>
                  <div className="kv ldr-kv"><div className="kv-label ldr-kv-label">Annual Value</div><div className="kv-value ldr-kv-value">{formatMoney(selected.monthly_rent * 12, selectedLeaseCurrency)}</div></div>
                  <div className="kv ldr-kv"><div className="kv-label ldr-kv-label">Security Deposit</div><div className="kv-value ldr-kv-value">{formatMoney(selected.deposit, selectedLeaseCurrency)}</div></div>
                </div>
              </div>

              <div className="drawer-section ldr-section">
                <div className="drawer-section-title ldr-section-title" style={{display:'flex',alignItems:'center',justifyContent:'space-between'}}>
                  <span>Fit-Out Period</span>
                  {user?.role === 'superuser' && selected?.status === 'active' && !editingFitout && (
                    <button className="btn-secondary" style={{fontSize:11,padding:'3px 10px',height:'auto'}} type="button" onClick={openFitoutEdit}>Edit</button>
                  )}
                </div>
                {editingFitout ? (
                  <div style={{background:'var(--bg-elevated)',borderRadius:9,padding:'13px 14px'}}>
                    <div style={{display:'flex',alignItems:'center',gap:10,marginBottom:10}}>
                      <label style={{fontSize:13,fontWeight:500,color:'var(--text-secondary)',cursor:'pointer',display:'flex',alignItems:'center',gap:7}}>
                        <input type="checkbox" checked={fitoutEditEnabled} onChange={e => setFitoutEditEnabled(e.target.checked)} style={{width:15,height:15}} />
                        Fit-Out Enabled
                      </label>
                    </div>
                    {fitoutEditEnabled && (
                      <div className="form-row" style={{marginBottom:10}}>
                        <div className="form-group">
                          <label className="form-label">Fit-Out End Date <span style={{color:'var(--text-muted)',fontSize:11}}>Rent starts day after</span></label>
                          <input className="form-input" type="date" value={fitoutEditToDate} onChange={e => setFitoutEditToDate(e.target.value)} />
                        </div>
                        {fitoutEditToDate && selected?.possession_date && (
                          <div className="form-group">
                            <label className="form-label">Duration</label>
                            <div style={{background:'var(--bg-surface)',border:'1px solid var(--border)',borderRadius:8,padding:'8px 12px',fontSize:'13.5px',color:'var(--amber)',fontWeight:600}}>
                              {Math.max(0, Math.round((new Date(`${fitoutEditToDate}T00:00:00`) - new Date(`${selected.possession_date}T00:00:00`)) / 86400000) + 1)} days
                            </div>
                          </div>
                        )}
                      </div>
                    )}
                    <div style={{display:'flex',gap:8}}>
                      <button className="btn-primary" style={{fontSize:12,padding:'5px 14px',height:'auto'}} type="button" onClick={saveFitout}>Save</button>
                      <button className="btn-secondary" style={{fontSize:12,padding:'5px 14px',height:'auto'}} type="button" onClick={() => setEditingFitout(false)}>Cancel</button>
                    </div>
                  </div>
                ) : (
                  <div className="kv-grid ldr-kv-grid">
                    <div className="kv ldr-kv"><div className="kv-label ldr-kv-label">Fit-Out</div><div className="kv-value ldr-kv-value" style={{color: selected.fitout_enabled ? 'var(--amber)' : 'var(--text-muted)'}}>{selected.fitout_enabled ? 'Enabled' : 'None'}</div></div>
                    {selected.fitout_enabled && <>
                      <div className="kv ldr-kv"><div className="kv-label ldr-kv-label">Possession Date</div><div className="kv-value ldr-kv-value">{fmtDateShort(selected.possession_date)}</div></div>
                      <div className="kv ldr-kv"><div className="kv-label ldr-kv-label">Fit-Out Until</div><div className="kv-value ldr-kv-value">{fmtDateShort(selected.fitout_to_date)}</div></div>
                      <div className="kv ldr-kv"><div className="kv-label ldr-kv-label">Fit-Out Days</div><div className="kv-value ldr-kv-value" style={{color:'var(--amber)',fontWeight:700}}>{selected.fitout_days} days</div></div>
                    </>}
                  </div>
                )}
              </div>

              <div className="drawer-section ldr-section" style={{opacity: isPending ? 0.45 : 1}}>
                <div className="drawer-section-title ldr-section-title">Payment Schedule</div>
                <div className="card" style={{overflow:'hidden'}}>
                  <table className="ldr-schedule-table">
                    <thead>
                      <tr><th>#</th><th>Due Date</th><th>Period Covered</th><th>Amount</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                      {paymentSchedule.map((row) => (
                        <tr key={row.installNum}>
                          <td style={{color:'var(--text-muted)',fontSize:'12px'}}>{row.installNum}</td>
                          <td style={{fontWeight:600,fontSize:'13px'}}>{row.dueDate}</td>
                          <td style={{fontSize:'12px',color:'var(--text-muted)'}}>{row.period}</td>
                          <td style={{fontWeight:700,fontVariantNumeric:'tabular-nums'}}>{formatMoney(row.amount, selectedLeaseCurrency)}</td>
                          <td>
                            <span style={{fontSize:'11.5px',fontWeight:600,color:row.status==='paid'?'var(--green)':row.status==='overdue'?'var(--red)':row.status==='partially_paid'?'var(--amber)':row.status==='upcoming'?'var(--amber)':row.status==='unpaid'?'var(--text-secondary)':'var(--text-muted)'}}>
                              {row.status === 'paid'
                                ? 'Paid'
                                : row.status === 'overdue'
                                  ? 'Overdue'
                                  : row.status === 'partially_paid'
                                    ? 'Partially Paid'
                                    : row.status === 'unpaid'
                                      ? 'Unpaid'
                                      : row.status === 'upcoming'
                                        ? 'Due Soon'
                                        : 'Scheduled'}
                            </span>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>

              <div className="drawer-section ldr-section">
                <div className="drawer-section-title ldr-section-title">Tenant</div>
                <div style={{background:'var(--bg-elevated)',borderRadius:9,padding:'13px 14px',display:'flex',alignItems:'center',gap:12}}>
                  <div style={{width:40,height:40,borderRadius:'50%',background:selected.tenant?.color,color:selected.tenant?.text_color,display:'flex',alignItems:'center',justifyContent:'center',fontSize:14,fontWeight:700,flexShrink:0}}>{selected.tenant?.initials || '—'}</div>
                  <div>
                    <div style={{fontSize:14,fontWeight:600}}>{selected.tenant?.name || '—'}</div>
                    <div style={{fontSize:12,color:'var(--text-muted)'}}>{selected.tenant?.email || '—'}</div>
                    <div style={{fontSize:12,color:'var(--text-muted)'}}>{selected.tenant?.phone || '—'}</div>
                  </div>
                </div>
              </div>

              <div className="drawer-section ldr-section">
                <div className="drawer-section-title ldr-section-title">Lease Terms</div>
                <div style={{background:'var(--bg-elevated)',borderRadius:9,padding:'13px 14px',fontSize:13,color:'var(--text-secondary)',lineHeight:1.7}}>{selected.terms || 'No special lease terms.'}</div>
              </div>

              {isPending && approvalLog.length > 0 && (
                <div className="drawer-section ldr-section">
                  <div className="drawer-section-title ldr-section-title">Approval History</div>
                  {approvalLog.map((e, i) => (
                    <div key={`${e.date}-${i}`} className="apv-log-item">
                      <div className={`apv-log-dot ${e.action==='approved' ? 'green' : e.action==='rejected' ? 'red' : e.action==='submitted' ? 'accent' : 'amber'}`}></div>
                      <div>
                        <div className="apv-log-text">{e.text}</div>
                        <div className="apv-log-meta">{e.by} · {e.date}</div>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>

            <div className="drawer-footer ldr-footer">
              {isPending ? (
                <>
                  {(user?.role === 'superuser' || user?.role === 'manager') && (
                    <button className="btn-primary" style={{flex:1,justifyContent:'center'}} onClick={()=>openEditModal(selected)}>Edit Lease</button>
                  )}
                  <button className="btn-secondary" style={{flex:1,justifyContent:'center'}} onClick={()=>setSelected(null)}>Download Draft</button>
                  <button className="btn-danger" onClick={() => setShowDeleteConfirm(true)}>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                  </button>
                </>
              ) : (
                <>
                  {user?.role === 'superuser' && (
                    <button className="btn-primary" style={{flex:1,justifyContent:'center'}} onClick={()=>openEditModal(selected)}>Edit Lease</button>
                  )}
                  <button className="btn-secondary" onClick={renewFromDrawer}>Renew</button>
                  <button className="btn-secondary" onClick={()=>setSelected(null)}>Download</button>
                  <button className="btn-danger" onClick={() => setShowDeleteConfirm(true)}>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                  </button>
                </>
              )}
            </div>
          </>}
        </div>
      </div>

      {/* Delete Confirmation Modal */}
      <div className={`modal-overlay ${showDeleteConfirm ? 'open' : ''}`} onClick={e => e.target === e.currentTarget && setShowDeleteConfirm(false)}>
        <div className="modal" style={{width: 420}}>
          <div className="modal-header">
            <div className="modal-title">Delete Lease</div>
            <button className="modal-close" onClick={() => setShowDeleteConfirm(false)}>✕</button>
          </div>
          <div className="modal-body">
            <p style={{margin: 0, fontSize: 14, color: 'var(--text-secondary)', lineHeight: 1.6}}>
              Are you sure you want to delete the lease for{' '}
              <strong>{selected?.tenant?.name}</strong> on unit{' '}
              <strong>{selected?.unit?.unit_number}</strong>?{' '}
              This action cannot be undone.
            </p>
          </div>
          <div className="modal-footer">
            <button className="btn-ghost" onClick={() => setShowDeleteConfirm(false)}>Cancel</button>
            <button className="btn-danger" onClick={() => {
              router.delete(`/leases/${selected.id}`);
              setShowDeleteConfirm(false);
              setSelected(null);
            }}>Delete Lease</button>
          </div>
        </div>
      </div>

      {/* New Lease Modal */}
      <div className={`modal-overlay ${showModal?'open':''}`} onClick={e=>e.target===e.currentTarget&&setShowModal(false)}>
        <div className="modal" style={{width:'min(640px, calc(100vw - 24px))',maxHeight:'calc(100dvh - 24px)',display:'flex',flexDirection:'column',overflow:'hidden'}}>
          <div className="modal-header" style={{flexShrink:0}}><div className="modal-title">{editingLeaseId ? `Edit Lease — Unit ${selected?.unit?.unit_number || ''}` : 'New Lease Agreement'}</div><button className="modal-close" onClick={()=>{setShowModal(false);setEditingLeaseId(null);}}>✕</button></div>
          <form onSubmit={submit} style={{display:'flex',flexDirection:'column',flex:1,minHeight:0}}>
            <div className="modal-body" style={{overflowY:'auto',flex:1,minHeight:0}}>
              <div style={{marginBottom:14}}>
                <label className="form-label" style={{marginBottom:8,display:'block'}}>Tenant *</label>
                <div style={{position:'relative'}}>
                  <div className="search-box" style={{width:'100%',background:'var(--bg-elevated)'}}>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                    <input
                      type="text"
                      placeholder="Search tenants..."
                      value={tenantSearch}
                      onFocus={()=>setShowTenantDropdown(true)}
                      onBlur={()=>setTimeout(()=>setShowTenantDropdown(false),120)}
                      onChange={(e)=>{setTenantSearch(e.target.value);setShowTenantDropdown(true);}}
                    />
                  </div>

                  <div className={`nl-tenant-dropdown ${showTenantDropdown?'open':''}`}>
                    {filteredTenants.length === 0 && <div className="nl-no-results">No tenants found</div>}
                    {filteredTenants.map(t => {
                      const tenantLease = leases.find(l => String(l.tenant_id) === String(t.id) && ['active','expiring','overdue','pending_accountant','pending_pm'].includes(l.status));
                      const unitLabel = tenantLease?.unit?.unit_number ? `Current: ${tenantLease.unit.unit_number}` : 'No active lease';
                      return (
                        <button type="button" key={t.id} className="nl-tenant-option" onMouseDown={(e)=>e.preventDefault()} onClick={()=>selectTenant(t)}>
                          <div className="nl-opt-avatar" style={{background:t.color,color:t.text_color}}>{t.initials}</div>
                          <div style={{flex:1,minWidth:0,textAlign:'left'}}>
                            <div className="nl-opt-name">{t.name}</div>
                            <div className="nl-opt-meta">{t.email} · {t.phone || '—'}</div>
                          </div>
                          <span className="nl-opt-unit">{unitLabel}</span>
                        </button>
                      );
                    })}
                  </div>
                </div>

                {currentTenant && (
                  <div className="nl-selected-card" style={{marginTop:10}}>
                    <div className="nl-selected-avatar" style={{background:currentTenant.color,color:currentTenant.text_color}}>{currentTenant.initials}</div>
                    <div style={{flex:1,minWidth:0}}>
                      <div className="nl-selected-name">{currentTenant.name}</div>
                      <div className="nl-selected-meta">{currentTenant.email} · {currentTenant.phone || '—'}</div>
                    </div>
                    <button type="button" className="nl-selected-clear" onClick={()=>setData('tenant_id','')} title="Clear selection">✕</button>
                  </div>
                )}
              </div>

              <div style={{height:1,background:'var(--border-subtle)',margin:'16px 0'}}></div>

              <div className="form-row">
                <div className="form-group"><label className="form-label">Unit *</label>
                  <select className="form-input form-select" value={data.unit_id} onChange={e=>onUnitChange(e.target.value)} required>
                    <option value="">Select unit…</option>
                    {unitsByFloor.map(([floor, floorUnits]) => (
                      <optgroup key={floor} label={`Floor ${floor}`}>
                        {floorUnits.map(u => <option key={u.id} value={u.id}>{u.unit_number} — {u.type} ({formatMoney(u.rent, u.currency)}/mo)</option>)}
                      </optgroup>
                    ))}
                  </select>
                </div>
                <div className="form-group"><label className="form-label">Payment Cycle *</label>
                  <select className="form-input form-select" value={data.payment_cycle} onChange={e=>setData('payment_cycle',e.target.value)}>
                    <option value={3}>Quarterly — every 3 months</option><option value={4}>Every 4 months</option><option value={6}>Semi-Annual — every 6 months</option><option value={12}>Annual — once a year</option>
                  </select>
                </div>
              </div>

              <div className="form-row">
                <div className="form-group"><label className="form-label">Possession Date * <span style={{color:'var(--text-muted)',fontWeight:400,fontSize:11}}>Service charge starts here</span></label><input className="form-input" type="date" value={possessionDate} onChange={e=>setPossessionDate(e.target.value)} required /></div>
                <div className="form-group"><label className="form-label">Duration <span style={{color:'var(--text-muted)',fontWeight:400}}>(min. 1 year)</span></label>
                  <select className="form-input form-select" value={data.duration_months} onChange={e=>setData('duration_months',e.target.value)}>
                    {DURATION_OPTIONS.map(opt => <option key={opt.months} value={opt.months}>{opt.label}</option>)}
                  </select>
                </div>
              </div>

              <div style={{background:'var(--bg-elevated)',borderRadius:10,padding:'14px 16px',marginBottom:12}}>
                <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',marginBottom:10}}>
                  <div>
                    <div style={{fontSize:'13.5px',fontWeight:500}}>Fit-Out Period</div>
                    <div style={{fontSize:12,color:'var(--text-muted)',marginTop:2}}>Rent-free days from possession. Service charge runs from possession date regardless.</div>
                  </div>
                  <button
                    type="button"
                    className={`pref-toggle ${fitoutEnabled ? 'on' : 'off'}`}
                    onClick={() => {
                      const next = !fitoutEnabled;
                      setFitoutEnabled(next);
                      if (next && possessionDate) {
                        const d = new Date(`${possessionDate}T00:00:00`);
                        d.setDate(d.getDate() + 14);
                        setFitoutToDate(d.toISOString().slice(0, 10));
                      }
                      if (!next) {
                        setFitoutToDate('');
                        setRentStartDate(possessionDate || '');
                      }
                    }}
                  />
                </div>
                {fitoutEnabled && (
                  <div style={{display:'grid',gridTemplateColumns:'1fr 1fr',gap:12}}>
                    <div>
                      <label className="form-label">Fit-Out Start</label>
                      <input className="form-input" type="date" value={possessionDate} readOnly style={{opacity:.65,cursor:'default'}} />
                    </div>
                    <div>
                      <label className="form-label">Fit-Out End Date <span style={{color:'var(--text-muted)',fontSize:11}}>Rent starts day after</span></label>
                      <input className="form-input" type="date" value={fitoutToDate} onChange={(e)=>setFitoutToDate(e.target.value)} />
                    </div>
                    <div>
                      <label className="form-label">Fit-Out Duration</label>
                      <div style={{background:'var(--bg-surface)',border:'1px solid var(--border)',borderRadius:8,padding:'8px 12px',fontSize:'13.5px',color:'var(--amber)',fontWeight:600}}>{summary.fitoutDays > 0 ? `${summary.fitoutDays} day${summary.fitoutDays !== 1 ? 's' : ''} rent-free` : '—'}</div>
                    </div>
                    <div>
                      <label className="form-label">Service Charge During Fit-Out</label>
                      <div style={{background:'var(--bg-surface)',border:'1px solid var(--border)',borderRadius:8,padding:'8px 12px',fontSize:'13.5px',color:'var(--amber)',fontWeight:600}}>
                        {summary.fitoutDays > 0
                          ? formatMoney(summary.fitoutExtraSC, selectedUnitCurrency)
                          : '—'}
                      </div>
                    </div>
                  </div>
                )}
              </div>

              <div className="form-row">
                <div className="form-group"><label className="form-label" style={{display:'flex',alignItems:'center',justifyContent:'space-between'}}>Rent Start Date <span style={{fontSize:11,color:'var(--green)',fontWeight:400}}>{fitoutEnabled && summary.fitoutDays > 0 ? `After ${summary.fitoutDays}-day fit-out` : 'Same as possession (no fit-out)'}</span></label><input className="form-input" type="date" value={rentStartDate} readOnly style={{opacity:.65,cursor:'default'}} /></div>
                <div className="form-group"><label className="form-label">Lease End <span style={{color:'var(--text-muted)',fontSize:11}}>(auto-calculated from rent start)</span></label><input className="form-input" type="date" value={data.end_date} readOnly style={{opacity:.65,cursor:'default'}} /></div>
              </div>

              <div className="form-row">
                <div className="form-group"><label className="form-label" style={{display:'flex',alignItems:'center',justifyContent:'space-between'}}>{`Monthly Rent (${selectedUnitCurrency}) *`} {data.unit_id && <span style={{fontSize:11,color:'var(--accent)',fontWeight:400}}>auto-filled from unit</span>}</label><input className="form-input" type="number" value={data.monthly_rent} onChange={e=>setData('monthly_rent',e.target.value)} placeholder={data.unit_id ? '' : 'Set unit first...'} required /></div>
              </div>

              <div className="form-row">
                <div className="form-group"><label className="form-label">{`Security Deposit (${selectedUnitCurrency})`}</label><input className="form-input" type="number" value={data.deposit} onChange={e=>setData('deposit',e.target.value)} placeholder="Auto-calculated" /></div>
                <div className="form-group"><label className="form-label">WHT Rate (%)</label><input className="form-input" type="number" min="0" max="100" value={whtRate} onChange={e=>setWhtRate(Number(e.target.value || 0))} /></div>
                <div className="form-group"><label className="form-label">VAT Rate (%)</label><input className="form-input" type="number" min="0" max="100" value={vatRate} onChange={e=>setVatRate(Number(e.target.value || 0))} /></div>
              </div>

              {summary.rent > 0 && (
                <div className="nl-summary-card" style={{marginBottom:14}}>
                  <div className="nl-summary-row"><span>Monthly Rent</span><strong>{formatMoney(summary.rent, selectedUnitCurrency)}</strong></div>
                  <div className="nl-summary-row"><span>Service Charge (unit flat rate)</span><strong>{formatMoney(summary.serviceCharge, selectedUnitCurrency)}</strong></div>
                  <div className="nl-summary-row" style={{borderTop:'1px solid var(--border)',paddingTop:6,marginTop:4}}><span>Subtotal</span><strong>{formatMoney(summary.subtotal, selectedUnitCurrency)}</strong></div>
                  <div className="nl-summary-row"><span>VAT ({Math.round(vatRate)}%)</span><strong>{formatMoney(summary.vat, selectedUnitCurrency)}</strong></div>
                  <div className="nl-summary-row" style={{borderTop:'1px solid var(--border)',paddingTop:6,marginTop:4}}><span>Gross Total (incl. VAT)</span><strong>{formatMoney(summary.gross, selectedUnitCurrency)}</strong></div>
                  <div className="nl-summary-row"><span style={{color:'var(--red)'}}>Less: WHT ({Math.round(whtRate)}% of rent + service charge, excl. VAT)</span><strong style={{color:'var(--red)'}}>{`(${formatMoney(summary.wht, selectedUnitCurrency)})`}</strong></div>
                  <div className="nl-summary-row" style={{borderTop:'1px solid var(--border)',paddingTop:6,marginTop:4}}><span style={{fontWeight:700}}>Net Payable / month</span><strong style={{fontSize:15}}>{formatMoney(summary.net, selectedUnitCurrency)}</strong></div>
                  <div className="nl-summary-row" style={{color:'var(--text-muted)',fontSize:12}}><span>WHT remittable to TRA / month</span><strong>{formatMoney(summary.wht, selectedUnitCurrency)}</strong></div>
                  <div className="nl-summary-row" style={{borderTop:'1px solid var(--border)',paddingTop:6,marginTop:4}}><span>Instalment (× {summary.cycle} months)</span><strong>{formatMoney(summary.instalment, selectedUnitCurrency)}</strong></div>
                  <div className="nl-summary-row"><span>Security deposit</span><strong>{formatMoney(summary.deposit, selectedUnitCurrency)}</strong></div>
                  <div className="nl-summary-row"><span>Annual value (incl. VAT)</span><strong>{formatMoney(summary.annual, selectedUnitCurrency)}</strong></div>
                  <div className="nl-summary-row"><span>Lease period</span><strong>{summary.period}</strong></div>
                  {fitoutEnabled && summary.fitoutDays > 0 && (
                    <div className="nl-summary-row">
                      <span style={{color:'var(--amber)'}}>First invoice SC extra days</span>
                      <strong style={{color:'var(--amber)'}}>{`${summary.fitoutDays} days · SC ${formatMoney(summary.fitoutExtraSC, selectedUnitCurrency)} + VAT ${formatMoney(summary.fitoutExtraVAT, selectedUnitCurrency)}`}</strong>
                    </div>
                  )}
                </div>
              )}

              <div className="form-row">
                <div className="form-group"><label className="form-label">Special Terms / Notes</label><textarea className="form-input" value={data.terms} onChange={e=>setData('terms',e.target.value)} rows={2} style={{resize:'vertical'}} placeholder="Optional: conditions, inclusions, exclusions…" /></div>
              </div>
            </div>
            <div className="modal-footer">
              <button type="button" className="btn-ghost" onClick={()=>{setShowModal(false);setEditingLeaseId(null);}}>Cancel</button>
              <button type="submit" className="btn-primary" disabled={processing}>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                {editingLeaseId ? 'Save Changes' : 'Create Lease'}
              </button>
            </div>
          </form>
        </div>
      </div>
    </AppLayout>
  );
}
