import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm } from '@inertiajs/react';

const STATUS_CLASS = { occupied:'occupied', vacant:'vacant', overdue:'overdue', maintenance:'maintenance' };
const STATUS_LABEL = { occupied:'Occupied', vacant:'Vacant', overdue:'Overdue', maintenance:'Maintenance' };
const fmt = (n) => Number(n).toLocaleString();

function UnitCard({ unit, onClick }) {
  const lease = unit.leases?.[0];
  const tenant = lease?.tenant;
  return (
    <div className={`unit-card ${unit.status}`} onClick={() => onClick(unit)}>
      <div className="unit-card-head">
        <div>
          <div className="unit-card-id">{unit.unit_number}</div>
          <div className="unit-card-type">{unit.type} · {unit.size_sqft} sq ft</div>
        </div>
        <span className={`badge ${STATUS_CLASS[unit.status]}`}>{STATUS_LABEL[unit.status]}</span>
      </div>
      <div style={{marginBottom:12}}>
        {tenant
          ? <><div style={{fontSize:13,fontWeight:500}}>{tenant.name}</div><div style={{fontSize:'11.5px',color:'var(--text-muted)'}}>Lease until {lease.end_date?.slice(0,7)}</div></>
          : <><div style={{fontSize:13,color:'var(--text-muted)'}}>— Unoccupied</div><div style={{fontSize:'11.5px',color:'var(--text-muted)'}}>{unit.status==='maintenance'?'Under maintenance':'Available now'}</div></>
        }
      </div>
      <div className="unit-card-foot">
        <span className="unit-card-rent">${fmt(unit.rent)}<span style={{fontSize:11,fontWeight:400,color:'var(--text-muted)'}}>/mo</span></span>
        <span style={{fontSize:'11.5px',color:'var(--text-muted)'}}>{unit.size_sqft} ft²</span>
      </div>
    </div>
  );
}

export default function UnitsIndex({ units }) {
  const [filter, setFilter] = useState('all');
  const [view, setView] = useState('grid');
  const [search, setSearch] = useState('');
  const [floorFilter, setFloorFilter] = useState('');
  const [selected, setSelected] = useState(null);
  const [showModal, setShowModal] = useState(false);

  const { data, setData, post, processing, reset } = useForm({
    unit_number:'', floor:'', type:'Studio', size_sqft:'', rent:'', status:'vacant', notes:''
  });

  const filtered = units.filter(u => {
    const matchStatus = filter === 'all' || u.status === filter;
    const matchFloor  = !floorFilter || String(u.floor) === floorFilter;
    const q = search.toLowerCase();
    const matchSearch = !q || u.unit_number.toLowerCase().includes(q) || u.type.toLowerCase().includes(q) || u.leases?.[0]?.tenant?.name?.toLowerCase().includes(q);
    return matchStatus && matchFloor && matchSearch;
  });

  const byFloor = filtered.reduce((acc, u) => { (acc[u.floor] = acc[u.floor] || []).push(u); return acc; }, {});
  const counts = { all: units.length, occupied: units.filter(u=>u.status==='occupied').length, vacant: units.filter(u=>u.status==='vacant').length, overdue: units.filter(u=>u.status==='overdue').length, maintenance: units.filter(u=>u.status==='maintenance').length };

  const submit = (e) => { e.preventDefault(); post('/units', { onSuccess: () => { reset(); setShowModal(false); } }); };

  return (
    <AppLayout title="Units" subtitle={`${units.length} units`}>
      <Head title="Units" />

      <div className="toolbar">
        <div className="filters">
          {['all','occupied','vacant','overdue','maintenance'].map(f => (
            <button key={f} className={`filter-pill ${filter===f?'active':''}`} onClick={() => setFilter(f)}>
              {f.charAt(0).toUpperCase()+f.slice(1)} <span className="pill-count">{counts[f]}</span>
            </button>
          ))}
        </div>
        <div style={{display:'flex',gap:8,alignItems:'center'}}>
          <div className="search-box" style={{width:190}}>
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <input type="text" placeholder="Search units…" value={search} onChange={e=>setSearch(e.target.value)} />
          </div>
          <select className="form-input form-select" value={floorFilter} onChange={e=>setFloorFilter(e.target.value)} style={{width:116,padding:'6px 28px 6px 10px',fontSize:'12.5px'}}>
            <option value="">All Floors</option>
            {[1,2,3,4,5,6].map(f=><option key={f} value={f}>Floor {f}</option>)}
          </select>
          <div className="view-toggle">
            <button className={`vt-btn ${view==='grid'?'active':''}`} onClick={()=>setView('grid')} title="Grid">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg>
            </button>
            <button className={`vt-btn ${view==='list'?'active':''}`} onClick={()=>setView('list')} title="List">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
            </button>
          </div>
          <button className="btn-primary" onClick={()=>setShowModal(true)}>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add Unit
          </button>
        </div>
      </div>

      {Object.keys(byFloor).sort((a,b)=>a-b).map(floor => {
        const floorUnits = byFloor[floor];
        const occ = floorUnits.filter(u=>u.status==='occupied'||u.status==='overdue').length;
        const wings = {1:'A',2:'B',3:'C',4:'D',5:'E',6:'F'};
        return (
          <div className="floor-section" key={floor}>
            <div className="floor-section-header">
              <span className="floor-section-title">Floor {floor} · Wing {wings[floor]||floor}</span>
              <div className="floor-section-line"></div>
              <span style={{fontSize:'11.5px',color:'var(--text-muted)'}}>{occ} occupied · {floorUnits.length-occ} available</span>
            </div>
            {view === 'grid'
              ? <div className="units-grid">{floorUnits.map(u=><UnitCard key={u.id} unit={u} onClick={setSelected}/>)}</div>
              : <div className="card" style={{marginBottom:0}}>
                  <table className="data-table">
                    <thead><tr><th>Unit</th><th>Type</th><th>Tenant</th><th>Status</th><th>Rent</th><th>Size</th></tr></thead>
                    <tbody>
                      {floorUnits.map(u => {
                        const t = u.leases?.[0]?.tenant;
                        return (
                          <tr key={u.id} onClick={()=>setSelected(u)}>
                            <td><div style={{fontWeight:700}}>{u.unit_number}</div></td>
                            <td style={{color:'var(--text-secondary)'}}>{u.type}</td>
                            <td>{t?<div className="tenant-cell"><div className="t-avatar" style={{background:t.color,color:t.text_color}}>{t.initials}</div>{t.name}</div>:<span style={{color:'var(--text-muted)'}}>—</span>}</td>
                            <td><span className={`badge ${STATUS_CLASS[u.status]}`}>{STATUS_LABEL[u.status]}</span></td>
                            <td style={{fontWeight:600}}>${fmt(u.rent)}</td>
                            <td style={{color:'var(--text-muted)'}}>{u.size_sqft} ft²</td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
            }
          </div>
        );
      })}

      {/* Unit Drawer */}
      <div className={`drawer-overlay ${selected?'open':''}`} onClick={e=>e.target===e.currentTarget&&setSelected(null)}>
        <div className="drawer">
          {selected && <>
            <div className="drawer-header">
              <div>
                <div style={{fontSize:26,fontWeight:700,letterSpacing:'-.8px'}}>{selected.unit_number}</div>
                <div style={{fontSize:13,color:'var(--text-muted)',marginTop:4}}>Floor {selected.floor} · {selected.type} · {selected.size_sqft} sq ft</div>
              </div>
              <button className="drawer-close" onClick={()=>setSelected(null)}>✕</button>
            </div>
            <div className="drawer-body">
              <div className="drawer-section">
                <div className="drawer-section-title">Unit Details</div>
                <div className="kv-grid">
                  <div className="kv"><div className="kv-label">Status</div><div className={`kv-value ${selected.status==='occupied'?'green':selected.status==='overdue'?'red':selected.status==='maintenance'?'accent':'amber'}`}>{STATUS_LABEL[selected.status]}</div></div>
                  <div className="kv"><div className="kv-label">Monthly Rent</div><div className="kv-value">${fmt(selected.rent)}</div></div>
                  <div className="kv"><div className="kv-label">Size</div><div className="kv-value">{selected.size_sqft} sq ft</div></div>
                  <div className="kv"><div className="kv-label">Security Deposit</div><div className="kv-value">${fmt(selected.deposit)}</div></div>
                </div>
              </div>
              {selected.leases?.[0]?.tenant && (
                <div className="drawer-section">
                  <div className="drawer-section-title">Current Tenant</div>
                  <div style={{background:'var(--bg-elevated)',borderRadius:10,padding:14,display:'flex',alignItems:'center',gap:12}}>
                    <div style={{width:40,height:40,borderRadius:'50%',background:selected.leases[0].tenant.color,color:selected.leases[0].tenant.text_color,display:'flex',alignItems:'center',justifyContent:'center',fontSize:14,fontWeight:700,flexShrink:0}}>{selected.leases[0].tenant.initials}</div>
                    <div>
                      <div style={{fontSize:14,fontWeight:600}}>{selected.leases[0].tenant.name}</div>
                      <div style={{fontSize:12,color:'var(--text-muted)'}}>{selected.leases[0].tenant.email}</div>
                      <div style={{fontSize:12,color:'var(--text-muted)'}}>{selected.leases[0].tenant.phone}</div>
                    </div>
                  </div>
                </div>
              )}
              {selected.leases?.[0] && (
                <div className="drawer-section">
                  <div className="drawer-section-title">Lease</div>
                  <div className="kv-grid">
                    <div className="kv"><div className="kv-label">Start</div><div className="kv-value">{selected.leases[0].start_date}</div></div>
                    <div className="kv"><div className="kv-label">End</div><div className="kv-value">{selected.leases[0].end_date}</div></div>
                    <div className="kv"><div className="kv-label">Annual Value</div><div className="kv-value">${fmt(selected.rent*12)}</div></div>
                    <div className="kv"><div className="kv-label">Status</div><div className={`kv-value ${selected.leases[0].status==='active'?'green':selected.leases[0].status==='overdue'?'red':'amber'}`}>{selected.leases[0].status}</div></div>
                  </div>
                </div>
              )}
            </div>
            <div className="drawer-footer">
              <button className="btn-primary" style={{flex:1,justifyContent:'center'}} onClick={()=>setSelected(null)}>Edit Unit</button>
              <button className="btn-danger" onClick={()=>setSelected(null)}>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
              </button>
            </div>
          </>}
        </div>
      </div>

      {/* Add Unit Modal */}
      <div className={`modal-overlay ${showModal?'open':''}`} onClick={e=>e.target===e.currentTarget&&setShowModal(false)}>
        <div className="modal">
          <div className="modal-header">
            <div className="modal-title">Add New Unit</div>
            <button className="modal-close" onClick={()=>setShowModal(false)}>✕</button>
          </div>
          <form onSubmit={submit}>
            <div className="modal-body">
              <div className="form-row">
                <div className="form-group"><label className="form-label">Unit Number</label><input className="form-input" value={data.unit_number} onChange={e=>setData('unit_number',e.target.value)} placeholder="e.g. A-103" required /></div>
                <div className="form-group"><label className="form-label">Floor</label><select className="form-input form-select" value={data.floor} onChange={e=>setData('floor',e.target.value)} required>{[1,2,3,4,5,6].map(f=><option key={f} value={f}>Floor {f}</option>)}</select></div>
              </div>
              <div className="form-row">
                <div className="form-group"><label className="form-label">Type</label><select className="form-input form-select" value={data.type} onChange={e=>setData('type',e.target.value)}>{['Studio','1 Bed','2 Bed','3 Bed','Penthouse'].map(t=><option key={t}>{t}</option>)}</select></div>
                <div className="form-group"><label className="form-label">Monthly Rent ($)</label><input className="form-input" type="number" value={data.rent} onChange={e=>setData('rent',e.target.value)} placeholder="1200" required /></div>
              </div>
              <div className="form-row">
                <div className="form-group"><label className="form-label">Size (sq ft)</label><input className="form-input" type="number" value={data.size_sqft} onChange={e=>setData('size_sqft',e.target.value)} placeholder="650" required /></div>
                <div className="form-group"><label className="form-label">Status</label><select className="form-input form-select" value={data.status} onChange={e=>setData('status',e.target.value)}>{['vacant','occupied','maintenance'].map(s=><option key={s}>{s}</option>)}</select></div>
              </div>
            </div>
            <div className="modal-footer">
              <button type="button" className="btn-ghost" onClick={()=>setShowModal(false)}>Cancel</button>
              <button type="submit" className="btn-primary" disabled={processing}>Add Unit</button>
            </div>
          </form>
        </div>
      </div>
    </AppLayout>
  );
}
