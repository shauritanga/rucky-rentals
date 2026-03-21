import { useMemo, useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

const TEAM_MODULES = [
  { key:'dashboard', label:'Dashboard', icon:'📊' },
  { key:'units', label:'Units', icon:'🏢' },
  { key:'tenants', label:'Tenants', icon:'👥' },
  { key:'leases', label:'Leases', icon:'📄' },
  { key:'payments', label:'Payments', icon:'💳' },
  { key:'invoices', label:'Invoices', icon:'🧾' },
  { key:'maintenance', label:'Maintenance', icon:'🔧' },
  { key:'documents', label:'Documents', icon:'📁' },
  { key:'electricity', label:'Electricity', icon:'⚡' },
  { key:'accounting', label:'Accounting', icon:'💰' },
  { key:'reports', label:'Reports', icon:'📈' },
  { key:'team', label:'Team Management', icon:'👤' },
];

const ROLE_LABELS = {
  accountant: 'Accountant',
  lease_manager: 'Lease Manager',
  maintenance_staff: 'Maintenance Staff',
  viewer: 'Viewer',
};

const ROLE_COLORS = {
  accountant: { bg:'var(--amber-dim)', color:'var(--amber)' },
  lease_manager: { bg:'var(--accent-dim)', color:'var(--accent)' },
  maintenance_staff: { bg:'var(--red-dim)', color:'var(--red)' },
  viewer: { bg:'var(--bg-elevated)', color:'var(--text-muted)' },
};

function initials(name) {
  return String(name || '')
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0])
    .join('')
    .toUpperCase() || 'U';
}

export default function TeamIndex({ teamMembers = [], roleDefaults = {} }) {
  const [team, setTeam] = useState(teamMembers);
  const [teamFilter, setTeamFilter] = useState('all');
  const [showModal, setShowModal] = useState(false);
  const [showPermDrawer, setShowPermDrawer] = useState(false);
  const [selectedMember, setSelectedMember] = useState(null);
  const [permDraft, setPermDraft] = useState({});

  const { data, setData, post, processing, reset } = useForm({
    name: '', email: '', phone: '', role: '', password: '', permissions: {},
  });

  const counts = useMemo(() => {
    const c = { all: team.length, accountant: 0, lease_manager: 0, maintenance_staff: 0, viewer: 0 };
    team.forEach((m) => { c[m.role] = (c[m.role] || 0) + 1; });
    return c;
  }, [team]);

  const rows = useMemo(
    () => team.filter((m) => teamFilter === 'all' || m.role === teamFilter),
    [team, teamFilter],
  );

  const onRoleChange = (role) => {
    setData('role', role);
    setData('permissions', roleDefaults?.[role] || {});
  };

  const submit = (e) => {
    e.preventDefault();
    post('/team', {
      onSuccess: () => {
        reset();
        setShowModal(false);
        router.reload({ only: ['teamMembers'] });
      },
    });
  };

  const removeMember = (member) => {
    if (!window.confirm(`Remove ${member.name} from the team?`)) return;
    router.delete(`/team/${member.id}`, {
      onSuccess: () => setTeam((prev) => prev.filter((m) => m.id !== member.id)),
    });
  };

  const openPerms = (member) => {
    setSelectedMember(member);
    setPermDraft({ ...(member.permissions || roleDefaults?.[member.role] || {}) });
    setShowPermDrawer(true);
  };

  const toggleStatus = (member) => {
    router.patch(`/team/${member.id}/status`, {}, {
      onSuccess: () => {
        setTeam((prev) => prev.map((m) => {
          if (m.id !== member.id) return m;
          return {
            ...m,
            status: m.status === 'suspended' ? 'active' : 'suspended',
          };
        }));
      },
    });
  };

  const savePerms = () => {
    if (!selectedMember) return;
    router.patch(`/team/${selectedMember.id}/permissions`, { permissions: permDraft }, {
      onSuccess: () => {
        setTeam((prev) => prev.map((m) => (m.id === selectedMember.id ? { ...m, permissions: { ...permDraft } } : m)));
        setShowPermDrawer(false);
        setSelectedMember(null);
      },
    });
  };

  return (
    <AppLayout title="Team" subtitle="Staff and access control">
      <Head title="Team" />

      <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',marginBottom:22}}>
        <div>
          <div style={{fontSize:18,fontWeight:700,letterSpacing:'-.3px'}}>Building Team</div>
          <div style={{fontSize:13,color:'var(--text-muted)',marginTop:3}}>Staff you manage for this property</div>
        </div>
        <button className="btn-primary" onClick={() => setShowModal(true)}>
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Add Staff Member
        </button>
      </div>

      <div className="toolbar" style={{marginBottom:18}}>
        <div className="filters">
          <button className={`filter-pill ${teamFilter==='all'?'active':''}`} onClick={() => setTeamFilter('all')}>All <span className="pill-count">{counts.all}</span></button>
          <button className={`filter-pill ${teamFilter==='accountant'?'active':''}`} onClick={() => setTeamFilter('accountant')}>Accountant <span className="pill-count">{counts.accountant}</span></button>
          <button className={`filter-pill ${teamFilter==='lease_manager'?'active':''}`} onClick={() => setTeamFilter('lease_manager')}>Lease Manager <span className="pill-count">{counts.lease_manager}</span></button>
          <button className={`filter-pill ${teamFilter==='maintenance_staff'?'active':''}`} onClick={() => setTeamFilter('maintenance_staff')}>Maintenance <span className="pill-count">{counts.maintenance_staff}</span></button>
          <button className={`filter-pill ${teamFilter==='viewer'?'active':''}`} onClick={() => setTeamFilter('viewer')}>Viewer <span className="pill-count">{counts.viewer}</span></button>
        </div>
      </div>

      <div className="card" style={{marginBottom:20}}>
        <table className="data-table">
          <thead><tr><th>Staff Member</th><th>Role</th><th>Module Access</th><th>Status</th><th>Last Active</th><th></th></tr></thead>
          <tbody>
            {!rows.length && (
              <tr><td colSpan={6} style={{textAlign:'center',padding:40,color:'var(--text-muted)'}}>No team members yet - add your first staff member</td></tr>
            )}
            {rows.map((m) => {
              const colors = ROLE_COLORS[m.role] || ROLE_COLORS.viewer;
              const activeModules = TEAM_MODULES.filter((mod) => (m.permissions || {})[mod.key]);
              const isActive = (m.status || 'active') === 'active';
              return (
                <tr key={m.id}>
                  <td>
                    <div style={{display:'flex',alignItems:'center',gap:10}}>
                      <div style={{width:32,height:32,borderRadius:'50%',background:colors.bg,color:colors.color,display:'flex',alignItems:'center',justifyContent:'center',fontSize:11,fontWeight:700,flexShrink:0}}>{initials(m.name)}</div>
                      <div>
                        <div style={{fontWeight:600,fontSize:13.5}}>{m.name}</div>
                        <div style={{fontSize:12,color:'var(--text-muted)'}}>{m.email}</div>
                      </div>
                    </div>
                  </td>
                  <td><span style={{fontSize:12,fontWeight:600,padding:'3px 10px',borderRadius:20,background:colors.bg,color:colors.color}}>{ROLE_LABELS[m.role] || m.role}</span></td>
                  <td>
                    <div style={{display:'flex',alignItems:'center',gap:6,flexWrap:'wrap'}}>
                      {activeModules.slice(0, 5).map((mod) => <span key={mod.key} style={{fontSize:11,background:'var(--bg-elevated)',borderRadius:5,padding:'2px 7px',color:'var(--text-secondary)'}}>{mod.icon} {mod.label}</span>)}
                      {activeModules.length > 5 && <span style={{fontSize:11,color:'var(--text-muted)'}}>+{activeModules.length - 5} more</span>}
                    </div>
                  </td>
                  <td><span style={{fontSize:12,fontWeight:600,color:isActive ? 'var(--green)' : 'var(--red)'}}>{isActive ? '● Active' : '● Suspended'}</span></td>
                  <td style={{fontSize:12.5,color:'var(--text-muted)'}}>{m.last_active || 'Recently'}</td>
                  <td>
                    <div style={{display:'flex',gap:6,justifyContent:'flex-end'}}>
                      <button className="btn-secondary" style={{fontSize:12,padding:'5px 10px'}} onClick={() => openPerms(m)}>
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        Permissions
                      </button>
                      <button className="btn-danger" style={{fontSize:12,padding:'5px 10px'}} onClick={() => removeMember(m)}>
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
                      </button>
                      <button className="btn-secondary" style={{fontSize:12,padding:'5px 10px'}} onClick={() => toggleStatus(m)}>
                        {isActive ? 'Suspend' : 'Activate'}
                      </button>
                    </div>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      <div className="card">
        <div className="card-header">
          <div>
            <div className="card-title">Role Permissions Reference</div>
            <div className="card-sub">Default access levels by role - customise per user when adding</div>
          </div>
        </div>
        <div style={{overflowX:'auto',padding:'0 20px 20px'}}>
          <table style={{width:'100%',borderCollapse:'collapse',fontSize:13}}>
            <thead>
              <tr style={{borderBottom:'1px solid var(--border-subtle)'}}>
                <th style={{textAlign:'left',padding:'8px 12px',fontSize:11,fontWeight:600,letterSpacing:'.4px',textTransform:'uppercase',color:'var(--text-muted)'}}>Module</th>
                <th style={{textAlign:'center',padding:'8px 12px',fontSize:11,fontWeight:600,letterSpacing:'.4px',textTransform:'uppercase',color:'var(--text-muted)'}}>Accountant</th>
                <th style={{textAlign:'center',padding:'8px 12px',fontSize:11,fontWeight:600,letterSpacing:'.4px',textTransform:'uppercase',color:'var(--text-muted)'}}>Lease Manager</th>
                <th style={{textAlign:'center',padding:'8px 12px',fontSize:11,fontWeight:600,letterSpacing:'.4px',textTransform:'uppercase',color:'var(--text-muted)'}}>Maintenance</th>
                <th style={{textAlign:'center',padding:'8px 12px',fontSize:11,fontWeight:600,letterSpacing:'.4px',textTransform:'uppercase',color:'var(--text-muted)'}}>Viewer</th>
              </tr>
            </thead>
            <tbody>
              {TEAM_MODULES.map((mod) => (
                <tr key={mod.key} style={{borderBottom:'1px solid var(--border-subtle)'}}>
                  <td style={{padding:'9px 12px',fontSize:13}}>{mod.icon} {mod.label}</td>
                  <td style={{textAlign:'center',padding:'9px 12px'}}>{roleDefaults?.accountant?.[mod.key] ? '✓' : '—'}</td>
                  <td style={{textAlign:'center',padding:'9px 12px'}}>{roleDefaults?.lease_manager?.[mod.key] ? '✓' : '—'}</td>
                  <td style={{textAlign:'center',padding:'9px 12px'}}>{roleDefaults?.maintenance_staff?.[mod.key] ? '✓' : '—'}</td>
                  <td style={{textAlign:'center',padding:'9px 12px'}}>{roleDefaults?.viewer?.[mod.key] ? '✓' : '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      <div className={`modal-overlay ${showModal ? 'open' : ''}`} onClick={(e) => e.target === e.currentTarget && setShowModal(false)}>
        <div className="modal" style={{width:640,maxHeight:'90vh',display:'flex',flexDirection:'column'}}>
          <div className="modal-header" style={{flexShrink:0}}>
            <div className="modal-title">Add Staff Member</div>
            <button className="modal-close" onClick={() => setShowModal(false)}>✕</button>
          </div>
          <form onSubmit={submit} style={{display:'flex',flexDirection:'column',minHeight:0,flex:1}}>
            <div className="modal-body" style={{flex:1,overflowY:'auto'}}>
              <div style={{fontSize:10.5,fontWeight:700,letterSpacing:'.6px',textTransform:'uppercase',color:'var(--text-muted)',marginBottom:12}}>Personal Information</div>
              <div className="form-row">
                <div className="form-group"><label className="form-label">Full Name *</label><input className="form-input" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="e.g. David Kamau" required /></div>
                <div className="form-group"><label className="form-label">Email *</label><input className="form-input" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} placeholder="e.g. david@ruckyrentals.co.tz" required /></div>
              </div>
              <div className="form-row">
                <div className="form-group"><label className="form-label">Phone</label><input className="form-input" value={data.phone} onChange={(e) => setData('phone', e.target.value)} placeholder="+255 7xx xxx xxx" /></div>
                <div className="form-group"><label className="form-label">Role *</label>
                  <select className="form-input form-select" value={data.role} onChange={(e) => onRoleChange(e.target.value)} required>
                    <option value="">Select role…</option>
                    <option value="accountant">Accountant</option>
                    <option value="lease_manager">Lease Manager</option>
                    <option value="maintenance_staff">Maintenance Staff</option>
                    <option value="viewer">Viewer (Read-Only)</option>
                  </select>
                </div>
              </div>
              <div className="form-row">
                <div className="form-group"><label className="form-label">Temporary Password *</label><input className="form-input" value={data.password} onChange={(e) => setData('password', e.target.value)} placeholder="They must change on first login" required /></div>
              </div>

              <div style={{borderTop:'1px solid var(--border-subtle)',paddingTop:18,marginTop:6}}>
                <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',marginBottom:12}}>
                  <div style={{fontSize:10.5,fontWeight:700,letterSpacing:'.6px',textTransform:'uppercase',color:'var(--text-muted)'}}>Module Permissions</div>
                  <div style={{fontSize:12,color:'var(--text-muted)'}}>Auto-filled from role</div>
                </div>
                <div style={{display:'grid',gridTemplateColumns:'1fr 1fr',gap:8}}>
                  {TEAM_MODULES.map((mod) => {
                    const on = !!data.permissions?.[mod.key];
                    return (
                      <div key={mod.key} style={{display:'flex',alignItems:'center',justifyContent:'space-between',background:'var(--bg-elevated)',borderRadius:8,padding:'10px 12px'}}>
                        <div style={{fontSize:13}}>{mod.icon} {mod.label}</div>
                        <button
                          type="button"
                          className={`pref-toggle ${on ? 'on' : 'off'}`}
                          onClick={() => setData('permissions', { ...data.permissions, [mod.key]: !on })}
                        ></button>
                      </div>
                    );
                  })}
                </div>
              </div>
            </div>
            <div className="modal-footer" style={{flexShrink:0}}>
              <button type="button" className="btn-ghost" onClick={() => setShowModal(false)}>Cancel</button>
              <button type="submit" className="btn-primary" disabled={processing}>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/><circle cx="8.5" cy="7" r="4"/></svg>
                Add Staff Member
              </button>
            </div>
          </form>
        </div>
      </div>

      <div className={`modal-overlay ${showPermDrawer ? 'open' : ''}`} onClick={(e) => e.target === e.currentTarget && setShowPermDrawer(false)}>
        <div className="modal" style={{width:520,maxHeight:'90vh',display:'flex',flexDirection:'column'}}>
          <div className="modal-header" style={{flexShrink:0}}>
            <div>
              <div className="modal-title">{selectedMember?.name || 'Edit Permissions'}</div>
              <div style={{fontSize:12,color:'var(--text-muted)',marginTop:3}}>{ROLE_LABELS[selectedMember?.role] || selectedMember?.role}</div>
            </div>
            <button className="modal-close" onClick={() => setShowPermDrawer(false)}>✕</button>
          </div>
          <div className="modal-body" style={{flex:1,overflowY:'auto'}}>
            <div style={{display:'grid',gridTemplateColumns:'1fr 1fr',gap:8,paddingTop:4}}>
              {TEAM_MODULES.map((mod) => {
                const on = !!permDraft?.[mod.key];
                return (
                  <div key={mod.key} style={{display:'flex',alignItems:'center',justifyContent:'space-between',background:'var(--bg-elevated)',borderRadius:8,padding:'10px 12px'}}>
                    <div style={{fontSize:13}}>{mod.icon} {mod.label}</div>
                    <button type="button" className={`pref-toggle ${on ? 'on' : 'off'}`} onClick={() => setPermDraft((prev) => ({ ...prev, [mod.key]: !on }))}></button>
                  </div>
                );
              })}
            </div>
          </div>
          <div className="modal-footer" style={{flexShrink:0}}>
            <button className="btn-ghost" onClick={() => setShowPermDrawer(false)}>Cancel</button>
            <button className="btn-primary" onClick={savePerms}>Save Permissions</button>
          </div>
        </div>
      </div>
    </AppLayout>
  );
}
