import { useEffect, useMemo, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm } from '@inertiajs/react';

const STATUS_CLASS = { occupied:'occupied', vacant:'vacant', overdue:'overdue', maintenance:'maintenance' };
const STATUS_LABEL = { occupied:'Occupied', vacant:'Vacant', overdue:'Overdue', maintenance:'Maintenance' };
const fmt = (n) => Number(n).toLocaleString();
const SQM_PER_SQFT = 0.09290304;
const COMMERCIAL_UNIT_TYPES = [
  'Office Suite',
  'Retail Shop',
  'Showroom',
  'Warehouse',
  'Restaurant Space',
  'Clinic Space',
  'Salon Space',
  'Kiosk',
];

const unitCurrency = (unit) => (unit?.currency === 'USD' ? 'USD' : 'TZS');
const unitSizeSqm = (unit) => {
  const sqm = Number(unit?.size_sqm);
  if (sqm > 0) return sqm;
  const sqft = Number(unit?.size_sqft);
  return sqft > 0 ? sqft * SQM_PER_SQFT : 0;
};
const unitRatePerSqm = (unit) => {
  const rate = Number(unit?.rate_per_sqm);
  if (rate > 0) return rate;
  const sqm = unitSizeSqm(unit);
  return sqm > 0 ? Number(unit?.rent || 0) / sqm : 0;
};
const money = (amount, currency = 'TZS') => currency === 'USD' ? `$${fmt(amount)}` : `TZS ${fmt(amount)}`;

function UnitCard({ unit, onClick }) {
  const lease = unit.leases?.[0];
  const tenant = lease?.tenant;
  const currency = unitCurrency(unit);
  const sizeSqm = unitSizeSqm(unit);
  return (
    <div className={`unit-card ${unit.status}`} onClick={() => onClick(unit)}>
      <div className="unit-card-head">
        <div>
          <div className="unit-card-id">{unit.unit_number}</div>
          <div className="unit-card-type">{unit.type} · {sizeSqm.toFixed(1)} m²</div>
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
        <span className="unit-card-rent">{money(unit.rent, currency)}<span style={{fontSize:11,fontWeight:400,color:'var(--text-muted)'}}>/mo</span></span>
        <span style={{fontSize:'11.5px',color:'var(--text-muted)'}}>{sizeSqm.toFixed(1)} m²</span>
      </div>
    </div>
  );
}

export default function UnitsIndex({ units, floorOptions = [], canCreateUnit = true }) {
  const [filter, setFilter] = useState('all');
  const [view, setView] = useState('grid');
  const [search, setSearch] = useState('');
  const [floorFilter, setFloorFilter] = useState('');
  const [selected, setSelected] = useState(null);
  const [showModal, setShowModal] = useState(false);

  const availableFloors = useMemo(
    () => (Array.isArray(floorOptions) && floorOptions.length > 0 ? floorOptions : []),
    [floorOptions],
  );

  const { data, setData, post, processing, reset } = useForm({
    unit_number:'', floor:'1', type:'Office Suite', size_sqm:'', rate_per_sqm:'', currency:'TZS', status:'vacant', notes:''
  });

  useEffect(() => {
    if (availableFloors.length === 0) {
      setData('floor', '');
      return;
    }

    const currentFloor = Number(data.floor);
    if (!availableFloors.includes(currentFloor)) {
      setData('floor', String(availableFloors[0]));
    }
  }, [availableFloors, data.floor, setData]);

  const sizeSqmInput = Number(data.size_sqm) || 0;
  const ratePerSqmInput = Number(data.rate_per_sqm) || 0;
  const computedMonthlyRent = sizeSqmInput * ratePerSqmInput;

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

  const unitHistory = (unit) => {
    const tenant = unit?.leases?.[0]?.tenant;
    const rentLabel = money(unit?.rent || 0, unitCurrency(unit));
    return [
      { dot: unit?.status === 'overdue' ? 'red' : 'green', text: unit?.status === 'overdue' ? `Rent overdue - ${rentLabel}` : `Rent status healthy - ${rentLabel}`, date: 'Mar 20, 2026' },
      { dot: 'accent', text: `Unit profile updated - ${unit?.unit_number || 'N/A'}`, date: 'Mar 18, 2026' },
      { dot: 'amber', text: tenant ? `Tenant assigned - ${tenant.name}` : 'No active tenant assignment', date: 'Mar 15, 2026' },
    ];
  };

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
            {availableFloors.map((f)=><option key={f} value={f}>Floor {f}</option>)}
          </select>
          <div className="view-toggle">
            <button className={`vt-btn ${view==='grid'?'active':''}`} onClick={()=>setView('grid')} title="Grid">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg>
            </button>
            <button className={`vt-btn ${view==='list'?'active':''}`} onClick={()=>setView('list')} title="List">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
            </button>
          </div>
          <button className="btn-primary" onClick={()=>setShowModal(true)} disabled={!canCreateUnit || availableFloors.length === 0}>
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
                            <td style={{fontWeight:600}}>{money(u.rent, unitCurrency(u))}</td>
                            <td style={{color:'var(--text-muted)'}}>{unitSizeSqm(u).toFixed(1)} m²</td>
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
      <div className={`unit-drawer-overlay ${selected?'open':''}`} onClick={e=>e.target===e.currentTarget&&setSelected(null)}>
        <div className="unit-drawer">
          {selected && <>
            <div className="drawer-header">
              <div>
                <div className="drawer-unit-id">{selected.unit_number}</div>
                <div className="drawer-unit-sub">Floor {selected.floor} · {selected.type} · {unitSizeSqm(selected).toFixed(1)} m²</div>
              </div>
              <button className="drawer-close" onClick={()=>setSelected(null)}>✕</button>
            </div>
            <div className="drawer-body">
              <div className="drawer-section">
                <div className="drawer-section-title">Unit Details</div>
                <div className="drawer-kv-grid">
                  <div className="drawer-kv"><div className="drawer-kv-label">Status</div><div className={`drawer-kv-value ${selected.status==='occupied'?'green':selected.status==='overdue'?'red':selected.status==='maintenance'?'accent':'amber'}`}>{STATUS_LABEL[selected.status]}</div></div>
                  <div className="drawer-kv"><div className="drawer-kv-label">Monthly Rent</div><div className="drawer-kv-value">{money(selected.rent, unitCurrency(selected))}</div></div>
                  <div className="drawer-kv"><div className="drawer-kv-label">Size</div><div className="drawer-kv-value">{unitSizeSqm(selected).toFixed(1)} m²</div></div>
                  <div className="drawer-kv"><div className="drawer-kv-label">Rate per m²</div><div className="drawer-kv-value">{money(unitRatePerSqm(selected).toFixed(2), unitCurrency(selected))}</div></div>
                  <div className="drawer-kv"><div className="drawer-kv-label">Currency</div><div className="drawer-kv-value">{unitCurrency(selected)}</div></div>
                  <div className="drawer-kv"><div className="drawer-kv-label">Security Deposit</div><div className="drawer-kv-value">{money(selected.deposit, unitCurrency(selected))}</div></div>
                </div>
              </div>
              {selected.leases?.[0]?.tenant && (
                <div className="drawer-section">
                  <div className="drawer-section-title">Current Tenant</div>
                  <div className="drawer-tenant-card">
                    <div className="dt-avatar" style={{background:selected.leases[0].tenant.color,color:selected.leases[0].tenant.text_color}}>{selected.leases[0].tenant.initials}</div>
                    <div>
                      <div className="dt-name">{selected.leases[0].tenant.name}</div>
                      <div className="dt-email">{selected.leases[0].tenant.email}</div>
                      <div className="dt-phone">{selected.leases[0].tenant.phone}</div>
                    </div>
                  </div>
                </div>
              )}
              {selected.leases?.[0] && (
                <div className="drawer-section">
                  <div className="drawer-section-title">Lease</div>
                  <div className="drawer-kv-grid">
                    <div className="drawer-kv"><div className="drawer-kv-label">Start</div><div className="drawer-kv-value">{selected.leases[0].start_date}</div></div>
                    <div className="drawer-kv"><div className="drawer-kv-label">End</div><div className="drawer-kv-value">{selected.leases[0].end_date}</div></div>
                    <div className="drawer-kv"><div className="drawer-kv-label">Annual Value</div><div className="drawer-kv-value">{money(selected.rent*12, unitCurrency(selected))}</div></div>
                    <div className="drawer-kv"><div className="drawer-kv-label">Status</div><div className={`drawer-kv-value ${selected.leases[0].status==='active'?'green':selected.leases[0].status==='overdue'?'red':'amber'}`}>{selected.leases[0].status}</div></div>
                  </div>
                </div>
              )}
              <div className="drawer-section">
                <div className="drawer-section-title">Activity</div>
                {unitHistory(selected).map((h, idx) => (
                  <div className="history-item" key={`${selected.id}-${idx}`}>
                    <div className={`history-dot ${h.dot}`}></div>
                    <div>
                      <div className="history-text">{h.text}</div>
                      <div className="history-date">{h.date}</div>
                    </div>
                  </div>
                ))}
              </div>
            </div>
            <div className="drawer-footer">
              <button className="btn-primary" style={{flex:1,justifyContent:'center'}} onClick={()=>setSelected(null)}>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Edit Unit
              </button>
              <button className="btn-secondary" onClick={()=>setSelected(null)}>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Export
              </button>
              <button className="btn-danger" onClick={()=>setSelected(null)}>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
              </button>
            </div>
          </>}
        </div>
      </div>

      {/* Add Unit Modal */}
      <div className={`modal-overlay ${showModal?'open':''}`} onClick={e=>e.target===e.currentTarget&&setShowModal(false)}>
        <div className="modal">
          <div className="modal-header">
            <div className="modal-title">Add New Commercial Unit</div>
            <button className="modal-close" onClick={()=>setShowModal(false)}>✕</button>
          </div>
          <form onSubmit={submit}>
            <div className="modal-body">
              {(!canCreateUnit || availableFloors.length === 0) && (
                <div style={{ marginBottom: 12, padding: '10px 12px', border: '1px solid var(--amber)', borderRadius: 8, background: 'var(--amber-dim)', color: 'var(--amber)', fontSize: 12.5 }}>
                  You cannot create units because no assigned property floors are available.
                </div>
              )}
              <div className="form-row">
                <div className="form-group"><label className="form-label">Unit Number</label><input className="form-input" value={data.unit_number} onChange={e=>setData('unit_number',e.target.value)} placeholder="e.g. A-103" required /></div>
                <div className="form-group"><label className="form-label">Floor</label><select className="form-input form-select" value={data.floor} onChange={e=>setData('floor',e.target.value)} required disabled={availableFloors.length===0}>{availableFloors.map((f)=><option key={f} value={f}>Floor {f}</option>)}</select></div>
              </div>
              <div className="form-row">
                <div className="form-group"><label className="form-label">Commercial Unit Type</label><select className="form-input form-select" value={data.type} onChange={e=>setData('type',e.target.value)}>{COMMERCIAL_UNIT_TYPES.map(t=><option key={t}>{t}</option>)}</select></div>
                <div className="form-group"><label className="form-label">Size (m²)</label><input className="form-input" type="number" step="0.01" min="0" value={data.size_sqm} onChange={e=>setData('size_sqm',e.target.value)} placeholder="60" required /></div>
              </div>
              <div className="form-row">
                <div className="form-group"><label className="form-label">Currency</label><select className="form-input form-select" value={data.currency} onChange={e=>setData('currency',e.target.value)}><option value="TZS">TZS</option><option value="USD">USD</option></select></div>
                <div className="form-group"><label className="form-label">Rate per m²</label><input className="form-input" type="number" step="0.01" min="0" value={data.rate_per_sqm} onChange={e=>setData('rate_per_sqm',e.target.value)} placeholder={data.currency==='TZS' ? '25000' : '20'} required /></div>
              </div>
              <div className="form-row">
                <div className="form-group"><label className="form-label">Computed Monthly Rent</label><input className="form-input" type="text" value={computedMonthlyRent > 0 ? money(computedMonthlyRent.toFixed(2), data.currency) : '—'} readOnly style={{opacity:.8,cursor:'default'}} /></div>
                <div className="form-group"><label className="form-label">Status</label><select className="form-input form-select" value={data.status} onChange={e=>setData('status',e.target.value)}>{['vacant','occupied','maintenance'].map(s=><option key={s}>{s}</option>)}</select></div>
              </div>
            </div>
            <div className="modal-footer">
              <button type="button" className="btn-ghost" onClick={()=>setShowModal(false)}>Cancel</button>
              <button type="submit" className="btn-primary" disabled={processing || !canCreateUnit || availableFloors.length===0}>Add Unit</button>
            </div>
          </form>
        </div>
      </div>
    </AppLayout>
  );
}
