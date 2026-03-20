import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm } from '@inertiajs/react';

const fmt = (n) => Number(n).toLocaleString();

function getTenantStatus(tenant) {
  const leases = tenant.leases || [];
  if (leases.some(l => l.status === 'overdue')) return 'overdue';
  const now = new Date('2026-03-19');
  if (leases.some(l => { const end = new Date(l.end_date); return (end - now) / (1000*60*60*24*30) <= 2; })) return 'expiring';
  return 'good';
}

export default function TenantsIndex({ tenants }) {
  const [filter, setFilter] = useState('all');
  const [search, setSearch] = useState('');
  const [view, setView] = useState('card');
  const [selected, setSelected] = useState(null);
  const [showModal, setShowModal] = useState(false);

  const { data, setData, post, processing, reset } = useForm({ name:'', email:'', phone:'', national_id:'', nok_name:'', nok_phone:'', nok_relation:'', notes:'' });

  const filtered = tenants.filter(t => {
    const st = getTenantStatus(t);
    const matchFilter = filter === 'all' || st === filter;
    const q = search.toLowerCase();
    const matchSearch = !q || t.name.toLowerCase().includes(q) || t.email.toLowerCase().includes(q);
    return matchFilter && matchSearch;
  });

  const counts = { all: tenants.length, good: tenants.filter(t=>getTenantStatus(t)==='good').length, overdue: tenants.filter(t=>getTenantStatus(t)==='overdue').length, expiring: tenants.filter(t=>getTenantStatus(t)==='expiring').length };

  const submit = (e) => { e.preventDefault(); post('/tenants', { onSuccess: () => { reset(); setShowModal(false); } }); };

  const totalRent = (t) => (t.leases||[]).reduce((s,l)=>s+Number(l.monthly_rent),0);
  const totalBal  = (t) => (t.leases||[]).reduce((s,l)=>l.status==='overdue'?s+Number(l.monthly_rent):s,0);

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
        <div className="tn-stat"><div className="tn-stat-value" style={{color:'var(--accent)'}}>$42k</div><div className="tn-stat-label">Monthly Revenue</div></div>
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
            {filtered.map(t => {
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
                    {bal > 0 && <span className="badge overdue">${fmt(bal)} due</span>}
                    {bal === 0 && st === 'expiring' && <span className="badge vacant">Expiring</span>}
                  </div>
                  <div className="tc-contact">
                    <div className="tc-contact-row"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>{t.email}</div>
                    <div className="tc-contact-row"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.81 19.79 19.79 0 01.84 1.16 2 2 0 012.82.84h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.91 8.1a16 16 0 006.29 6.29l1.61-1.61a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 15.09v1.83z"/></svg>{t.phone}</div>
                  </div>
                  <div className="tc-foot">
                    <div style={{flex:1,fontSize:12,color:'var(--text-muted)'}}>{t.leases?.[0]?.end_date ? `Lease until ${t.leases[0].end_date}` : '—'}</div>
                    <div className="tc-rent">${fmt(rent)}<span style={{fontWeight:400,fontSize:11,color:'var(--text-muted)'}}>/mo</span></div>
                  </div>
                </div>
              );
            })}
          </div>
        : <div className="card">
            <table className="data-table">
              <thead><tr><th>Tenant</th><th>Unit(s)</th><th>Status</th><th>Monthly Rent</th><th>Balance</th><th>Lease End</th></tr></thead>
              <tbody>
                {filtered.map(t => {
                  const st = getTenantStatus(t);
                  const bal = totalBal(t);
                  const units = (t.leases||[]).map(l=>l.unit?.unit_number).filter(Boolean).join(', ');
                  return (
                    <tr key={t.id} onClick={()=>setSelected(t)}>
                      <td><div className="tenant-cell"><div className="t-avatar" style={{background:t.color,color:t.text_color}}>{t.initials}</div><div><div style={{fontWeight:600}}>{t.name}</div><div style={{fontSize:'11.5px',color:'var(--text-muted)'}}>{t.email}</div></div></div></td>
                      <td style={{fontWeight:500,color:'var(--text-secondary)'}}>{units||'—'}</td>
                      <td><span className={`badge ${st==='overdue'?'overdue':st==='expiring'?'vacant':'occupied'}`}>{st==='good'?'Good':st.charAt(0).toUpperCase()+st.slice(1)}</span></td>
                      <td style={{fontWeight:600}}>${fmt(totalRent(t))}</td>
                      <td>{bal>0?<span style={{color:'var(--red)',fontWeight:600}}>${fmt(bal)}</span>:<span style={{color:'var(--text-muted)'}}>—</span>}</td>
                      <td style={{color:'var(--text-secondary)'}}>{t.leases?.[0]?.end_date||'—'}</td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
      }

      {/* Tenant Drawer */}
      <div className={`drawer-overlay ${selected?'open':''}`} onClick={e=>e.target===e.currentTarget&&setSelected(null)}>
        <div className="drawer">
          {selected && <>
            <div className="drawer-header">
              <div style={{display:'flex',alignItems:'center',gap:14}}>
                <div style={{width:52,height:52,borderRadius:'50%',background:selected.color,color:selected.text_color,display:'flex',alignItems:'center',justifyContent:'center',fontSize:18,fontWeight:700,flexShrink:0}}>{selected.initials}</div>
                <div>
                  <div style={{fontSize:18,fontWeight:700}}>{selected.name}</div>
                  <div style={{fontSize:'12.5px',color:'var(--text-muted)',marginTop:4}}>Unit {(selected.leases||[]).map(l=>l.unit?.unit_number).join(', ')||'—'}</div>
                </div>
              </div>
              <button className="drawer-close" onClick={()=>setSelected(null)}>✕</button>
            </div>
            <div className="drawer-body">
              {selected.notes && <div style={{background:'var(--amber-dim)',border:'1px solid var(--amber)',borderRadius:9,padding:'11px 13px',fontSize:'12.5px',color:'var(--amber)',marginBottom:20}}>{selected.notes}</div>}
              <div className="drawer-section">
                <div className="drawer-section-title">Contact Information</div>
                {[['Email',selected.email],['Phone',selected.phone],['National ID',selected.national_id||'—']].map(([l,v])=>(
                  <div key={l} style={{background:'var(--bg-elevated)',borderRadius:9,padding:'10px 13px',marginBottom:8,display:'flex',alignItems:'center',gap:10}}>
                    <div style={{width:28,height:28,borderRadius:7,background:'var(--accent-dim)',color:'var(--accent)',display:'flex',alignItems:'center',justifyContent:'center',flexShrink:0}}>
                      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    </div>
                    <div><div style={{fontSize:11,color:'var(--text-muted)'}}>{l}</div><div style={{fontSize:13,fontWeight:500}}>{v}</div></div>
                  </div>
                ))}
              </div>
              <div className="drawer-section">
                <div className="drawer-section-title">Lease Overview</div>
                <div className="kv-grid">
                  <div className="kv"><div className="kv-label">Monthly Rent</div><div className="kv-value">${fmt(totalRent(selected))}</div></div>
                  <div className="kv"><div className="kv-label">Balance</div><div className={`kv-value ${totalBal(selected)>0?'red':'green'}`}>{totalBal(selected)>0?`$${fmt(totalBal(selected))} overdue`:'Paid up'}</div></div>
                  <div className="kv"><div className="kv-label">Deposit Held</div><div className="kv-value">${fmt((selected.leases||[]).reduce((s,l)=>s+Number(l.deposit),0))}</div></div>
                  <div className="kv"><div className="kv-label">Leases</div><div className="kv-value accent">{selected.leases?.length||0}</div></div>
                </div>
              </div>
              {selected.nok_name && (
                <div className="drawer-section">
                  <div className="drawer-section-title">Emergency Contact</div>
                  <div style={{background:'var(--bg-elevated)',borderRadius:9,padding:'10px 13px'}}>
                    <div style={{fontSize:11,color:'var(--text-muted)'}}>{selected.nok_relation}</div>
                    <div style={{fontSize:13,fontWeight:600}}>{selected.nok_name}</div>
                    <div style={{fontSize:12,color:'var(--text-muted)'}}>{selected.nok_phone}</div>
                  </div>
                </div>
              )}
            </div>
            <div className="drawer-footer">
              <button className="btn-primary" style={{flex:1,justifyContent:'center'}} onClick={()=>setSelected(null)}>Edit Tenant</button>
              <button className="btn-secondary" onClick={()=>setSelected(null)}>Send Invoice</button>
            </div>
          </>}
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
