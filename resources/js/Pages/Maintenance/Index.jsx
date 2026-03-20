import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm, router } from '@inertiajs/react';
import useExchangeRate from '@/hooks/useExchangeRate';

const CAT_ICONS = { Plumbing:'🔧', Electrical:'💡', HVAC:'❄️', General:'🪛', Security:'🔒' };

export default function MaintenanceIndex({ tickets, units }) {
  const [filter, setFilter] = useState('all');
  const [search, setSearch] = useState('');
  const [selected, setSelected] = useState(null);
  const [showModal, setShowModal] = useState(false);
  const [note, setNote] = useState('');

  const { data, setData, post, processing, reset } = useForm({ title:'', description:'', unit_ref:'', category:'Plumbing', priority:'med', assignee:'' });
  const { formatTzsFromUsd, formatCompactTzsFromUsd } = useExchangeRate();

  const filtered = tickets.filter(t => {
    const matchFilter = filter === 'all' || t.status === filter;
    const q = search.toLowerCase();
    const matchSearch = !q || t.title.toLowerCase().includes(q) || t.unit_ref.toLowerCase().includes(q) || t.ticket_number.toLowerCase().includes(q);
    return matchFilter && matchSearch;
  });

  const counts = { all: tickets.length, open: tickets.filter(t=>t.status==='open').length, 'in-progress': tickets.filter(t=>t.status==='in-progress').length, resolved: tickets.filter(t=>t.status==='resolved').length };
  const totalCostUsd = tickets.reduce((sum, ticket) => sum + Number(ticket.cost || 0), 0);

  const submit = (e) => { e.preventDefault(); post('/maintenance', { onSuccess: () => { reset(); setShowModal(false); } }); };
  const updateStatus = (ticket, status) => router.patch(`/maintenance/${ticket.id}`, { status }, { onSuccess: () => setSelected(s => s ? {...s, status} : null) });
  const addNote = () => { if (!note.trim() || !selected) return; router.patch(`/maintenance/${selected.id}`, { note }, { onSuccess: () => { setNote(''); } }); };

  const notes = selected ? JSON.parse(selected.notes || '[]') : [];

  return (
    <AppLayout title="Maintenance" subtitle={`${counts.open} open`}>
      <Head title="Maintenance" />

      <div className="stats-grid">
        <div className="stat-card"><div className="stat-top"><div className="stat-icon red"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div><span className="stat-delta down">{tickets.filter(t=>t.priority==='high'&&t.status==='open').length} urgent</span></div><div className="stat-value">{counts.open + counts['in-progress']}</div><div className="stat-label">Open Tickets</div></div>
        <div className="stat-card"><div className="stat-top"><div className="stat-icon amber"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div><span className="stat-delta up">avg</span></div><div className="stat-value">3.2d</div><div className="stat-label">Avg Resolution Time</div></div>
        <div className="stat-card"><div className="stat-top"><div className="stat-icon green"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="20 6 9 17 4 12"/></svg></div><span className="stat-delta up">this month</span></div><div className="stat-value">{counts.resolved}</div><div className="stat-label">Resolved Tickets</div></div>
        <div className="stat-card"><div className="stat-top"><div className="stat-icon amber"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></div><span className="stat-delta down">↑ 5%</span></div><div className="stat-value">{formatCompactTzsFromUsd(totalCostUsd)}</div><div className="stat-label">Cost This Month</div></div>
      </div>

      <div className="toolbar">
        <div className="filters">
          {[['all','All'],['open','Open'],['in-progress','In Progress'],['resolved','Resolved']].map(([f,l])=>(
            <button key={f} className={`filter-pill ${filter===f?'active':''}`} onClick={()=>setFilter(f)}>{l} <span className="pill-count">{counts[f]||0}</span></button>
          ))}
        </div>
        <div style={{display:'flex',gap:8,alignItems:'center'}}>
          <div className="search-box" style={{width:190}}>
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <input type="text" placeholder="Search tickets…" value={search} onChange={e=>setSearch(e.target.value)} />
          </div>
          <button className="btn-primary" onClick={()=>setShowModal(true)}>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            New Ticket
          </button>
        </div>
      </div>

      <div className="card">
        <table className="data-table">
          <thead><tr><th style={{width:52}}></th><th>Issue</th><th>Unit</th><th>Category</th><th>Priority</th><th>Status</th><th>Reported</th><th>Assignee</th><th>Cost</th><th></th></tr></thead>
          <tbody>
            {filtered.map(t => (
              <tr key={t.id} onClick={()=>setSelected(t)}>
                <td style={{paddingLeft:20}}><div style={{width:34,height:34,borderRadius:9,background:'var(--bg-elevated)',display:'flex',alignItems:'center',justifyContent:'center',fontSize:16}}>{CAT_ICONS[t.category]||'🪛'}</div></td>
                <td><div style={{fontWeight:600,fontSize:'13.5px'}}>{t.title}</div><div style={{fontSize:'11.5px',color:'var(--text-muted)'}}>{t.ticket_number}</div></td>
                <td style={{fontWeight:600,color:'var(--text-secondary)'}}>{t.unit_ref}</td>
                <td style={{fontSize:'12.5px',color:'var(--text-secondary)'}}>{t.category}</td>
                <td><span className={`priority ${t.priority}`}>{t.priority==='med'?'Med':t.priority.charAt(0).toUpperCase()+t.priority.slice(1)}</span></td>
                <td><span className={`maint-status-badge ${t.status}`}>{t.status==='in-progress'?'In Progress':t.status.charAt(0).toUpperCase()+t.status.slice(1)}</span></td>
                <td style={{fontSize:12,color:'var(--text-muted)',whiteSpace:'nowrap'}}>{t.reported_date}</td>
                <td><div className="assignee-chip"><div className="assignee-dot">{t.assignee?t.assignee.slice(0,2).toUpperCase():'?'}</div>{t.assignee||'—'}</div></td>
                <td style={{fontSize:13,fontWeight:600,color:t.cost?'var(--text-secondary)':'var(--text-muted)'}}>{t.cost ? formatTzsFromUsd(t.cost) : '—'}</td>
                <td><button className="action-dots" onClick={e=>{e.stopPropagation();setSelected(t)}}>···</button></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Ticket Drawer */}
      <div className={`drawer-overlay ${selected?'open':''}`} onClick={e=>e.target===e.currentTarget&&setSelected(null)}>
        <div className="drawer">
          {selected && <>
            <div className="drawer-header">
              <div style={{display:'flex',alignItems:'flex-start',gap:14}}>
                <div style={{width:46,height:46,borderRadius:12,background:'var(--bg-surface)',border:'1px solid var(--border)',display:'flex',alignItems:'center',justifyContent:'center',fontSize:22,flexShrink:0}}>{CAT_ICONS[selected.category]||'🪛'}</div>
                <div>
                  <div style={{fontSize:17,fontWeight:700,letterSpacing:'-.3px'}}>{selected.title}</div>
                  <div style={{fontSize:12,color:'var(--text-muted)',marginTop:5,display:'flex',gap:8}}>
                    <span style={{color:selected.priority==='high'?'var(--red)':selected.priority==='med'?'var(--amber)':'var(--green)'}}>{selected.priority} priority</span>
                    <span>·</span><span>{selected.category}</span><span>·</span><span>{selected.ticket_number}</span>
                  </div>
                </div>
              </div>
              <button className="drawer-close" onClick={()=>setSelected(null)}>✕</button>
            </div>
            <div className="drawer-body">
              <div className="drawer-section">
                <div className="drawer-section-title">Status</div>
                <div style={{display:'flex',background:'var(--bg-elevated)',borderRadius:10,padding:4,marginBottom:14}}>
                  {['open','in-progress','resolved'].map(s=>(
                    <button key={s} onClick={()=>updateStatus(selected,s)} style={{flex:1,padding:'8px 10px',borderRadius:8,border:'none',fontSize:'12.5px',fontWeight:500,fontFamily:'inherit',cursor:'pointer',background:selected.status===s?(s==='open'?'var(--red-dim)':s==='in-progress'?'var(--amber-dim)':'var(--green-dim)'):'none',color:selected.status===s?(s==='open'?'var(--red)':s==='in-progress'?'var(--amber)':'var(--green)'):'var(--text-muted)',transition:'all .15s'}}>
                      {s==='in-progress'?'In Progress':s.charAt(0).toUpperCase()+s.slice(1)}
                    </button>
                  ))}
                </div>
              </div>
              <div className="drawer-section">
                <div className="drawer-section-title">Details</div>
                <div className="kv-grid">
                  <div className="kv"><div className="kv-label">Status</div><div className={`kv-value ${selected.status==='open'?'red':selected.status==='in-progress'?'amber':'green'}`}>{selected.status}</div></div>
                  <div className="kv"><div className="kv-label">Unit</div><div className="kv-value accent">{selected.unit_ref}</div></div>
                  <div className="kv"><div className="kv-label">Reported</div><div className="kv-value" style={{fontSize:'12.5px'}}>{selected.reported_date}</div></div>
                  <div className="kv"><div className="kv-label">Category</div><div className="kv-value" style={{fontSize:13}}>{selected.category}</div></div>
                </div>
              </div>
              {selected.description && (
                <div className="drawer-section">
                  <div className="drawer-section-title">Description</div>
                  <div style={{background:'var(--bg-elevated)',borderRadius:9,padding:'12px 13px',fontSize:13,color:'var(--text-secondary)',lineHeight:1.6}}>{selected.description}</div>
                </div>
              )}
              <div className="drawer-section">
                <div className="drawer-section-title">Notes & Updates</div>
                {notes.length === 0 && <div style={{fontSize:13,color:'var(--text-muted)',padding:'4px 0'}}>No notes yet.</div>}
                {notes.map((n,i)=>(
                  <div key={i} style={{display:'flex',gap:10,padding:'10px 0',borderBottom:'1px solid var(--border-subtle)'}}>
                    <div style={{width:28,height:28,borderRadius:'50%',background:'var(--accent-dim)',color:'var(--accent)',display:'flex',alignItems:'center',justifyContent:'center',fontSize:10,fontWeight:700,flexShrink:0}}>{n.av}</div>
                    <div style={{flex:1}}><div style={{fontSize:12,fontWeight:600}}>{n.author} <span style={{fontSize:11,color:'var(--text-muted)',fontWeight:400}}>{n.date}</span></div><div style={{fontSize:13,color:'var(--text-secondary)',marginTop:3,lineHeight:1.5}}>{n.text}</div></div>
                  </div>
                ))}
                <div style={{marginTop:12,display:'flex',gap:8,alignItems:'flex-start',background:'var(--bg-elevated)',borderRadius:10,padding:'10px 12px',border:'1px solid var(--border)'}}>
                  <textarea value={note} onChange={e=>setNote(e.target.value)} placeholder="Add a note…" rows={2} style={{flex:1,background:'none',border:'none',outline:'none',fontSize:13,fontFamily:'inherit',color:'var(--text-primary)',resize:'none',minHeight:36,lineHeight:1.5}} />
                  <button onClick={addNote} style={{width:30,height:30,borderRadius:7,background:'var(--accent)',border:'none',color:'#fff',display:'flex',alignItems:'center',justifyContent:'center',cursor:'pointer',flexShrink:0,alignSelf:'flex-end'}}>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                  </button>
                </div>
              </div>
            </div>
            <div className="drawer-footer">
              <button className="btn-primary" style={{flex:1,justifyContent:'center'}} onClick={()=>updateStatus(selected,selected.status==='resolved'?'open':'resolved')}>
                {selected.status==='resolved'?'↺ Reopen':'✓ Mark Resolved'}
              </button>
              <button className="btn-danger" onClick={()=>{router.delete(`/maintenance/${selected.id}`);setSelected(null);}}>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
              </button>
            </div>
          </>}
        </div>
      </div>

      {/* New Ticket Modal */}
      <div className={`modal-overlay ${showModal?'open':''}`} onClick={e=>e.target===e.currentTarget&&setShowModal(false)}>
        <div className="modal" style={{width:500}}>
          <div className="modal-header"><div className="modal-title">New Maintenance Ticket</div><button className="modal-close" onClick={()=>setShowModal(false)}>✕</button></div>
          <form onSubmit={submit}>
            <div className="modal-body">
              <div className="form-row"><div className="form-group"><label className="form-label">Issue Title *</label><input className="form-input" value={data.title} onChange={e=>setData('title',e.target.value)} placeholder="Briefly describe the issue…" required /></div></div>
              <div className="form-row">
                <div className="form-group"><label className="form-label">Category *</label><select className="form-input form-select" value={data.category} onChange={e=>setData('category',e.target.value)}>{['Plumbing','Electrical','HVAC','General','Security'].map(c=><option key={c}>{c}</option>)}</select></div>
                <div className="form-group"><label className="form-label">Priority *</label><select className="form-input form-select" value={data.priority} onChange={e=>setData('priority',e.target.value)}><option value="high">High — Urgent</option><option value="med">Medium</option><option value="low">Low</option></select></div>
              </div>
              <div className="form-row">
                <div className="form-group"><label className="form-label">Unit *</label><select className="form-input form-select" value={data.unit_ref} onChange={e=>setData('unit_ref',e.target.value)} required><option value="">Select unit…</option><option value="Common">Common Area</option>{units.map(u=><option key={u.id} value={u.unit_number}>{u.unit_number}</option>)}</select></div>
                <div className="form-group"><label className="form-label">Assign To</label><select className="form-input form-select" value={data.assignee} onChange={e=>setData('assignee',e.target.value)}><option value="">Unassigned</option>{['Peter Ng. (Plumber)','JK Electric','Cool Air Ltd','In-house Team','SecurePro'].map(a=><option key={a}>{a}</option>)}</select></div>
              </div>
              <div className="form-row"><div className="form-group"><label className="form-label">Description</label><textarea className="form-input" value={data.description} onChange={e=>setData('description',e.target.value)} rows={3} style={{resize:'vertical'}} placeholder="Additional details…" /></div></div>
            </div>
            <div className="modal-footer">
              <button type="button" className="btn-ghost" onClick={()=>setShowModal(false)}>Cancel</button>
              <button type="submit" className="btn-primary" disabled={processing}>Create Ticket</button>
            </div>
          </form>
        </div>
      </div>
    </AppLayout>
  );
}
