import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm, router } from '@inertiajs/react';

const fmt = (n) => Number(n).toLocaleString();
const CYCLE_LABELS = { 3:'Quarterly · 3mo', 4:'4-Month', 6:'Semi-Annual · 6mo', 12:'Annual' };
const STATUS_BADGE = { active:'active', expiring:'expiring', overdue:'overdue', pending_accountant:'pending_accountant', pending_pm:'pending_pm', rejected:'rejected', terminated:'pending' };

export default function LeasesIndex({ leases, tenants, units }) {
  const [filter, setFilter] = useState('all');
  const [search, setSearch] = useState('');
  const [selected, setSelected] = useState(null);
  const [showModal, setShowModal] = useState(false);

  const { data, setData, post, processing, reset } = useForm({
    tenant_id:'', unit_id:'', start_date:'2026-04-01', end_date:'2027-04-01',
    duration_months:12, payment_cycle:3, monthly_rent:'', deposit:'', terms:''
  });

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

  const approve = (lease, action) => router.patch(`/leases/${lease.id}`, { action }, { onSuccess: () => setSelected(s => s ? {...s, status: action==='approve_accountant'?'pending_pm':action==='approve_pm'?'active':s.status} : null) });
  const submit = (e) => { e.preventDefault(); post('/leases', { onSuccess: () => { reset(); setShowModal(false); } }); };

  const approvalLog = selected ? JSON.parse(selected.approval_log || '[]') : [];

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
          <button className="btn-primary" onClick={()=>setShowModal(true)}>
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
      <div className={`drawer-overlay ${selected?'open':''}`} onClick={e=>e.target===e.currentTarget&&setSelected(null)}>
        <div className="drawer" style={{width:500}}>
          {selected && <>
            <div className="drawer-header">
              <div>
                <div style={{fontSize:28,fontWeight:800,letterSpacing:-1,color:'var(--accent)'}}>{selected.unit?.unit_number}</div>
                <div style={{fontSize:16,fontWeight:600,marginTop:4}}>{selected.tenant?.name}</div>
                <div style={{fontSize:12,color:'var(--text-muted)',marginTop:3}}>{selected.duration_months}-month lease · {CYCLE_LABELS[selected.payment_cycle]} payments</div>
              </div>
              <button className="drawer-close" onClick={()=>setSelected(null)}>✕</button>
            </div>
            <div className="drawer-body">
              {(selected.status==='pending_accountant'||selected.status==='pending_pm'||selected.status==='rejected') && (
                <div className="drawer-section">
                  <div className="drawer-section-title">Approval Workflow</div>
                  <div style={{background:selected.status==='rejected'?'var(--red-dim)':'var(--accent-dim)',border:`1px solid ${selected.status==='rejected'?'var(--red)':'var(--accent)'}`,borderRadius:10,padding:'14px 16px'}}>
                    <div style={{fontSize:13,fontWeight:600,marginBottom:3}}>
                      {selected.status==='pending_accountant'?'Awaiting Accountant Approval':selected.status==='pending_pm'?'Awaiting Property Manager Approval':'Lease Rejected'}
                    </div>
                    <div style={{fontSize:12,color:'var(--text-secondary)',marginBottom:12}}>
                      {selected.status==='pending_accountant'?'The accountant must verify the lease financials before it proceeds.':selected.status==='pending_pm'?'Accountant approved. Property Manager must give final sign-off.':'This lease was rejected and requires resubmission.'}
                    </div>
                    <div style={{display:'flex',gap:8}}>
                      {selected.status==='pending_accountant' && <>
                        <button className="btn-primary" onClick={()=>approve(selected,'approve_accountant')}>✓ Approve as Accountant</button>
                        <button className="btn-danger" onClick={()=>approve(selected,'reject')}>✕ Reject</button>
                      </>}
                      {selected.status==='pending_pm' && <>
                        <button className="btn-primary" onClick={()=>approve(selected,'approve_pm')}>✓ Approve as PM</button>
                        <button className="btn-danger" onClick={()=>approve(selected,'reject')}>✕ Reject</button>
                      </>}
                      {selected.status==='rejected' && <button className="btn-primary" onClick={()=>approve(selected,'resubmit')}>↺ Resubmit for Review</button>}
                    </div>
                  </div>
                </div>
              )}
              <div className="drawer-section">
                <div className="drawer-section-title">Lease Details</div>
                <div className="kv-grid">
                  <div className="kv"><div className="kv-label">Status</div><div className={`kv-value ${selected.status==='active'?'green':selected.status==='overdue'?'red':selected.status==='rejected'?'red':'amber'}`}>{selected.status?.replace('_',' ')}</div></div>
                  <div className="kv"><div className="kv-label">Payment Cycle</div><div className="kv-value" style={{fontSize:'12.5px'}}>{CYCLE_LABELS[selected.payment_cycle]}</div></div>
                  <div className="kv"><div className="kv-label">Monthly Rent</div><div className="kv-value">${fmt(selected.monthly_rent)}</div></div>
                  <div className="kv"><div className="kv-label">Instalment</div><div className="kv-value accent">${fmt(selected.monthly_rent * selected.payment_cycle)}</div></div>
                  <div className="kv"><div className="kv-label">Annual Value</div><div className="kv-value">${fmt(selected.monthly_rent * 12)}</div></div>
                  <div className="kv"><div className="kv-label">Security Deposit</div><div className="kv-value">${fmt(selected.deposit)}</div></div>
                </div>
              </div>
              <div className="drawer-section">
                <div className="drawer-section-title">Lease Duration</div>
                <div style={{background:'var(--bg-elevated)',borderRadius:9,padding:14}}>
                  <div style={{display:'flex',justifyContent:'space-between',fontSize:'11.5px',color:'var(--text-muted)',marginBottom:8}}><span>{selected.start_date}</span><span>{selected.end_date}</span></div>
                  <div style={{height:8,background:'var(--border)',borderRadius:20,overflow:'hidden',marginBottom:6}}>
                    <div style={{height:'100%',background:'var(--accent)',borderRadius:20,width:'60%'}}></div>
                  </div>
                  <div style={{fontSize:12,color:'var(--text-secondary)',textAlign:'center'}}>{selected.duration_months} months total</div>
                </div>
              </div>
              {approvalLog.length > 0 && (
                <div className="drawer-section">
                  <div className="drawer-section-title">Approval History</div>
                  {approvalLog.map((e,i) => (
                    <div key={i} style={{display:'flex',gap:10,padding:'9px 0',borderBottom:'1px solid var(--border-subtle)'}}>
                      <div style={{width:8,height:8,borderRadius:'50%',marginTop:5,flexShrink:0,background:e.action==='approved'?'var(--green)':e.action==='rejected'?'var(--red)':'var(--accent)'}}></div>
                      <div><div style={{fontSize:13}}>{e.text}</div><div style={{fontSize:'11.5px',color:'var(--text-muted)'}}>{e.by} · {e.date}</div></div>
                    </div>
                  ))}
                </div>
              )}
            </div>
            <div className="drawer-footer">
              <button className="btn-primary" style={{flex:1,justifyContent:'center'}} onClick={()=>setSelected(null)}>Edit Lease</button>
              <button className="btn-secondary" onClick={()=>setSelected(null)}>↺ Renew</button>
              <button className="btn-danger" onClick={()=>{router.delete(`/leases/${selected.id}`);setSelected(null);}}>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
              </button>
            </div>
          </>}
        </div>
      </div>

      {/* New Lease Modal */}
      <div className={`modal-overlay ${showModal?'open':''}`} onClick={e=>e.target===e.currentTarget&&setShowModal(false)}>
        <div className="modal" style={{width:560}}>
          <div className="modal-header"><div className="modal-title">New Lease Agreement</div><button className="modal-close" onClick={()=>setShowModal(false)}>✕</button></div>
          <form onSubmit={submit}>
            <div className="modal-body">
              <div className="form-row">
                <div className="form-group"><label className="form-label">Tenant *</label>
                  <select className="form-input form-select" value={data.tenant_id} onChange={e=>setData('tenant_id',e.target.value)} required>
                    <option value="">Select tenant…</option>
                    {tenants.map(t=><option key={t.id} value={t.id}>{t.name}</option>)}
                  </select>
                </div>
                <div className="form-group"><label className="form-label">Unit *</label>
                  <select className="form-input form-select" value={data.unit_id} onChange={e=>{setData('unit_id',e.target.value);const u=units.find(x=>x.id==e.target.value);if(u){setData(d=>({...d,monthly_rent:u.rent,deposit:u.rent*2}));}}} required>
                    <option value="">Select unit…</option>
                    {units.map(u=><option key={u.id} value={u.id}>{u.unit_number} — {u.type} (${fmt(u.rent)}/mo)</option>)}
                  </select>
                </div>
              </div>
              <div className="form-row">
                <div className="form-group"><label className="form-label">Start Date *</label><input className="form-input" type="date" value={data.start_date} onChange={e=>setData('start_date',e.target.value)} required /></div>
                <div className="form-group"><label className="form-label">Duration</label>
                  <select className="form-input form-select" value={data.duration_months} onChange={e=>setData('duration_months',e.target.value)}>
                    <option value={12}>1 Year (12 months)</option><option value={24}>2 Years (24 months)</option><option value={36}>3 Years (36 months)</option>
                  </select>
                </div>
              </div>
              <div className="form-row">
                <div className="form-group"><label className="form-label">Payment Cycle *</label>
                  <select className="form-input form-select" value={data.payment_cycle} onChange={e=>setData('payment_cycle',e.target.value)}>
                    <option value={3}>Quarterly (3 months)</option><option value={4}>Every 4 months</option><option value={6}>Semi-Annual (6 months)</option><option value={12}>Annual</option>
                  </select>
                </div>
                <div className="form-group"><label className="form-label">Monthly Rent ($) *</label><input className="form-input" type="number" value={data.monthly_rent} onChange={e=>setData('monthly_rent',e.target.value)} required /></div>
              </div>
              <div className="form-row">
                <div className="form-group"><label className="form-label">Security Deposit ($)</label><input className="form-input" type="number" value={data.deposit} onChange={e=>setData('deposit',e.target.value)} /></div>
                <div className="form-group"><label className="form-label">Special Terms</label><input className="form-input" value={data.terms} onChange={e=>setData('terms',e.target.value)} placeholder="Optional…" /></div>
              </div>
              {data.monthly_rent && <div style={{background:'var(--bg-elevated)',borderRadius:9,padding:'12px 14px',fontSize:13,color:'var(--text-secondary)'}}>
                Instalment: <strong style={{color:'var(--accent)'}}>${fmt(data.monthly_rent * data.payment_cycle)}</strong> every {data.payment_cycle} months · Deposit: <strong>${fmt(data.deposit||data.monthly_rent*2)}</strong>
              </div>}
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
