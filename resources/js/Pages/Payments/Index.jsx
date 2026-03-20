import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm, router } from '@inertiajs/react';
import useExchangeRate from '@/hooks/useExchangeRate';

const fmt = (n) => Number(n).toLocaleString();

export default function PaymentsIndex({ payments, tenants, units }) {
  const { formatTzsFromUsd, formatCompactTzsFromUsd } = useExchangeRate();
  const [filter, setFilter] = useState('all');
  const [search, setSearch] = useState('');
  const [month, setMonth] = useState('Mar 2026');
  const [showModal, setShowModal] = useState(false);

  const { data, setData, post, processing, reset } = useForm({ tenant_id:'', unit_id:'', month:'Mar 2026', amount:'', method:'M-Pesa', status:'paid', paid_date:'' });

  const filtered = payments.filter(p => {
    const matchFilter = filter === 'all' || p.status === filter;
    const matchMonth  = !month || p.month === month;
    const q = search.toLowerCase();
    const matchSearch = !q || p.tenant?.name?.toLowerCase().includes(q) || p.unit?.unit_number?.toLowerCase().includes(q);
    return matchFilter && matchMonth && matchSearch;
  });

  const counts = { all: payments.length, paid: payments.filter(p=>p.status==='paid').length, overdue: payments.filter(p=>p.status==='overdue').length, pending: payments.filter(p=>p.status==='pending').length };
  const collected = payments.filter(p=>p.status==='paid'&&p.month===month).reduce((s,p)=>s+Number(p.amount),0);
  const overdueAmt = payments.filter(p=>p.status==='overdue').reduce((s,p)=>s+Number(p.amount),0);

  const submit = (e) => { e.preventDefault(); post('/payments', { onSuccess: () => { reset(); setShowModal(false); } }); };

  return (
    <AppLayout title="Payments" subtitle="Mar 2026">
      <Head title="Payments" />

      <div className="stats-grid">
        <div className="stat-card"><div className="stat-top"><div className="stat-icon green"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="20 6 9 17 4 12"/></svg></div><span className="stat-delta up">↑ 4%</span></div><div className="stat-value">{formatCompactTzsFromUsd(collected)}</div><div className="stat-label">Collected This Month</div></div>
        <div className="stat-card"><div className="stat-top"><div className="stat-icon red"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div><span className="stat-delta down">{counts.overdue} tenants</span></div><div className="stat-value">{formatCompactTzsFromUsd(overdueAmt)}</div><div className="stat-label">Overdue Balance</div></div>
        <div className="stat-card"><div className="stat-top"><div className="stat-icon amber"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></div><span className="stat-delta up">↑ {formatTzsFromUsd(800)}</span></div><div className="stat-value">{formatCompactTzsFromUsd(42000)}</div><div className="stat-label">Expected This Month</div></div>
        <div className="stat-card"><div className="stat-top"><div className="stat-icon blue"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div><span className="stat-delta up">{counts.paid > 0 ? Math.round(counts.paid/(counts.paid+counts.overdue+counts.pending)*100) : 0}%</span></div><div className="stat-value">{counts.paid}/{counts.paid+counts.overdue+counts.pending}</div><div className="stat-label">Paid This Month</div></div>
      </div>

      <div className="toolbar">
        <div className="filters">
          {[['all','All'],['paid','Paid'],['overdue','Overdue'],['pending','Pending']].map(([f,l])=>(
            <button key={f} className={`filter-pill ${filter===f?'active':''}`} onClick={()=>setFilter(f)}>{l} <span className="pill-count">{counts[f]||0}</span></button>
          ))}
        </div>
        <div style={{display:'flex',gap:8,alignItems:'center'}}>
          <div className="search-box" style={{width:190}}>
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <input type="text" placeholder="Search payments…" value={search} onChange={e=>setSearch(e.target.value)} />
          </div>
          <select className="form-input form-select" value={month} onChange={e=>setMonth(e.target.value)} style={{width:130,padding:'6px 28px 6px 10px',fontSize:'12.5px'}}>
            {['Mar 2026','Feb 2026','Jan 2026','Dec 2025'].map(m=><option key={m}>{m}</option>)}
          </select>
          <button className="btn-primary" onClick={()=>setShowModal(true)}>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Record Payment
          </button>
        </div>
      </div>

      <div className="card">
        <table className="data-table">
          <thead><tr><th>Tenant</th><th>Unit</th><th>Month</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th><th></th></tr></thead>
          <tbody>
            {filtered.map(p => (
              <tr key={p.id}>
                <td><div className="tenant-cell"><div className="t-avatar" style={{background:p.tenant?.color,color:p.tenant?.text_color}}>{p.tenant?.initials}</div><div><div style={{fontWeight:600}}>{p.tenant?.name}</div></div></div></td>
                <td style={{fontWeight:600,color:'var(--text-secondary)'}}>{p.unit?.unit_number}</td>
                <td style={{color:'var(--text-secondary)'}}>{p.month}</td>
                <td style={{fontWeight:700}}>{formatTzsFromUsd(p.amount)}</td>
                <td style={{fontSize:'12.5px',color:'var(--text-secondary)'}}>{p.method||'—'}</td>
                <td><span className={`badge ${p.status}`}>{p.status.charAt(0).toUpperCase()+p.status.slice(1)}</span></td>
                <td style={{color:'var(--text-muted)',fontSize:'12.5px'}}>{p.paid_date||'—'}</td>
                <td><button className="action-dots">···</button></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className={`modal-overlay ${showModal?'open':''}`} onClick={e=>e.target===e.currentTarget&&setShowModal(false)}>
        <div className="modal">
          <div className="modal-header"><div className="modal-title">Record Payment</div><button className="modal-close" onClick={()=>setShowModal(false)}>✕</button></div>
          <form onSubmit={submit}>
            <div className="modal-body">
              <div className="form-row">
                <div className="form-group"><label className="form-label">Tenant *</label><select className="form-input form-select" value={data.tenant_id} onChange={e=>setData('tenant_id',e.target.value)} required><option value="">Select…</option>{tenants.map(t=><option key={t.id} value={t.id}>{t.name}</option>)}</select></div>
                <div className="form-group"><label className="form-label">Unit *</label><select className="form-input form-select" value={data.unit_id} onChange={e=>setData('unit_id',e.target.value)} required><option value="">Select…</option>{units.map(u=><option key={u.id} value={u.id}>{u.unit_number}</option>)}</select></div>
              </div>
              <div className="form-row">
                <div className="form-group"><label className="form-label">Month</label><select className="form-input form-select" value={data.month} onChange={e=>setData('month',e.target.value)}>{['Mar 2026','Feb 2026','Jan 2026','Dec 2025'].map(m=><option key={m}>{m}</option>)}</select></div>
                <div className="form-group"><label className="form-label">Amount (TZS) *</label><input className="form-input" type="number" value={data.amount} onChange={e=>setData('amount',e.target.value)} required /></div>
              </div>
              <div className="form-row">
                <div className="form-group"><label className="form-label">Method</label><select className="form-input form-select" value={data.method} onChange={e=>setData('method',e.target.value)}>{['M-Pesa','Bank Transfer','Cash'].map(m=><option key={m}>{m}</option>)}</select></div>
                <div className="form-group"><label className="form-label">Status</label><select className="form-input form-select" value={data.status} onChange={e=>setData('status',e.target.value)}>{['paid','pending','overdue'].map(s=><option key={s}>{s}</option>)}</select></div>
              </div>
              {data.status === 'paid' && <div className="form-row"><div className="form-group"><label className="form-label">Payment Date</label><input className="form-input" type="date" value={data.paid_date} onChange={e=>setData('paid_date',e.target.value)} /></div></div>}
            </div>
            <div className="modal-footer">
              <button type="button" className="btn-ghost" onClick={()=>setShowModal(false)}>Cancel</button>
              <button type="submit" className="btn-primary" disabled={processing}>Save Payment</button>
            </div>
          </form>
        </div>
      </div>
    </AppLayout>
  );
}
