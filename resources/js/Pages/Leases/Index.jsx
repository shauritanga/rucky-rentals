import { useEffect, useMemo, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm, router } from '@inertiajs/react';

const fmt = (n) => Number(n).toLocaleString();
const CYCLE_LABELS = { 3:'Quarterly · 3mo', 4:'4-Month', 6:'Semi-Annual · 6mo', 12:'Annual' };
const CYCLE_PAYMENTS = { 3:'Quarterly', 4:'4-Month', 6:'Semi-Annual', 12:'Annual' };
const STATUS_BADGE = { active:'active', expiring:'expiring', overdue:'overdue', pending_accountant:'pending_accountant', pending_pm:'pending_pm', rejected:'rejected', terminated:'pending' };
const STATUS_KV_MAP = { active:'green', expiring:'amber', overdue:'red', pending_accountant:'amber', pending_pm:'accent', rejected:'red' };
const STATUS_LABEL_MAP = { active:'Active', expiring:'Expiring Soon', overdue:'Overdue', pending_accountant:'Pending Accountant', pending_pm:'Pending PM', rejected:'Rejected' };
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

export default function LeasesIndex({ leases, tenants, units }) {
  const [filter, setFilter] = useState('all');
  const [search, setSearch] = useState('');
  const [selected, setSelected] = useState(null);
  const [showModal, setShowModal] = useState(false);
  const [leaseMode, setLeaseMode] = useState('existing');
  const [tenantSearch, setTenantSearch] = useState('');
  const [showTenantDropdown, setShowTenantDropdown] = useState(false);

  const { data, setData, post, processing, reset } = useForm({
    tenant_mode:'existing',
    tenant_id:'', unit_id:'', start_date:'2026-04-01', end_date:'2027-04-01',
    duration_months:12, payment_cycle:3, monthly_rent:'', deposit:'', terms:'',
    new_tenant_name:'', new_tenant_email:'', new_tenant_phone:'', new_tenant_national_id:''
  });

  useEffect(() => {
    const nextEndDate = addMonthsISO(data.start_date, data.duration_months);
    if (nextEndDate && nextEndDate !== data.end_date) {
      setData('end_date', nextEndDate);
    }
  }, [data.start_date, data.duration_months, data.end_date, setData]);

  const summary = useMemo(() => {
    const rent = Number(data.monthly_rent) || 0;
    const cycle = Number(data.payment_cycle) || 0;
    const duration = Number(data.duration_months) || 0;
    const instalment = rent * cycle;
    const deposit = Number(data.deposit) || (rent > 0 ? rent * 2 : 0);
    const annual = rent * 12;

    return {
      rent,
      cycle,
      duration,
      instalment,
      deposit,
      annual,
      period: `${fmtDateShort(data.start_date)} -> ${fmtDateShort(data.end_date)} (${duration} months)`,
    };
  }, [data.monthly_rent, data.payment_cycle, data.duration_months, data.deposit, data.start_date, data.end_date]);

  const filtered = leases.filter(l => {
    const matchFilter = filter === 'all' || l.status === filter;
    const q = search.toLowerCase();
    const matchSearch = !q || l.tenant?.name?.toLowerCase().includes(q) || l.unit?.unit_number?.toLowerCase().includes(q);
    return matchFilter && matchSearch;
  });

  const counts = {};
  ['all','active','expiring','overdue','pending_accountant','pending_pm','rejected'].forEach(s => {
    counts[s] = s === 'all' ? leases.length : leases.filter(l=>l.status===s).length;
  });

  const currentTenant = tenants.find(t => String(t.id) === String(data.tenant_id));
  const filteredTenants = tenants.filter(t => {
    const q = tenantSearch.toLowerCase().trim();
    if (!q) return true;
    return t.name?.toLowerCase().includes(q) || t.email?.toLowerCase().includes(q) || t.phone?.toLowerCase().includes(q);
  });

  const unitsByFloor = useMemo(() => {
    const grouped = {};
    units.forEach((u) => {
      const floor = Number(u.floor) || 0;
      if (!grouped[floor]) grouped[floor] = [];
      grouped[floor].push(u);
    });
    return Object.entries(grouped).sort((a, b) => Number(a[0]) - Number(b[0]));
  }, [units]);

  const openLeaseModal = () => {
    reset();
    setLeaseMode('existing');
    setTenantSearch('');
    setShowTenantDropdown(false);
    setData('tenant_mode', 'existing');
    setData('start_date', '2026-04-01');
    setData('duration_months', 12);
    setData('payment_cycle', 3);
    setData('unit_id', '');
    setData('tenant_id', '');
    setData('monthly_rent', '');
    setData('deposit', '');
    setData('terms', '');
    setData('new_tenant_name', '');
    setData('new_tenant_email', '');
    setData('new_tenant_phone', '');
    setData('new_tenant_national_id', '');
    setShowModal(true);
  };

  const selectTenant = (tenant) => {
    setData('tenant_id', tenant.id);
    setData('tenant_mode', 'existing');
    setLeaseMode('existing');
    setTenantSearch('');
    setShowTenantDropdown(false);
  };

  const onUnitChange = (unitId) => {
    setData('unit_id', unitId);
    const u = units.find(x => String(x.id) === String(unitId));
    if (u) {
      setData(d => ({ ...d, monthly_rent: u.rent, deposit: u.rent * 2 }));
    }
  };

  const approve = (lease, action) => router.patch(`/leases/${lease.id}`, { action }, { onSuccess: () => setSelected(s => s ? {...s, status: action==='approve_accountant'?'pending_pm':action==='approve_pm'?'active':s.status} : null) });
  const submit = (e) => {
    e.preventDefault();
    post('/leases', {
      onSuccess: () => {
        reset();
        setShowModal(false);
        setTenantSearch('');
        setShowTenantDropdown(false);
        setLeaseMode('existing');
      }
    });
  };

  const isPending = selected ? ['pending_accountant', 'pending_pm', 'rejected'].includes(selected.status) : false;
  const approvalLog = useMemo(() => parseApprovalLog(selected?.approval_log), [selected]);
  const leaseProgress = useMemo(() => (selected ? calcLeaseProgress(selected) : { pct: 0, daysLeft: 0 }), [selected]);
  const paymentSchedule = useMemo(() => (selected ? buildPaymentSchedule(selected, isPending) : []), [selected, isPending]);

  const renewFromDrawer = () => {
    if (!selected) return;
    const lease = selected;
    openLeaseModal();
    setData('tenant_mode', 'existing');
    setLeaseMode('existing');
    setData('tenant_id', lease.tenant_id || lease.tenant?.id || '');
    setData('unit_id', lease.unit_id || lease.unit?.id || '');
    setData('start_date', lease.end_date || lease.start_date);
    setData('duration_months', lease.duration_months || 12);
    setData('payment_cycle', lease.payment_cycle || 3);
    setData('monthly_rent', lease.monthly_rent || '');
    setData('deposit', lease.deposit || '');
    setData('terms', lease.terms || '');
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
        <div className="tn-stat"><div className="tn-stat-value" style={{color:'var(--green)'}}>$42k</div><div className="tn-stat-label">Monthly Revenue</div></div>
        <div className="tn-stat-divider"></div>
        <div className="tn-stat"><div className="tn-stat-value" style={{color:'var(--accent)'}}>$504k</div><div className="tn-stat-label">Annual Contract Value</div></div>
      </div>

      <div className="toolbar">
        <div className="filters">
          {[['all','All'],['active','Active'],['pending_accountant','Awaiting Accountant'],['pending_pm','Awaiting PM'],['expiring','Expiring'],['overdue','Overdue'],['rejected','Rejected']].map(([f,l])=>(
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
                <td style={{fontWeight:600}}>${fmt(l.monthly_rent)}</td>
                <td style={{fontSize:'11.5px',color:l.status==='active'?'var(--green)':l.status==='rejected'?'var(--red)':'var(--amber)',fontWeight:600}}>
                  {l.status==='active'||l.status==='expiring'||l.status==='overdue'?'✓ Approved':l.status==='pending_accountant'?'⏳ Accountant':l.status==='pending_pm'?'⏳ Prop. Manager':l.status==='rejected'?'✕ Rejected':'—'}
                </td>
                <td><span className={`badge ${STATUS_BADGE[l.status]||'pending'}`}>{l.status?.replace('_',' ')}</span></td>
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
                    {[{ label: 'Accountant Review', role: 'Accountant' }, { label: 'PM Approval', role: 'Property Manager' }].map((step, i) => {
                      const isRejected = selected.status === 'rejected';
                      const currentStep = selected.status === 'pending_pm' ? 1 : 0;
                      const isDone = !isRejected && i < currentStep;
                      const isActive = !isRejected && i === currentStep;
                      const cls = isDone ? 'done' : isActive ? 'active' : isRejected && i === currentStep ? 'rejected' : '';
                      const icon = isDone ? '✓' : isRejected && i === currentStep ? '✕' : String(i + 1);

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
                    {selected.status === 'pending_accountant' && (
                      <>
                        <div className="apv-banner-title">Awaiting Accountant Approval</div>
                        <div className="apv-banner-sub">The accountant must review and verify the lease financials — rent amount, deposit, and payment schedule — before it proceeds to the Property Manager.</div>
                        <div className="apv-banner-actions">
                          <button className="apv-btn-approve" onClick={()=>approve(selected,'approve_accountant')}>Approve as Accountant</button>
                          <button className="apv-btn-reject" onClick={()=>approve(selected,'reject')}>Reject</button>
                        </div>
                      </>
                    )}

                    {selected.status === 'pending_pm' && (
                      <>
                        <div className="apv-banner-title">Awaiting Property Manager Approval</div>
                        <div className="apv-banner-sub">Accountant approval complete. The Property Manager must give final sign-off before the lease becomes active.</div>
                        <div className="apv-banner-actions">
                          <button className="apv-btn-approve" onClick={()=>approve(selected,'approve_pm')}>Approve as Property Manager</button>
                          <button className="apv-btn-reject" onClick={()=>approve(selected,'reject')}>Reject</button>
                        </div>
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
                  <div className="kv ldr-kv"><div className="kv-label ldr-kv-label">Monthly Rent</div><div className="kv-value ldr-kv-value">${fmt(selected.monthly_rent)}</div></div>
                  <div className="kv ldr-kv"><div className="kv-label ldr-kv-label">Instalment</div><div className="kv-value ldr-kv-value accent">${fmt(selected.monthly_rent * selected.payment_cycle)}</div></div>
                  <div className="kv ldr-kv"><div className="kv-label ldr-kv-label">Annual Value</div><div className="kv-value ldr-kv-value">${fmt(selected.monthly_rent * 12)}</div></div>
                  <div className="kv ldr-kv"><div className="kv-label ldr-kv-label">Security Deposit</div><div className="kv-value ldr-kv-value">${fmt(selected.deposit)}</div></div>
                </div>
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
                          <td style={{fontWeight:700,fontVariantNumeric:'tabular-nums'}}>${fmt(row.amount)}</td>
                          <td>
                            <span style={{fontSize:'11.5px',fontWeight:600,color:row.status==='paid'?'var(--green)':row.status==='overdue'?'var(--red)':row.status==='upcoming'?'var(--amber)':'var(--text-muted)'}}>
                              {row.status === 'paid' ? 'Paid' : row.status === 'overdue' ? 'Overdue' : row.status === 'upcoming' ? 'Due Soon' : 'Scheduled'}
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
                  <button className="btn-secondary" style={{flex:1,justifyContent:'center'}} onClick={()=>setSelected(null)}>Download Draft</button>
                  <button className="btn-danger" onClick={()=>{router.delete(`/leases/${selected.id}`);setSelected(null);}}>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                  </button>
                </>
              ) : (
                <>
                  <button className="btn-primary" style={{flex:1,justifyContent:'center'}} onClick={()=>setSelected(null)}>Edit Lease</button>
                  <button className="btn-secondary" onClick={renewFromDrawer}>Renew</button>
                  <button className="btn-secondary" onClick={()=>setSelected(null)}>Download</button>
                  <button className="btn-danger" onClick={()=>{router.delete(`/leases/${selected.id}`);setSelected(null);}}>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                  </button>
                </>
              )}
            </div>
          </>}
        </div>
      </div>

      {/* New Lease Modal */}
      <div className={`modal-overlay ${showModal?'open':''}`} onClick={e=>e.target===e.currentTarget&&setShowModal(false)}>
        <div className="modal" style={{width:560,maxHeight:'92vh',display:'flex',flexDirection:'column'}}>
          <div className="modal-header" style={{flexShrink:0}}><div className="modal-title">New Lease Agreement</div><button className="modal-close" onClick={()=>setShowModal(false)}>✕</button></div>
          <form onSubmit={submit}>
            <div className="modal-body" style={{overflowY:'auto',flex:1}}>
              <div style={{marginBottom:18}}>
                <label className="form-label" style={{marginBottom:8,display:'block'}}>Tenant *</label>
                <div className="nl-toggle-bar">
                  <button type="button" className={`nl-toggle-btn ${leaseMode==='existing'?'active':''}`} onClick={()=>{setLeaseMode('existing');setData('tenant_mode','existing');}}>
                    Existing Tenant
                  </button>
                  <button type="button" className={`nl-toggle-btn ${leaseMode==='new'?'active':''}`} onClick={()=>{setLeaseMode('new');setData('tenant_mode','new');setData('tenant_id','');}}>
                    New Tenant
                  </button>
                </div>
              </div>

              {leaseMode === 'existing' && (
                <div style={{marginBottom:14}}>
                  <div style={{position:'relative'}}>
                    <div className="search-box" style={{width:'100%',background:'var(--bg-elevated)'}}>
                      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                      <input
                        type="text"
                        placeholder="Search existing tenants..."
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
              )}

              {leaseMode === 'new' && (
                <div style={{marginBottom:14}}>
                  <div className="form-row">
                    <div className="form-group"><label className="form-label">Full Name *</label><input className="form-input" value={data.new_tenant_name} onChange={e=>setData('new_tenant_name',e.target.value)} placeholder="e.g. Cynthia Oloo" /></div>
                    <div className="form-group"><label className="form-label">National ID</label><input className="form-input" value={data.new_tenant_national_id} onChange={e=>setData('new_tenant_national_id',e.target.value)} placeholder="e.g. KE-12345678" /></div>
                  </div>
                  <div className="form-row">
                    <div className="form-group"><label className="form-label">Email *</label><input className="form-input" type="email" value={data.new_tenant_email} onChange={e=>setData('new_tenant_email',e.target.value)} placeholder="tenant@email.com" /></div>
                    <div className="form-group"><label className="form-label">Phone *</label><input className="form-input" value={data.new_tenant_phone} onChange={e=>setData('new_tenant_phone',e.target.value)} placeholder="+254 7xx xxx xxx" /></div>
                  </div>
                </div>
              )}

              <div style={{height:1,background:'var(--border-subtle)',margin:'16px 0'}}></div>

              <div className="form-row">
                <div className="form-group"><label className="form-label">Unit *</label>
                  <select className="form-input form-select" value={data.unit_id} onChange={e=>onUnitChange(e.target.value)} required>
                    <option value="">Select unit…</option>
                    {unitsByFloor.map(([floor, floorUnits]) => (
                      <optgroup key={floor} label={`Floor ${floor}`}>
                        {floorUnits.map(u => <option key={u.id} value={u.id}>{u.unit_number} — {u.type} (${fmt(u.rent)}/mo)</option>)}
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
                <div className="form-group"><label className="form-label">Start Date *</label><input className="form-input" type="date" value={data.start_date} onChange={e=>setData('start_date',e.target.value)} required /></div>
                <div className="form-group"><label className="form-label">Duration <span style={{color:'var(--text-muted)',fontWeight:400}}>(min. 1 year)</span></label>
                  <select className="form-input form-select" value={data.duration_months} onChange={e=>setData('duration_months',e.target.value)}>
                    {DURATION_OPTIONS.map(opt => <option key={opt.months} value={opt.months}>{opt.label}</option>)}
                  </select>
                </div>
              </div>

              <div className="form-row">
                <div className="form-group"><label className="form-label">Lease End <span style={{color:'var(--text-muted)',fontSize:11}}>(auto-calculated)</span></label><input className="form-input" type="date" value={data.end_date} readOnly style={{opacity:.65,cursor:'default'}} /></div>
                <div className="form-group"><label className="form-label" style={{display:'flex',alignItems:'center',justifyContent:'space-between'}}>Monthly Rent ($) * {data.unit_id && <span style={{fontSize:11,color:'var(--accent)',fontWeight:400}}>auto-filled from unit</span>}</label><input className="form-input" type="number" value={data.monthly_rent} onChange={e=>setData('monthly_rent',e.target.value)} placeholder={data.unit_id ? '' : 'Set unit first...'} required /></div>
              </div>

              {summary.rent > 0 && (
                <div className="nl-summary-card" style={{marginBottom:14}}>
                  <div className="nl-summary-row"><span>Instalment amount</span><strong>${fmt(summary.instalment)} / {summary.cycle} months</strong></div>
                  <div className="nl-summary-row"><span>Security deposit</span><strong>${fmt(summary.deposit)}</strong></div>
                  <div className="nl-summary-row"><span>Annual value</span><strong>${fmt(summary.annual)} / year</strong></div>
                  <div className="nl-summary-row"><span>Lease period</span><strong>{summary.period}</strong></div>
                </div>
              )}

              <div className="form-row">
                <div className="form-group"><label className="form-label" style={{display:'flex',alignItems:'center',justifyContent:'space-between'}}>Security Deposit ($) {data.monthly_rent && <span style={{fontSize:11,color:'var(--accent)',fontWeight:400}}>= 2 x monthly rent</span>}</label><input className="form-input" type="number" value={data.deposit} onChange={e=>setData('deposit',e.target.value)} placeholder="Auto: 2x rent" /></div>
              </div>

              <div className="form-row">
                <div className="form-group"><label className="form-label">Special Terms / Notes</label><textarea className="form-input" value={data.terms} onChange={e=>setData('terms',e.target.value)} rows={2} style={{resize:'vertical'}} placeholder="Optional: conditions, inclusions, exclusions…" /></div>
              </div>
            </div>
            <div className="modal-footer">
              <button type="button" className="btn-ghost" onClick={()=>setShowModal(false)}>Cancel</button>
              <button type="submit" className="btn-primary" disabled={processing}>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                Create Lease
              </button>
            </div>
          </form>
        </div>
      </div>
    </AppLayout>
  );
}
