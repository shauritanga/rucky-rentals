import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm } from '@inertiajs/react';
import useExchangeRate from '@/hooks/useExchangeRate';

const fmt = (n) => Number(n).toLocaleString();

function getTenantStatus(tenant) {
  const leases = tenant.leases || [];
  if (leases.some(l => l.status === 'overdue')) return 'overdue';
  const now = new Date('2026-03-19');
  if (leases.some(l => { const end = new Date(l.end_date); return (end - now) / (1000*60*60*24*30) <= 2; })) return 'expiring';
  return 'good';
}

export default function TenantsIndex({ tenants }) {
  const { formatTzsFromUsd, formatCompactTzsFromUsd } = useExchangeRate();
  const [filter, setFilter] = useState('all');
  const [search, setSearch] = useState('');
  const [sortBy, setSortBy] = useState('name');
  const [view, setView] = useState('card');
  const [selected, setSelected] = useState(null);
  const [showModal, setShowModal] = useState(false);

  const { data, setData, post, processing, reset } = useForm({ name:'', email:'', phone:'', national_id:'', nok_name:'', nok_phone:'', nok_relation:'', notes:'' });

  const totalRent = (t) => (t.leases||[]).reduce((s,l)=>s+Number(l.monthly_rent),0);
  const totalBal  = (t) => (t.leases||[]).reduce((s,l)=>l.status==='overdue'?s+Number(l.monthly_rent):s,0);

  const filtered = tenants.filter(t => {
    const st = getTenantStatus(t);
    const matchFilter = filter === 'all' || st === filter;
    const q = search.toLowerCase();
    const matchSearch = !q || t.name.toLowerCase().includes(q) || t.email.toLowerCase().includes(q);
    return matchFilter && matchSearch;
  });

  const sorted = [...filtered].sort((a, b) => {
    if (sortBy === 'rent-hi') return totalRent(b) - totalRent(a);
    if (sortBy === 'rent-lo') return totalRent(a) - totalRent(b);
    if (sortBy === 'balance') return totalBal(b) - totalBal(a);
    if (sortBy === 'lease') {
      const ae = new Date(a.leases?.[0]?.end_date || 0).getTime();
      const be = new Date(b.leases?.[0]?.end_date || 0).getTime();
      return ae - be;
    }
    return a.name.localeCompare(b.name);
  });

  const counts = { all: tenants.length, good: tenants.filter(t=>getTenantStatus(t)==='good').length, overdue: tenants.filter(t=>getTenantStatus(t)==='overdue').length, expiring: tenants.filter(t=>getTenantStatus(t)==='expiring').length };

  const submit = (e) => { e.preventDefault(); post('/tenants', { onSuccess: () => { reset(); setShowModal(false); } }); };
  const toMonYear = (date) => {
    if (!date) return '—';
    const d = new Date(date);
    if (Number.isNaN(d.getTime())) return date;
    return d.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
  };
  const leaseProgress = (lease) => {
    if (!lease?.start_date || !lease?.end_date) return { pct: 0, fillClass: '', caption: 'No active lease' };
    const now = new Date('2026-03-20');
    const start = new Date(lease.start_date);
    const end = new Date(lease.end_date);
    if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime()) || end <= start) return { pct: 0, fillClass: '', caption: 'No active lease' };
    const total = end.getTime() - start.getTime();
    const elapsed = Math.max(0, Math.min(now.getTime() - start.getTime(), total));
    const pct = Math.max(0, Math.min(100, (elapsed / total) * 100));
    const daysLeft = Math.ceil((end.getTime() - now.getTime()) / (1000 * 60 * 60 * 24));
    const fillClass = daysLeft < 0 ? 'danger' : daysLeft <= 60 ? 'warning' : '';
    const caption = daysLeft < 0 ? `Expired ${Math.abs(daysLeft)} days ago` : `${daysLeft} days remaining`;
    return { pct, fillClass, caption };
  };
  const paymentHistory = (tenant) => {
    const rent = totalRent(tenant);
    const overdue = totalBal(tenant) > 0;
    return [
      { ok: !overdue, month: 'March 2026', amount: rent, date: overdue ? 'Overdue' : 'Mar 1, 2026' },
      { ok: true, month: 'February 2026', amount: rent, date: 'Feb 2, 2026' },
      { ok: !overdue, month: 'January 2026', amount: rent, date: overdue ? 'Late — Jan 18' : 'Jan 3, 2026' },
      { ok: true, month: 'December 2025', amount: rent, date: 'Dec 1, 2025' },
    ];
  };

  return (
    <AppLayout title="Tenants" subtitle={`${tenants.length} tenants`}>
      <Head title="Tenants" />

      <div className="tn-stats-row">
        <div className="tn-stat"><div className="tn-stat-value">{tenants.length}</div><div className="tn-stat-label">Total Tenants</div></div>
        <div className="tn-stat-divider"></div>
        <div className="tn-stat"><div className="tn-stat-value" style={{color:'var(--green)'}}>{counts.good}</div><div className="tn-stat-label">Good Standing</div></div>
        <div className="tn-stat-divider"></div>
        <div className="tn-stat"><div className="tn-stat-value" style={{color:'var(--red)'}}>{counts.overdue}</div><div className="tn-stat-label">Overdue</div></div>
        <div className="tn-stat-divider"></div>
        <div className="tn-stat"><div className="tn-stat-value" style={{color:'var(--amber)'}}>{counts.expiring}</div><div className="tn-stat-label">Lease Expiring Soon</div></div>
        <div className="tn-stat-divider"></div>
        <div className="tn-stat"><div className="tn-stat-value" style={{color:'var(--accent)'}}>{formatCompactTzsFromUsd(42000)}</div><div className="tn-stat-label">Monthly Revenue</div></div>
      </div>

      <div className="toolbar">
        <div className="filters">
          {[['all','All'],['good','Good Standing'],['overdue','Overdue'],['expiring','Expiring Soon']].map(([f,l]) => (
            <button key={f} className={`filter-pill ${filter===f?'active':''}`} onClick={()=>setFilter(f)}>
              {l} <span className="pill-count">{counts[f]}</span>
            </button>
          ))}
        </div>
        <div style={{display:'flex',gap:8,alignItems:'center'}}>
          <div className="search-box" style={{width:200}}>
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <input type="text" placeholder="Search tenants…" value={search} onChange={e=>setSearch(e.target.value)} />
          </div>
          <select className="form-input form-select" value={sortBy} onChange={e=>setSortBy(e.target.value)} style={{width:140,padding:'6px 28px 6px 10px',fontSize:'12.5px'}}>
            <option value="name">Sort: Name</option>
            <option value="rent-hi">Sort: Rent ↓</option>
            <option value="rent-lo">Sort: Rent ↑</option>
            <option value="lease">Sort: Lease End</option>
            <option value="balance">Sort: Balance</option>
          </select>
          <div className="view-toggle">
            <button className={`vt-btn ${view==='card'?'active':''}`} onClick={()=>setView('card')} title="Cards"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg></button>
            <button className={`vt-btn ${view==='list'?'active':''}`} onClick={()=>setView('list')} title="List"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg></button>
          </div>
          <button className="btn-primary" onClick={()=>setShowModal(true)}>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add Tenant
          </button>
        </div>
      </div>

      {view === 'card'
        ? <div className="tenants-grid">
            {sorted.map(t => {
              const st = getTenantStatus(t);
              const bal = totalBal(t);
              const rent = totalRent(t);
              const units = (t.leases||[]).map(l=>l.unit?.unit_number).filter(Boolean).join(', ');
              const dotColor = st==='overdue'?'var(--red)':st==='expiring'?'var(--amber)':'var(--green)';
              return (
                <div className="tenant-card" key={t.id} onClick={()=>setSelected(t)}>
                  <div className="tc-head">
                    <div className="tc-avatar" style={{background:t.color,color:t.text_color}}>
                      {t.initials}
                      <div style={{position:'absolute',bottom:0,right:0,width:10,height:10,borderRadius:'50%',background:dotColor,border:'2px solid var(--bg-surface)'}}></div>
                    </div>
                    <div style={{flex:1,minWidth:0}}>
                      <div className="tc-name">{t.name}</div>
                      <div className="tc-unit">Unit {units||'—'}</div>
                    </div>
                    {bal > 0 && <span className="badge overdue">{formatTzsFromUsd(bal)} due</span>}
                    {bal === 0 && st === 'expiring' && <span className="badge vacant">Expiring</span>}
                  </div>
                  <div className="tc-contact">
                    <div className="tc-contact-row"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>{t.email}</div>
                    <div className="tc-contact-row"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.81 19.79 19.79 0 01.84 1.16 2 2 0 012.82.84h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.91 8.1a16 16 0 006.29 6.29l1.61-1.61a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 15.09v1.83z"/></svg>{t.phone}</div>
                  </div>
                  <div className="tc-foot">
                    <div style={{flex:1,fontSize:12,color:'var(--text-muted)'}}>{t.leases?.[0]?.end_date ? `Lease until ${t.leases[0].end_date}` : '—'}</div>
                    <div className="tc-rent">{formatTzsFromUsd(rent)}<span style={{fontWeight:400,fontSize:11,color:'var(--text-muted)'}}>/mo</span></div>
                  </div>
                </div>
              );
            })}
          </div>
        : <div className="card">
            <table className="data-table">
              <thead><tr><th>Tenant</th><th>Unit(s)</th><th>Status</th><th>Monthly Rent</th><th>Balance</th><th>Lease End</th></tr></thead>
              <tbody>
                {sorted.map(t => {
                  const st = getTenantStatus(t);
                  const bal = totalBal(t);
                  const units = (t.leases||[]).map(l=>l.unit?.unit_number).filter(Boolean).join(', ');
                  return (
                    <tr key={t.id} onClick={()=>setSelected(t)}>
                      <td><div className="tenant-cell"><div className="t-avatar" style={{background:t.color,color:t.text_color}}>{t.initials}</div><div><div style={{fontWeight:600}}>{t.name}</div><div style={{fontSize:'11.5px',color:'var(--text-muted)'}}>{t.email}</div></div></div></td>
                      <td style={{fontWeight:500,color:'var(--text-secondary)'}}>{units||'—'}</td>
                      <td><span className={`badge ${st==='overdue'?'overdue':st==='expiring'?'vacant':'occupied'}`}>{st==='good'?'Good':st.charAt(0).toUpperCase()+st.slice(1)}</span></td>
                      <td style={{fontWeight:600}}>{formatTzsFromUsd(totalRent(t))}</td>
                      <td>{bal>0?<span style={{color:'var(--red)',fontWeight:600}}>{formatTzsFromUsd(bal)}</span>:<span style={{color:'var(--text-muted)'}}>—</span>}</td>
                      <td style={{color:'var(--text-secondary)'}}>{t.leases?.[0]?.end_date||'—'}</td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
      }

      {/* Tenant Drawer */}
      <div className={`tenant-drawer-overlay ${selected?'open':''}`} onClick={e=>e.target===e.currentTarget&&setSelected(null)}>
        <div className="tenant-drawer">
          {selected && (() => {
            const status = getTenantStatus(selected);
            const ringClass = status === 'overdue' ? 'red' : status === 'expiring' ? 'amber' : 'green';
            const unitsText = (selected.leases||[]).map(l=>l.unit?.unit_number).filter(Boolean).join(', ') || '—';
            const firstLease = (selected.leases||[])[0];
            const prog = leaseProgress(firstLease);
            const hist = paymentHistory(selected);
            const bal = totalBal(selected);
            return (
              <>
                <div className="tdr-header">
                  <div className="tdr-avatar-wrap">
                    <div className="tdr-avatar" style={{background:selected.color,color:selected.text_color}}>
                      <div className={`tdr-status-ring ${ringClass}`}></div>
                      {selected.initials}
                    </div>
                    <div>
                      <div className="tdr-name">{selected.name}</div>
                      <div className="tdr-unit">Unit {unitsText}</div>
                      <div className="tdr-since">Tenant since {toMonYear(firstLease?.start_date)}</div>
                    </div>
                  </div>
                  <button className="drawer-close" onClick={()=>setSelected(null)}>✕</button>
                </div>

                <div className="tdr-body">
                  {selected.notes && (
                    <div className="tdr-section">
                      <div className="tdr-note">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" style={{flexShrink:0,marginTop:1}}><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <span>{selected.notes}</span>
                      </div>
                    </div>
                  )}

                  <div className="tdr-section">
                    <div className="tdr-section-title">Contact Information</div>
                    <div className="tdr-contact-list">
                      <div className="tdr-contact-item">
                        <div className="tdr-contact-icon"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></div>
                        <div><div className="tdr-contact-label">Email</div><div className="tdr-contact-value">{selected.email}</div></div>
                      </div>
                      <div className="tdr-contact-item">
                        <div className="tdr-contact-icon"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.81 19.79 19.79 0 01.84 1.16 2 2 0 012.82.84h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.91 8.1a16 16 0 006.29 6.29l1.61-1.61a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 15.09v1.83z"/></svg></div>
                        <div><div className="tdr-contact-label">Phone</div><div className="tdr-contact-value">{selected.phone}</div></div>
                      </div>
                      <div className="tdr-contact-item">
                        <div className="tdr-contact-icon"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
                        <div><div className="tdr-contact-label">National ID</div><div className="tdr-contact-value">{selected.national_id || '—'}</div></div>
                      </div>
                    </div>
                  </div>

                  <div className="tdr-section">
                    <div className="tdr-section-title">Lease Overview</div>
                    <div className="tdr-lease-progress">
                      {firstLease ? (
                        <>
                          <div className="tdr-lease-dates"><span>{toMonYear(firstLease.start_date)}</span><span>{toMonYear(firstLease.end_date)}</span></div>
                          <div className="tdr-lease-track"><div className={`tdr-lease-fill ${prog.fillClass}`} style={{width:`${prog.pct.toFixed(1)}%`}}></div></div>
                          <div className="tdr-lease-caption">{prog.caption}</div>
                        </>
                      ) : <div style={{color:'var(--text-muted)',fontSize:13}}>No active lease</div>}
                    </div>
                    <div className="tdr-kv-grid">
                      <div className="tdr-kv"><div className="tdr-kv-label">Monthly Rent</div><div className="tdr-kv-value">{formatTzsFromUsd(totalRent(selected))}</div></div>
                      <div className="tdr-kv"><div className="tdr-kv-label">Balance</div><div className={`tdr-kv-value ${bal>0?'red':'green'}`}>{bal>0?`${formatTzsFromUsd(bal)} overdue`:'Paid up'}</div></div>
                      <div className="tdr-kv"><div className="tdr-kv-label">Deposit Held</div><div className="tdr-kv-value">{formatTzsFromUsd((selected.leases||[]).reduce((s,l)=>s+Number(l.deposit),0))}</div></div>
                      <div className="tdr-kv"><div className="tdr-kv-label">Units</div><div className="tdr-kv-value accent">{unitsText}</div></div>
                    </div>
                  </div>

                  <div className="tdr-section">
                    <div className="tdr-section-title">Payment History</div>
                    <div>
                      {hist.map((p, idx) => (
                        <div className="tdr-payment-row" key={idx}>
                          <div className={`tdr-pay-dot ${p.ok?'green':'red'}`}></div>
                          <div className="tdr-pay-month">{p.month}</div>
                          <div className="tdr-pay-amount" style={{color:p.ok?'var(--green)':'var(--red)'}}>{formatTzsFromUsd(p.amount)}</div>
                          <div className="tdr-pay-date">{p.date}</div>
                        </div>
                      ))}
                    </div>
                  </div>

                  <div className="tdr-section">
                    <div className="tdr-section-title">Emergency Contact</div>
                    <div className="tdr-contact-list">
                      <div className="tdr-contact-item">
                        <div className="tdr-contact-icon" style={{background:'var(--amber-dim)',color:'var(--amber)'}}>
                          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.81a19.79 19.79 0 01-3.07-8.67A2 2 0 012 .84h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L6.09 8.29a16 16 0 006.29 6.29l1.61-1.61a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 15.09v1.83z"/></svg>
                        </div>
                        <div>
                          <div className="tdr-contact-label">{selected.nok_relation || 'Next of Kin'}</div>
                          <div className="tdr-contact-value">{selected.nok_name || '—'}</div>
                          <div className="tdr-contact-label" style={{marginTop:2}}>{selected.nok_phone || '—'}</div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <div className="tdr-footer">
                  <button className="btn-primary" onClick={()=>setSelected(null)} style={{flex:1,justifyContent:'center'}}>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    Edit Tenant
                  </button>
                  <button className="btn-secondary" onClick={()=>setSelected(null)}>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                    Send Invoice
                  </button>
                  <button className="btn-secondary" onClick={()=>setSelected(null)}>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.81a19.79 19.79 0 01-3.07-8.67A2 2 0 012 .84h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L6.09 8.29a16 16 0 006.29 6.29l1.61-1.61a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 15.09v1.83z"/></svg>
                    Contact
                  </button>
                </div>
              </>
            );
          })()}
        </div>
      </div>

      {/* Add Tenant Modal */}
      <div className={`modal-overlay ${showModal?'open':''}`} onClick={e=>e.target===e.currentTarget&&setShowModal(false)}>
        <div className="modal">
          <div className="modal-header"><div className="modal-title">Add Tenant</div><button className="modal-close" onClick={()=>setShowModal(false)}>✕</button></div>
          <form onSubmit={submit}>
            <div className="modal-body">
              <div className="form-row">
                <div className="form-group"><label className="form-label">Full Name *</label><input className="form-input" value={data.name} onChange={e=>setData('name',e.target.value)} required /></div>
                <div className="form-group"><label className="form-label">National ID</label><input className="form-input" value={data.national_id} onChange={e=>setData('national_id',e.target.value)} /></div>
              </div>
              <div className="form-row">
                <div className="form-group"><label className="form-label">Email *</label><input className="form-input" type="email" value={data.email} onChange={e=>setData('email',e.target.value)} required /></div>
                <div className="form-group"><label className="form-label">Phone *</label><input className="form-input" value={data.phone} onChange={e=>setData('phone',e.target.value)} required /></div>
              </div>
              <div className="form-row">
                <div className="form-group"><label className="form-label">Next of Kin</label><input className="form-input" value={data.nok_name} onChange={e=>setData('nok_name',e.target.value)} /></div>
                <div className="form-group"><label className="form-label">NOK Phone</label><input className="form-input" value={data.nok_phone} onChange={e=>setData('nok_phone',e.target.value)} /></div>
              </div>
            </div>
            <div className="modal-footer">
              <button type="button" className="btn-ghost" onClick={()=>setShowModal(false)}>Cancel</button>
              <button type="submit" className="btn-primary" disabled={processing}>Add Tenant</button>
            </div>
          </form>
        </div>
      </div>
    </AppLayout>
  );
}
