import { useEffect, useMemo, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { floorSortOrder } from '@/utils/floorConfig';

const STATUS_CLASS = { occupied:'occupied', vacant:'vacant', overdue:'overdue', maintenance:'maintenance' };
const STATUS_LABEL = { occupied:'Occupied', vacant:'Vacant', overdue:'Overdue', maintenance:'Maintenance' };
const fmt = (n) => Number(n).toLocaleString();
const SQM_PER_SQFT = 0.09290304;
const COMMERCIAL_UNIT_TYPES = [
  'Office Suite',
  'Retail Shop',
  'Showroom',
  'Warehouse',
  'Restaurant',
  'Clinic',
  'Salon',
  'Store',
];

// Unit number must be G.01–G.99, M.01–M.99, F1.01–F1.99 … F99.99, B1.01–B1.99 … B99.99
const UNIT_NUMBER_RE = /^(G|M|F\d{1,2}|B\d{1,2})\.(?:0[1-9]|[1-9]\d)$/i;

/** Returns the floor ID that matches a given unit-number prefix, or null. */
const guessFloorId = (unitNum) => {
  const u = String(unitNum).toUpperCase();
  if (/^G/.test(u)) return 'G';
  if (/^M/.test(u)) return 'M';
  const f = u.match(/^F(\d{1,2})/);
  if (f) return f[1];          // upper floors stored as String(n) e.g. '1','2'
  const b = u.match(/^B(\d{1,2})/);
  if (b) return `B${b[1]}`;   // basements stored as 'B1','B2'
  return null;
};

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

export default function UnitsIndex({ units, floorOptions = [], canCreateUnit = true, settings = {} }) {
  const [filter, setFilter] = useState('all');
  const [view, setView] = useState('grid');
  const [search, setSearch] = useState('');
  const [floorFilter, setFloorFilter] = useState('');
  const [selected, setSelected] = useState(null);
  const [showModal, setShowModal] = useState(false);
  const [showEditModal, setShowEditModal] = useState(false);
  const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
  const [deleting, setDeleting] = useState(false);

  const availableFloors = useMemo(
    () => (Array.isArray(floorOptions) && floorOptions.length > 0 ? floorOptions : []),
    [floorOptions],
  );

  const floorLabelMap = useMemo(
    () => Object.fromEntries(availableFloors.map(f => [f.id, f.label])),
    [availableFloors],
  );

  // ── Create form ──────────────────────────────────────────────────────────
  const { data, setData, post, processing, reset, errors } = useForm({
    unit_number:'', floor:'', type:'Office Suite', size_sqm:'', rate_per_sqm:'', service_charge_per_sqm:'', currency:'TZS', status:'vacant', electricity_type:'direct', notes:''
  });

  // ── Edit form ─────────────────────────────────────────────────────────────
  const { data: editData, setData: setEditData, patch: patchUnit, processing: editProcessing, reset: resetEdit, errors: editErrors } = useForm({
    unit_number:'', floor:'', type:'Office Suite', size_sqm:'', rate_per_sqm:'', service_charge_per_sqm:'', currency:'TZS', status:'vacant', electricity_type:'direct', notes:''
  });

  useEffect(() => {
    if (availableFloors.length === 0) {
      setData('floor', '');
      return;
    }
    const ids = availableFloors.map(f => f.id);
    if (!ids.includes(data.floor)) {
      setData('floor', availableFloors[0].id);
    }
  }, [availableFloors]);

  const depositRentMonths = Number(settings.deposit_rent_months ?? 1);
  const depositScMonths   = Number(settings.deposit_service_charge_months ?? 1);

  // Create form computed values
  const sizeSqmInput = Number(data.size_sqm) || 0;
  const ratePerSqmInput = Number(data.rate_per_sqm) || 0;
  const computedMonthlyRent = sizeSqmInput * ratePerSqmInput;
  const computedServiceCharge = sizeSqmInput * (Number(data.service_charge_per_sqm) || 0);
  const computedDeposit = (computedMonthlyRent * depositRentMonths) + (computedServiceCharge * depositScMonths);

  // Edit form computed values
  const editSizeSqm = Number(editData.size_sqm) || 0;
  const editRent    = editSizeSqm * (Number(editData.rate_per_sqm) || 0);
  const editSc      = editSizeSqm * (Number(editData.service_charge_per_sqm) || 0);
  const editDeposit = (editRent * depositRentMonths) + (editSc * depositScMonths);

  // Unit number format validation
  const unitNumberValid    = !data.unit_number     || UNIT_NUMBER_RE.test(data.unit_number);
  const editUnitNumberValid = !editData.unit_number || UNIT_NUMBER_RE.test(editData.unit_number);

  /** Handler for create-form unit number — enforces allowed chars and auto-selects floor */
  const onUnitNumberChange = (e) => {
    const val = e.target.value.toUpperCase().replace(/[^GMFB0-9.]/g, '');
    const guessed = guessFloorId(val);
    if (guessed && availableFloors.some(f => f.id === guessed)) {
      setData({ ...data, unit_number: val, floor: guessed });
    } else {
      setData('unit_number', val);
    }
  };

  /** Handler for edit-form unit number — enforces allowed chars and auto-selects floor */
  const onEditUnitNumberChange = (e) => {
    const val = e.target.value.toUpperCase().replace(/[^GMFB0-9.]/g, '');
    const guessed = guessFloorId(val);
    if (guessed && availableFloors.some(f => f.id === guessed)) {
      setEditData({ ...editData, unit_number: val, floor: guessed });
    } else {
      setEditData('unit_number', val);
    }
  };

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

  const openEditModal = (unit) => {
    setEditData({
      unit_number:            unit.unit_number ?? '',
      floor:                  unit.floor ?? '',
      type:                   unit.type ?? 'Office Suite',
      size_sqm:               unit.size_sqm ?? '',
      rate_per_sqm:           unit.rate_per_sqm ?? unitRatePerSqm(unit),
      service_charge_per_sqm: unit.service_charge_per_sqm ?? (unitSizeSqm(unit) > 0 ? (Number(unit.service_charge ?? 0) / unitSizeSqm(unit)).toFixed(2) : ''),
      currency:               unit.currency ?? 'TZS',
      status:                 unit.status ?? 'vacant',
      electricity_type:       unit.electricity_type ?? 'direct',
      notes:                  unit.notes ?? '',
    });
    setShowEditModal(true);
  };

  const submitEdit = (e) => {
    e.preventDefault();
    patchUnit(`/units/${selected.id}`, {
      preserveScroll: true,
      onSuccess: () => {
        setShowEditModal(false);
        setSelected(null);
      },
    });
  };

  const handleDelete = () => {
    setDeleting(true);
    router.delete(`/units/${selected.id}`, {
      preserveScroll: true,
      onSuccess: () => {
        setShowDeleteConfirm(false);
        setSelected(null);
      },
      onFinish: () => setDeleting(false),
    });
  };

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
          <select className="form-input form-select" value={floorFilter} onChange={e=>setFloorFilter(e.target.value)} style={{width:130,padding:'6px 28px 6px 10px',fontSize:'12.5px'}}>
            <option value="">All Floors</option>
            {availableFloors.map(f=><option key={f.id} value={f.id}>{f.label}</option>)}
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

      {Object.keys(byFloor).sort((a,b)=>floorSortOrder(a)-floorSortOrder(b)).map(floor => {
        const floorUnits = byFloor[floor];
        const occ = floorUnits.filter(u=>u.status==='occupied'||u.status==='overdue').length;
        return (
          <div className="floor-section" key={floor}>
            <div className="floor-section-header">
              <span className="floor-section-title">{floorLabelMap[floor] ?? `Floor ${floor}`}</span>
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
                <div className="drawer-unit-sub">{floorLabelMap[selected.floor] ?? selected.floor} · {selected.type} · {unitSizeSqm(selected).toFixed(1)} m²</div>
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
                  <div className="drawer-kv"><div className="drawer-kv-label">Service Charge / mo</div><div className="drawer-kv-value">{money(selected.service_charge ?? 0, unitCurrency(selected))}</div></div>
                  <div className="drawer-kv"><div className="drawer-kv-label">Electricity</div><div className="drawer-kv-value">{selected.electricity_type === 'submeter' ? 'Submeter' : 'Direct (Own Meter)'}</div></div>
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
              <button
                className="btn-primary"
                style={{flex:1,justifyContent:'center'}}
                onClick={() => openEditModal(selected)}
              >
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Edit Unit
              </button>
              <button
                className="btn-danger"
                onClick={() => setShowDeleteConfirm(true)}
                title="Delete unit"
              >
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
              </button>
            </div>
          </>}
        </div>
      </div>

      {/* Edit Unit Modal */}
      <div className={`modal-overlay ${showEditModal ? 'open' : ''}`} onClick={e => e.target === e.currentTarget && setShowEditModal(false)}>
        <div className="modal">
          <div className="modal-header">
            <div className="modal-title">Edit Unit — {selected?.unit_number}</div>
            <button className="modal-close" onClick={() => setShowEditModal(false)}>✕</button>
          </div>
          <form onSubmit={submitEdit} style={{display:'flex',flexDirection:'column',flex:1,minHeight:0,overflow:'hidden'}}>
            <div className="modal-body">
              <div className="form-row">
                <div className="form-group">
                  <label className="form-label">
                    Unit Number
                    <span style={{fontWeight:400,color:'var(--text-muted)',marginLeft:6,fontSize:11}}>G.01–G.99 · M.01 · F1.01 · B1.01</span>
                  </label>
                  <input
                    className={`form-input${editErrors.unit_number || !editUnitNumberValid ? ' input-error' : ''}`}
                    value={editData.unit_number}
                    onChange={onEditUnitNumberChange}
                    placeholder="e.g. G.01 or F1.05"
                    maxLength={6}
                    required
                  />
                  {editErrors.unit_number && <div className="form-error">{editErrors.unit_number}</div>}
                  {!editErrors.unit_number && !editUnitNumberValid && (
                    <div className="form-error">Invalid format — use G.01, M.03, F1.07, B1.02, etc.</div>
                  )}
                </div>
                <div className="form-group">
                  <label className="form-label">Floor</label>
                  <select className="form-input form-select" value={editData.floor} onChange={e => setEditData('floor', e.target.value)} required>
                    {availableFloors.map(f => <option key={f.id} value={f.id}>{f.label}</option>)}
                  </select>
                </div>
              </div>
              <div className="form-row">
                <div className="form-group">
                  <label className="form-label">Commercial Unit Type</label>
                  <select className="form-input form-select" value={editData.type} onChange={e => setEditData('type', e.target.value)}>
                    {COMMERCIAL_UNIT_TYPES.map(t => <option key={t}>{t}</option>)}
                  </select>
                </div>
                <div className="form-group">
                  <label className="form-label">Size (m²)</label>
                  <input className="form-input" type="number" step="0.01" min="0" value={editData.size_sqm} onChange={e => setEditData('size_sqm', e.target.value)} required />
                </div>
              </div>
              <div className="form-row">
                <div className="form-group">
                  <label className="form-label">Currency</label>
                  <select className="form-input form-select" value={editData.currency} onChange={e => setEditData('currency', e.target.value)}>
                    <option value="TZS">TZS</option>
                    <option value="USD">USD</option>
                  </select>
                </div>
                <div className="form-group">
                  <label className="form-label">Rent Rate per m² ({editData.currency})</label>
                  <input className="form-input" type="number" step="0.01" min="0" value={editData.rate_per_sqm} onChange={e => setEditData('rate_per_sqm', e.target.value)} required />
                </div>
              </div>
              <div className="form-row">
                <div className="form-group">
                  <label className="form-label">Service Charge Rate per m² ({editData.currency})</label>
                  <input className="form-input" type="number" step="0.01" min="0" value={editData.service_charge_per_sqm} onChange={e => setEditData('service_charge_per_sqm', e.target.value)} />
                </div>
                <div className="form-group">
                  <label className="form-label">Electricity Type</label>
                  <select className="form-input form-select" value={editData.electricity_type} onChange={e => setEditData('electricity_type', e.target.value)}>
                    <option value="direct">Direct (Own Meter)</option>
                    <option value="submeter">Submeter</option>
                  </select>
                </div>
              </div>
              <div className="form-row">
                <div className="form-group">
                  <label className="form-label">Monthly Rent (computed)</label>
                  <input className="form-input" type="text" value={editRent > 0 ? money(editRent.toFixed(2), editData.currency) : '—'} readOnly style={{opacity:.8,cursor:'default'}} />
                </div>
                <div className="form-group">
                  <label className="form-label">Service Charge / mo (computed)</label>
                  <input className="form-input" type="text" value={editSc > 0 ? money(editSc.toFixed(2), editData.currency) : '—'} readOnly style={{opacity:.8,cursor:'default'}} />
                </div>
              </div>
              <div className="form-row">
                <div className="form-group">
                  <label className="form-label">Security Deposit (computed)</label>
                  <input className="form-input" type="text" value={editDeposit > 0 ? money(editDeposit.toFixed(2), editData.currency) : '—'} readOnly style={{opacity:.8,cursor:'default'}} />
                </div>
                <div className="form-group">
                  <label className="form-label">Status</label>
                  <select className="form-input form-select" value={editData.status} onChange={e => setEditData('status', e.target.value)}>
                    {['vacant','occupied','overdue','maintenance'].map(s => <option key={s}>{s}</option>)}
                  </select>
                </div>
              </div>
              <div className="form-row">
                <div className="form-group" style={{flex:1}}>
                  <label className="form-label">Notes</label>
                  <input className="form-input" value={editData.notes} onChange={e => setEditData('notes', e.target.value)} placeholder="Optional notes" />
                </div>
              </div>
            </div>
            <div className="modal-footer">
              <button type="button" className="btn-ghost" onClick={() => setShowEditModal(false)} disabled={editProcessing}>Cancel</button>
              <button type="submit" className="btn-primary" disabled={editProcessing || !editUnitNumberValid} aria-busy={editProcessing}>
                {editProcessing ? (
                  <><span className="btn-spinner" aria-hidden="true"></span><span>Saving…</span></>
                ) : (
                  <span>Save Changes</span>
                )}
              </button>
            </div>
          </form>
        </div>
      </div>

      {/* Delete Confirmation Modal */}
      <div className={`modal-overlay ${showDeleteConfirm ? 'open' : ''}`} onClick={e => e.target === e.currentTarget && setShowDeleteConfirm(false)}>
        <div className="modal" style={{width: 400}}>
          <div className="modal-header">
            <div className="modal-title">Delete Unit</div>
            <button className="modal-close" onClick={() => setShowDeleteConfirm(false)}>✕</button>
          </div>
          <div className="modal-body">
            <p style={{fontSize: 14, color: 'var(--text)', marginBottom: 8}}>
              Are you sure you want to delete unit <strong>{selected?.unit_number}</strong>?
            </p>
            <p style={{fontSize: 13, color: 'var(--text-muted)'}}>
              This cannot be undone. Any associated leases must be removed first.
            </p>
          </div>
          <div className="modal-footer">
            <button className="btn-ghost" onClick={() => setShowDeleteConfirm(false)} disabled={deleting}>Cancel</button>
            <button className="btn-danger" onClick={handleDelete} disabled={deleting} aria-busy={deleting}>
              {deleting ? (
                <><span className="btn-spinner" aria-hidden="true"></span><span>Deleting…</span></>
              ) : (
                <span>Delete Unit</span>
              )}
            </button>
          </div>
        </div>
      </div>

      {/* Add Unit Modal */}
      <div className={`modal-overlay ${showModal?'open':''}`} onClick={e=>e.target===e.currentTarget&&setShowModal(false)}>
        <div className="modal">
          <div className="modal-header">
            <div className="modal-title">Add New Commercial Unit</div>
            <button className="modal-close" onClick={()=>setShowModal(false)}>✕</button>
          </div>
          <form onSubmit={submit} style={{display:'flex',flexDirection:'column',flex:1,minHeight:0,overflow:'hidden'}}>
            <div className="modal-body">
              {(!canCreateUnit || availableFloors.length === 0) && (
                <div style={{ marginBottom: 12, padding: '10px 12px', border: '1px solid var(--amber)', borderRadius: 8, background: 'var(--amber-dim)', color: 'var(--amber)', fontSize: 12.5 }}>
                  You cannot create units because no assigned property floors are available.
                </div>
              )}
              {Object.keys(errors).length > 0 && (
                <div style={{ marginBottom: 12, padding: '10px 12px', border: '1px solid var(--red)', borderRadius: 8, background: 'rgba(239,68,68,.08)', color: 'var(--red)', fontSize: 12.5 }}>
                  <strong style={{ display: 'block', marginBottom: 4 }}>Please fix the following errors:</strong>
                  <ul style={{ margin: 0, paddingLeft: 16 }}>
                    {Object.values(errors).map((msg, i) => <li key={i}>{msg}</li>)}
                  </ul>
                </div>
              )}
              <div className="form-row">
                <div className="form-group">
                  <label className="form-label">
                    Unit Number
                    <span style={{fontWeight:400,color:'var(--text-muted)',marginLeft:6,fontSize:11}}>G.01–G.99 · M.01 · F1.01 · B1.01</span>
                  </label>
                  <input
                    className={`form-input${errors.unit_number || !unitNumberValid ? ' input-error' : ''}`}
                    value={data.unit_number}
                    onChange={onUnitNumberChange}
                    placeholder="e.g. G.01 or F1.05"
                    maxLength={6}
                    required
                  />
                  {errors.unit_number && <div className="form-error">{errors.unit_number}</div>}
                  {!errors.unit_number && !unitNumberValid && (
                    <div className="form-error">Invalid format — use G.01, M.03, F1.07, B1.02, etc.</div>
                  )}
                </div>
                <div className="form-group">
                  <label className="form-label">Floor</label>
                  <select className={`form-input form-select${errors.floor ? ' input-error' : ''}`} value={data.floor} onChange={e=>setData('floor',e.target.value)} required disabled={availableFloors.length===0}>{availableFloors.map(f=><option key={f.id} value={f.id}>{f.label}</option>)}</select>
                  {errors.floor && <div className="form-error">{errors.floor}</div>}
                </div>
              </div>
              <div className="form-row">
                <div className="form-group">
                  <label className="form-label">Commercial Unit Type</label>
                  <select className={`form-input form-select${errors.type ? ' input-error' : ''}`} value={data.type} onChange={e=>setData('type',e.target.value)}>{COMMERCIAL_UNIT_TYPES.map(t=><option key={t}>{t}</option>)}</select>
                  {errors.type && <div className="form-error">{errors.type}</div>}
                </div>
                <div className="form-group">
                  <label className="form-label">Size (m²)</label>
                  <input className={`form-input${errors.size_sqm ? ' input-error' : ''}`} type="number" step="0.01" min="0" value={data.size_sqm} onChange={e=>setData('size_sqm',e.target.value)} placeholder="60" required />
                  {errors.size_sqm && <div className="form-error">{errors.size_sqm}</div>}
                </div>
              </div>
              <div className="form-row">
                <div className="form-group">
                  <label className="form-label">Currency</label>
                  <select className="form-input form-select" value={data.currency} onChange={e=>setData('currency',e.target.value)}><option value="TZS">TZS</option><option value="USD">USD</option></select>
                </div>
                <div className="form-group">
                  <label className="form-label">Rent Rate per m² ({data.currency})</label>
                  <input className={`form-input${errors.rate_per_sqm ? ' input-error' : ''}`} type="number" step="0.01" min="0" value={data.rate_per_sqm} onChange={e=>setData('rate_per_sqm',e.target.value)} placeholder={data.currency==='TZS' ? '25000' : '20'} required />
                  {errors.rate_per_sqm && <div className="form-error">{errors.rate_per_sqm}</div>}
                </div>
              </div>
              <div className="form-row">
                <div className="form-group">
                  <label className="form-label">Service Charge Rate per m² ({data.currency})</label>
                  <input className={`form-input${errors.service_charge_per_sqm ? ' input-error' : ''}`} type="number" step="0.01" min="0" value={data.service_charge_per_sqm} onChange={e=>setData('service_charge_per_sqm',e.target.value)} placeholder={data.currency==='TZS' ? '2000' : '2'} />
                  {errors.service_charge_per_sqm && <div className="form-error">{errors.service_charge_per_sqm}</div>}
                </div>
                <div className="form-group">
                  <label className="form-label">Electricity Type</label>
                  <select className="form-input form-select" value={data.electricity_type} onChange={e=>setData('electricity_type',e.target.value)}><option value="direct">Direct (Own Meter)</option><option value="submeter">Submeter</option></select>
                </div>
              </div>
              <div className="form-row">
                <div className="form-group"><label className="form-label">Computed Monthly Rent</label><input className="form-input" type="text" value={computedMonthlyRent > 0 ? money(computedMonthlyRent.toFixed(2), data.currency) : '—'} readOnly style={{opacity:.8,cursor:'default'}} /></div>
                <div className="form-group"><label className="form-label">Computed Service Charge / mo</label><input className="form-input" type="text" value={computedServiceCharge > 0 ? money(computedServiceCharge.toFixed(2), data.currency) : '—'} readOnly style={{opacity:.8,cursor:'default'}} /></div>
              </div>
              <div className="form-row">
                <div className="form-group"><label className="form-label">Computed Security Deposit</label><input className="form-input" type="text" value={computedDeposit > 0 ? money(computedDeposit.toFixed(2), data.currency) : '—'} readOnly style={{opacity:.8,cursor:'default'}} /></div>
                <div className="form-group"><label className="form-label">Status</label><select className="form-input form-select" value={data.status} onChange={e=>setData('status',e.target.value)}>{['vacant','occupied','maintenance'].map(s=><option key={s}>{s}</option>)}</select></div>
              </div>
            </div>
            <div className="modal-footer">
              <button type="button" className="btn-ghost" onClick={()=>setShowModal(false)}>Cancel</button>
              <button type="submit" className="btn-primary" disabled={processing || !canCreateUnit || availableFloors.length===0 || !unitNumberValid}>Add Unit</button>
            </div>
          </form>
        </div>
      </div>
    </AppLayout>
  );
}
