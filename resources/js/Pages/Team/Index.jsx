import { useMemo, useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { formatDisplayDate } from '@/utils/dateFormat';

const RESOURCES = [
  { key:'units', label:'Units', icon:'🏢', actions:['read','update'], desc:'View and edit unit records' },
  { key:'tenants', label:'Tenants', icon:'👥', actions:['create','read','update','delete'], desc:'Manage tenant profiles' },
  { key:'leases', label:'Leases', icon:'📄', actions:['create','read','update'], desc:'Create and manage lease agreements' },
  { key:'invoices', label:'Invoices', icon:'🧾', actions:['create','read','update'], desc:'Issue and manage invoices' },
  { key:'payments', label:'Payments', icon:'💳', actions:['create','read'], desc:'Record and view payments' },
  { key:'maintenance', label:'Maintenance', icon:'🔧', actions:['create','read','update','delete'], desc:'Submit and manage maintenance requests' },
  { key:'documents', label:'Documents', icon:'📁', actions:['create','read','delete'], desc:'Upload, view and remove documents' },
  { key:'electricity', label:'Electricity', icon:'⚡', actions:['create','read','update'], desc:'Post readings and manage billing' },
  { key:'accounting', label:'Accounting', icon:'💰', actions:['create','read'], desc:'Post journal entries, view accounts' },
  { key:'reports', label:'Reports', icon:'📈', actions:['read'], desc:'View financial and operational reports' },
  { key:'team', label:'Team Management', icon:'👤', actions:['create','read','update','delete'], desc:'Add, edit and remove staff members' },
  { key:'audit', label:'Audit Trail', icon:'📋', actions:['read'], desc:'View activity log (always read-only)' },
];

const ACTION_CFG = {
  create: { label:'C', full:'Create', color:'var(--green)', tip:'Create new records' },
  read: { label:'R', full:'Read', color:'var(--accent)', tip:'View records' },
  update: { label:'U', full:'Update', color:'var(--amber)', tip:'Edit existing records' },
  delete: { label:'D', full:'Delete', color:'var(--red)', tip:'Permanently delete records' },
};

const ROLE_LABELS = {
  accountant: 'Accountant',
  lease_manager: 'Lease Assistant',
  maintenance_staff: 'Maintenance Staff',
  viewer: 'Viewer',
};

const ROLE_COLORS = {
  accountant: { bg:'var(--amber-dim)', color:'var(--amber)' },
  lease_manager: { bg:'var(--accent-dim)', color:'var(--accent)' },
  maintenance_staff: { bg:'var(--red-dim)', color:'var(--red)' },
  viewer: { bg:'var(--bg-elevated)', color:'var(--text-muted)' },
};

const STATUS_META = {
  active: { label: 'Active', color: 'var(--green)' },
  suspended: { label: 'Suspended', color: 'var(--red)' },
  pending_approval: { label: 'Pending Approval', color: 'var(--amber)' },
  rejected: { label: 'Rejected', color: 'var(--red)' },
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

function normalizePermissions(rawPermissions = {}) {
  return RESOURCES.reduce((acc, resource) => {
    const value = rawPermissions?.[resource.key];

    if (typeof value === 'boolean') {
      // Backward compatibility: legacy bool=true maps to full access on that resource.
      acc[resource.key] = value
        ? Object.fromEntries(resource.actions.map((action) => [action, true]))
        : {};
      return acc;
    }

    if (value && typeof value === 'object' && !Array.isArray(value)) {
      acc[resource.key] = Object.fromEntries(
        resource.actions.map((action) => [action, !!value[action]]),
      );
      return acc;
    }

    acc[resource.key] = {};
    return acc;
  }, {});
}

function grantedActionCount(permissions = {}) {
  return RESOURCES.reduce((count, resource) => {
    const granted = resource.actions.filter((action) => !!permissions?.[resource.key]?.[action]).length;
    return count + granted;
  }, 0);
}

function accessTag(permissions = {}, resource) {
  const granted = resource.actions.filter((action) => !!permissions?.[resource.key]?.[action]);
  if (!granted.length) return null;
  if (granted.length === resource.actions.length) return 'Manage';
  return granted.map((action) => ACTION_CFG[action].label).join('');
}

export default function TeamIndex({ teamMembers = [], archivedMembers = [], roleDefaults = {} }) {
  const normalizedRoleDefaults = useMemo(() => {
    return Object.fromEntries(
      Object.entries(roleDefaults || {}).map(([role, defaults]) => [role, normalizePermissions(defaults)]),
    );
  }, [roleDefaults]);

  const [team, setTeam] = useState(
    teamMembers.map((member) => ({
      ...member,
      permissions: normalizePermissions(member.permissions || {}),
    })),
  );
  const [archived, setArchived] = useState(
    archivedMembers.map((member) => ({
      ...member,
      permissions: normalizePermissions(member.permissions || {}),
    })),
  );
  const [teamFilter, setTeamFilter] = useState('all');
  const [viewTab, setViewTab] = useState('active');
  const [activePage, setActivePage] = useState(1);
  const [archivedPage, setArchivedPage] = useState(1);
  const PER_PAGE = 10;
  const [showModal, setShowModal] = useState(false);
  const [showPermDrawer, setShowPermDrawer] = useState(false);
  const [selectedMember, setSelectedMember] = useState(null);
  const [permDraft, setPermDraft] = useState({});
  const [showDeleteDialog, setShowDeleteDialog] = useState(false);
  const [deleteTarget, setDeleteTarget] = useState(null);
  const [deleteConfirmName, setDeleteConfirmName] = useState('');
  const [deleteSubmitting, setDeleteSubmitting] = useState(false);
  const [restoreSubmittingId, setRestoreSubmittingId] = useState(null);

  const { data, setData, post, processing, reset } = useForm({
    name: '', email: '', phone: '', role: '', permissions: {},
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

  const activeTotalPages = Math.max(1, Math.ceil(rows.length / PER_PAGE));
  const safeActivePage = Math.min(activePage, activeTotalPages);
  const paginatedRows = rows.slice((safeActivePage - 1) * PER_PAGE, safeActivePage * PER_PAGE);

  const archivedTotalPages = Math.max(1, Math.ceil(archived.length / PER_PAGE));
  const safeArchivedPage = Math.min(archivedPage, archivedTotalPages);
  const paginatedArchived = archived.slice((safeArchivedPage - 1) * PER_PAGE, safeArchivedPage * PER_PAGE);

  const setFilter = (f) => { setTeamFilter(f); setActivePage(1); };

  const onRoleChange = (role) => {
    setData('role', role);
    setData('permissions', normalizedRoleDefaults?.[role] || {});
  };

  const submit = (e) => {
    e.preventDefault();
    post('/team', {
      onSuccess: () => {
        reset();
        setShowModal(false);
        router.reload({ only: ['teamMembers', 'archivedMembers'] });
      },
    });
  };

  const openDeleteDialog = (member) => {
    setDeleteTarget(member);
    setDeleteConfirmName('');
    setShowDeleteDialog(true);
  };

  const closeDeleteDialog = () => {
    setShowDeleteDialog(false);
    setDeleteTarget(null);
    setDeleteConfirmName('');
    setDeleteSubmitting(false);
  };

  const confirmDeleteMember = () => {
    if (!deleteTarget || deleteSubmitting) return;
    setDeleteSubmitting(true);
    router.delete(`/team/${deleteTarget.id}`, {
      data: {
        confirm_name: deleteConfirmName,
      },
      onSuccess: () => {
        setTeam((prev) => prev.filter((m) => m.id !== deleteTarget.id));
        setArchived((prev) => [{ ...deleteTarget, deleted_at: 'just now' }, ...prev]);
        closeDeleteDialog();
      },
      onError: () => {
        setDeleteSubmitting(false);
      },
      onFinish: () => {
        setDeleteSubmitting(false);
      },
    });
  };

  const openPerms = (member) => {
    setSelectedMember(member);
    setPermDraft({ ...(member.permissions || normalizedRoleDefaults?.[member.role] || {}) });
    setShowPermDrawer(true);
  };

  const togglePermission = (draft, resourceKey, action) => {
    const next = { ...(draft || {}) };
    const current = { ...(next[resourceKey] || {}) };
    current[action] = !current[action];
    next[resourceKey] = current;
    return next;
  };

  const toggleManagePermissions = (draft, resourceKey) => {
    const resource = RESOURCES.find((item) => item.key === resourceKey);
    if (!resource) return draft;
    const current = draft?.[resourceKey] || {};
    const allOn = resource.actions.every((action) => !!current[action]);
    const next = { ...(draft || {}) };
    next[resourceKey] = Object.fromEntries(resource.actions.map((action) => [action, !allOn]));
    return next;
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

  const restoreMember = (member) => {
    if (restoreSubmittingId) return;
    setRestoreSubmittingId(member.id);
    router.patch(`/team/${member.id}/restore`, {}, {
      onSuccess: () => {
        setArchived((prev) => prev.filter((m) => m.id !== member.id));
        setTeam((prev) => [{ ...member, status: 'active', deleted_at: null }, ...prev]);
      },
      onFinish: () => {
        setRestoreSubmittingId(null);
      },
    });
  };

  const resubmitMember = (member) => {
    router.post(`/team/${member.id}/resubmit`, {}, {
      onSuccess: () => {
        router.reload({ only: ['teamMembers'] });
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

      <div className="team-tabs">
        <button
          className={`team-tab ${viewTab==='active'?'active':''}`}
          onClick={() => setViewTab('active')}
        >
          Team Members <span className="team-tab-count">{team.length}</span>
        </button>
        <button
          className={`team-tab ${viewTab==='archived'?'active':''}`}
          onClick={() => setViewTab('archived')}
        >
          Archived Members <span className="team-tab-count">{archived.length}</span>
        </button>
        <button
          className={`team-tab ${viewTab==='permissions'?'active':''}`}
          onClick={() => setViewTab('permissions')}
        >
          Permissions Reference
        </button>
      </div>

      {viewTab === 'active' && (
      <>
      <div className="units-toolbar" style={{marginBottom:18}}>
        <div className="units-filters">
          <button className={`filter-pill ${teamFilter==='all'?'active':''}`} onClick={() => setFilter('all')}>All <span className="pill-count">{counts.all}</span></button>
          <button className={`filter-pill ${teamFilter==='accountant'?'active':''}`} onClick={() => setFilter('accountant')}>Accountant <span className="pill-count">{counts.accountant}</span></button>
          <button className={`filter-pill ${teamFilter==='lease_manager'?'active':''}`} onClick={() => setFilter('lease_manager')}>Lease Assistant <span className="pill-count">{counts.lease_manager}</span></button>
          <button className={`filter-pill ${teamFilter==='maintenance_staff'?'active':''}`} onClick={() => setFilter('maintenance_staff')}>Maintenance <span className="pill-count">{counts.maintenance_staff}</span></button>
          <button className={`filter-pill ${teamFilter==='viewer'?'active':''}`} onClick={() => setFilter('viewer')}>Viewer <span className="pill-count">{counts.viewer}</span></button>
        </div>
      </div>

      <div className="card" style={{marginBottom:20}}>
        <table className="units-list-table">
          <thead><tr><th>Staff Member</th><th>Role</th><th>Resource Access</th><th style={{textAlign:'center'}}>Actions</th><th>Status</th><th></th></tr></thead>
          <tbody>
            {!rows.length && (
              <tr><td colSpan={6} style={{textAlign:'center',padding:40,color:'var(--text-muted)'}}>No team members yet — add your first staff member</td></tr>
            )}
            {paginatedRows.map((m) => {
              const colors = ROLE_COLORS[m.role] || ROLE_COLORS.viewer;
              const activeResources = RESOURCES.filter((resource) => accessTag(m.permissions, resource));
              const topResources = activeResources.slice(0, 4);
              const moreCount = Math.max(0, activeResources.length - 4);
              const statusKey = m.status || 'active';
              const isActive = statusKey === 'active';
              const isPending = statusKey === 'pending_approval';
              const isRejected = statusKey === 'rejected';
              const statusInfo = STATUS_META[statusKey] || STATUS_META.active;
              const granted = grantedActionCount(m.permissions || {});
              return (
                <tr key={m.id}>
                  <td>
                    <div style={{display:'flex',alignItems:'center',gap:10}}>
                      <div style={{width:32,height:32,borderRadius:'50%',background:colors.bg,color:colors.color,display:'flex',alignItems:'center',justifyContent:'center',fontSize:11,fontWeight:700,flexShrink:0}}>{initials(m.name)}</div>
                      <div>
                        <div style={{fontWeight:600,fontSize:13.5}}>{m.name}</div>
                        <div style={{fontSize:12,color:'var(--text-muted)'}}>{m.email}</div>
                        {m.requested_by && <div style={{fontSize:11.5,color:'var(--text-muted)'}}>Requested by {m.requested_by}</div>}
                        {isRejected && m.approval_note && <div style={{fontSize:11.5,color:'var(--red)'}}>Rejected: {m.approval_note}</div>}
                      </div>
                    </div>
                  </td>
                  <td><span style={{fontSize:12,fontWeight:600,padding:'3px 10px',borderRadius:20,background:colors.bg,color:colors.color}}>{ROLE_LABELS[m.role] || m.role}</span></td>
                  <td>
                    <div style={{display:'flex',alignItems:'center',gap:5,flexWrap:'wrap'}}>
                      {topResources.map((resource) => (
                        <span key={resource.key} title={resource.label} style={{fontSize:11,background:'var(--bg-elevated)',border:'1px solid var(--border-subtle)',borderRadius:5,padding:'2px 7px',color:'var(--text-secondary)'}}>
                          {resource.icon} {resource.label} <span style={{fontWeight:700,letterSpacing:'.5px'}}>{accessTag(m.permissions, resource)}</span>
                        </span>
                      ))}
                      {moreCount > 0 && <span style={{fontSize:11,color:'var(--text-muted)'}}>+{moreCount} more</span>}
                    </div>
                  </td>
                  <td>
                    <div style={{fontSize:12.5,color:'var(--text-muted)',textAlign:'center'}}>{granted} actions</div>
                  </td>
                  <td>
                    <div style={{fontSize:12,fontWeight:600,color:statusInfo.color}}>● {statusInfo.label}</div>
                    {(isPending || isRejected) && (
                      <div style={{fontSize:11,color:'var(--text-muted)',marginTop:2}}>
                        {formatDisplayDate(isPending ? m.approval_requested_at : m.approval_decided_at)}
                      </div>
                    )}
                  </td>
                  <td>
                    <div style={{display:'flex',gap:6,justifyContent:'flex-end'}}>
                      <button className="btn-secondary" style={{fontSize:12,padding:'5px 10px'}} onClick={() => openPerms(m)}>
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        Permissions
                      </button>
                      {!isPending && !isRejected && (
                        <button className="btn-secondary" style={{fontSize:12,padding:'5px 10px'}} onClick={() => toggleStatus(m)}>
                          {isActive ? 'Suspend' : 'Activate'}
                        </button>
                      )}
                      {isRejected && (
                        <button className="btn-secondary" style={{fontSize:12,padding:'5px 10px'}} onClick={() => resubmitMember(m)}>
                          Resubmit
                        </button>
                      )}
                      <button className="btn-danger" style={{fontSize:12,padding:'5px 10px'}} onClick={() => openDeleteDialog(m)}>
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
                      </button>
                    </div>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      {activeTotalPages > 1 && (
        <div style={{display:'flex',alignItems:'center',justifyContent:'center',gap:6,marginTop:4,marginBottom:20}}>
          <button className="btn-ghost" style={{fontSize:12,padding:'5px 10px'}} disabled={safeActivePage===1} onClick={() => setActivePage((p) => p - 1)}>← Prev</button>
          {Array.from({length:activeTotalPages},(_,i)=>i+1).map((p) => (
            <button key={p} onClick={() => setActivePage(p)} style={{fontSize:12,padding:'5px 10px',borderRadius:6,border:'1px solid var(--border)',background:p===safeActivePage?'var(--accent)':'transparent',color:p===safeActivePage?'#fff':'var(--text-secondary)',fontWeight:p===safeActivePage?700:400,cursor:'pointer'}}>
              {p}
            </button>
          ))}
          <button className="btn-ghost" style={{fontSize:12,padding:'5px 10px'}} disabled={safeActivePage===activeTotalPages} onClick={() => setActivePage((p) => p + 1)}>Next →</button>
        </div>
      )}
      </>
      )}

      {viewTab === 'archived' && (
      <>
      <div className="card" style={{marginBottom:20}}>
        <div className="card-header">
          <div>
            <div className="card-title">Archived Team Members</div>
            <div className="card-sub">Soft-deleted team members can be restored here</div>
          </div>
          <div style={{fontSize:12,color:'var(--text-muted)',fontWeight:600}}>{archived.length} archived</div>
        </div>
        <div style={{overflowX:'auto'}}>
          <table className="units-list-table">
            <thead>
              <tr>
                <th>Staff Member</th>
                <th>Role</th>
                <th>Archived</th>
                <th style={{textAlign:'right'}}>Action</th>
              </tr>
            </thead>
            <tbody>
              {!archived.length && (
                <tr>
                  <td colSpan={4} style={{textAlign:'center',padding:30,color:'var(--text-muted)'}}>
                    No archived team members.
                  </td>
                </tr>
              )}
              {paginatedArchived.map((m) => {
                const colors = ROLE_COLORS[m.role] || ROLE_COLORS.viewer;
                return (
                  <tr key={`archived-${m.id}`}>
                    <td>
                      <div style={{display:'flex',alignItems:'center',gap:10}}>
                        <div style={{width:30,height:30,borderRadius:'50%',background:'var(--bg-elevated)',color:'var(--text-muted)',display:'flex',alignItems:'center',justifyContent:'center',fontSize:11,fontWeight:700,flexShrink:0}}>{initials(m.name)}</div>
                        <div>
                          <div style={{fontWeight:600,fontSize:13.5}}>{m.name}</div>
                          <div style={{fontSize:12,color:'var(--text-muted)'}}>{m.email}</div>
                        </div>
                      </div>
                    </td>
                    <td><span style={{fontSize:12,fontWeight:600,padding:'3px 10px',borderRadius:20,background:colors.bg,color:colors.color}}>{ROLE_LABELS[m.role] || m.role}</span></td>
                    <td style={{fontSize:12.5,color:'var(--text-muted)'}}>{m.deleted_at || 'recently'}</td>
                    <td>
                      <div style={{display:'flex',justifyContent:'flex-end'}}>
                        <button
                          className="btn-secondary"
                          style={{fontSize:12,padding:'6px 11px'}}
                          disabled={restoreSubmittingId === m.id}
                          onClick={() => restoreMember(m)}
                        >
                          {restoreSubmittingId === m.id ? 'Restoring…' : 'Restore'}
                        </button>
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>

      {archivedTotalPages > 1 && (
        <div style={{display:'flex',alignItems:'center',justifyContent:'center',gap:6,marginTop:4,marginBottom:20}}>
          <button className="btn-ghost" style={{fontSize:12,padding:'5px 10px'}} disabled={safeArchivedPage===1} onClick={() => setArchivedPage((p) => p - 1)}>← Prev</button>
          {Array.from({length:archivedTotalPages},(_,i)=>i+1).map((p) => (
            <button key={p} onClick={() => setArchivedPage(p)} style={{fontSize:12,padding:'5px 10px',borderRadius:6,border:'1px solid var(--border)',background:p===safeArchivedPage?'var(--accent)':'transparent',color:p===safeArchivedPage?'#fff':'var(--text-secondary)',fontWeight:p===safeArchivedPage?700:400,cursor:'pointer'}}>
              {p}
            </button>
          ))}
          <button className="btn-ghost" style={{fontSize:12,padding:'5px 10px'}} disabled={safeArchivedPage===archivedTotalPages} onClick={() => setArchivedPage((p) => p + 1)}>Next →</button>
        </div>
      )}
      </>
      )}

      {viewTab === 'permissions' && (
      <div className="card">
        <div className="card-header">
          <div>
            <div className="card-title">Role Permissions Reference</div>
            <div className="card-sub">Default access levels by role — customise per user when adding</div>
          </div>
        </div>
        <div style={{overflowX:'auto',padding:'0 20px 20px'}}>
          <table style={{width:'100%',borderCollapse:'collapse',fontSize:13}}>
            <thead>
              <tr style={{borderBottom:'1px solid var(--border-subtle)'}}>
                <th style={{textAlign:'left',padding:'8px 12px',fontSize:11,fontWeight:600,letterSpacing:'.4px',textTransform:'uppercase',color:'var(--text-muted)'}}>Resource</th>
                <th style={{textAlign:'center',padding:'8px 12px',fontSize:11,fontWeight:600,letterSpacing:'.4px',textTransform:'uppercase',color:'var(--text-muted)'}}>Accountant</th>
                <th style={{textAlign:'center',padding:'8px 12px',fontSize:11,fontWeight:600,letterSpacing:'.4px',textTransform:'uppercase',color:'var(--text-muted)'}}>Lease Assistant</th>
                <th style={{textAlign:'center',padding:'8px 12px',fontSize:11,fontWeight:600,letterSpacing:'.4px',textTransform:'uppercase',color:'var(--text-muted)'}}>Maintenance Staff</th>
                <th style={{textAlign:'center',padding:'8px 12px',fontSize:11,fontWeight:600,letterSpacing:'.4px',textTransform:'uppercase',color:'var(--text-muted)'}}>Viewer</th>
              </tr>
            </thead>
            <tbody>
              {RESOURCES.map((resource) => (
                <tr key={resource.key} style={{borderBottom:'1px solid var(--border-subtle)'}}>
                  <td style={{padding:'8px 12px'}}>
                    <div style={{fontSize:13,fontWeight:500}}>{resource.icon} {resource.label}</div>
                    <div style={{fontSize:11,color:'var(--text-muted)',marginTop:1}}>
                      {resource.actions.map((action) => (
                        <span key={action} title={ACTION_CFG[action].tip} style={{fontSize:10,fontWeight:600,color:ACTION_CFG[action].color,marginRight:4}}>{ACTION_CFG[action].full}</span>
                      ))}
                    </div>
                  </td>
                  {['accountant','lease_manager','maintenance_staff','viewer'].map((role) => {
                    const defaults = normalizedRoleDefaults?.[role]?.[resource.key] || {};
                    const badges = resource.actions
                      .filter((action) => defaults[action])
                      .map((action) => (
                        <span key={action} title={ACTION_CFG[action].tip} style={{fontSize:10,fontWeight:700,color:ACTION_CFG[action].color,background:`${ACTION_CFG[action].color}18`,padding:'1px 5px',borderRadius:4,margin:1}}>{ACTION_CFG[action].label}</span>
                      ));

                    return (
                      <td key={role} style={{textAlign:'center',padding:'7px 10px'}}>
                        {badges.length ? badges : <span style={{color:'var(--text-muted)',fontSize:12}}>—</span>}
                      </td>
                    );
                  })}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
      )}

      <div className={`modal-overlay ${showModal ? 'open' : ''}`} onClick={(e) => e.target === e.currentTarget && setShowModal(false)}>
        <div className="modal" style={{width:'min(640px, calc(100vw - 24px))',height:'min(720px, 82dvh)',maxHeight:'min(92vh, calc(100dvh - 20px))',display:'flex',flexDirection:'column'}}>
          <div className="modal-header" style={{flexShrink:0}}>
            <div className="modal-title">Add Staff Member</div>
            <button className="modal-close" onClick={() => setShowModal(false)}>✕</button>
          </div>
          <form onSubmit={submit} style={{display:'flex',flexDirection:'column',minHeight:0,flex:1}}>
            <div className="modal-body" style={{flex:1,overflowY:'auto'}}>
              <div style={{fontSize:10.5,fontWeight:700,letterSpacing:'.6px',textTransform:'uppercase',color:'var(--text-muted)',marginBottom:12}}>Personal Information</div>
              <div className="form-row">
                <div className="form-group"><label className="form-label">Full Name *</label><input className="form-input" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="e.g. David Kamau" required /></div>
                <div className="form-group"><label className="form-label">Email *</label><input className="form-input" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} placeholder="e.g. david@rukyrentals.co.tz" required /></div>
              </div>
              <div className="form-row">
                <div className="form-group"><label className="form-label">Phone</label><input className="form-input" value={data.phone} onChange={(e) => setData('phone', e.target.value)} placeholder="+255 7xx xxx xxx" /></div>
                <div className="form-group"><label className="form-label">Role *</label>
                  <select className="form-input form-select" value={data.role} onChange={(e) => onRoleChange(e.target.value)} required>
                    <option value="">Select role…</option>
                    <option value="accountant">Accountant</option>
                    <option value="lease_manager">Lease Assistant</option>
                    <option value="maintenance_staff">Maintenance Staff</option>
                    <option value="viewer">Viewer (Read-Only)</option>
                  </select>
                </div>
              </div>
              <div style={{fontSize:12,color:'var(--text-muted)',marginBottom:14}}>
                Submitting this form sends a superuser approval request. The staff member will receive their welcome email and temporary password only after approval.
              </div>

              <div style={{borderTop:'1px solid var(--border-subtle)',paddingTop:18,marginTop:6}}>
                <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',marginBottom:12}}>
                  <div style={{fontSize:10.5,fontWeight:700,letterSpacing:'.6px',textTransform:'uppercase',color:'var(--text-muted)'}}>Module Permissions</div>
                  <div style={{fontSize:12,color:'var(--text-muted)'}}>Auto-filled from role — customise below</div>
                </div>
                <div style={{display:'flex',flexDirection:'column',gap:8}}>
                  {RESOURCES.map((resource) => {
                    const current = data.permissions?.[resource.key] || {};
                    const isManage = resource.actions.every((action) => !!current[action]);
                    return (
                      <div key={resource.key} style={{background:'var(--bg-elevated)',border:'1px solid var(--border-subtle)',borderRadius:10,padding:'12px 14px'}}>
                        <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',marginBottom:10}}>
                          <div>
                            <div style={{fontSize:13,fontWeight:600}}>{resource.icon} {resource.label}</div>
                            <div style={{fontSize:11,color:'var(--text-muted)',marginTop:2}}>{resource.desc}</div>
                          </div>
                          <button
                            type="button"
                            onClick={() => setData('permissions', toggleManagePermissions(data.permissions, resource.key))}
                            style={{fontSize:11,fontWeight:700,padding:'3px 10px',borderRadius:20,border:`1px solid ${isManage ? '#a78bfa' : 'var(--border)'}`,background:isManage ? 'rgba(167,139,250,.15)' : 'transparent',color:isManage ? '#a78bfa' : 'var(--text-muted)',cursor:'pointer',transition:'all .15s',whiteSpace:'nowrap'}}
                          >
                            ★ Manage
                          </button>
                        </div>
                        <div style={{display:'flex',gap:6,flexWrap:'wrap'}}>
                          {resource.actions.map((action) => {
                            const cfg = ACTION_CFG[action];
                            const on = !!current[action];
                            return (
                              <label key={action} style={{display:'flex',alignItems:'center',gap:5,cursor:'pointer',padding:'5px 10px',borderRadius:7,border:`1px solid ${on ? cfg.color : 'var(--border-subtle)'}`,background:on ? `${cfg.color}18` : 'var(--bg-surface)',transition:'all .12s',userSelect:'none'}}>
                                <input
                                  type="checkbox"
                                  checked={on}
                                  onChange={() => setData('permissions', togglePermission(data.permissions, resource.key, action))}
                                  style={{accentColor:cfg.color,width:13,height:13,cursor:'pointer'}}
                                />
                                <span style={{fontSize:12,fontWeight:600,color:on ? cfg.color : 'var(--text-muted)'}}>{cfg.full}</span>
                              </label>
                            );
                          })}
                        </div>
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
                Submit for Approval
              </button>
            </div>
          </form>
        </div>
      </div>

      <div className={`modal-overlay ${showPermDrawer ? 'open' : ''}`} onClick={(e) => e.target === e.currentTarget && setShowPermDrawer(false)}>
        <div className="modal" style={{width:620,maxHeight:'90vh',display:'flex',flexDirection:'column'}}>
          <div className="modal-header" style={{flexShrink:0}}>
            <div>
              <div className="modal-title">{selectedMember?.name || 'Edit Permissions'}</div>
              <div style={{fontSize:12,color:'var(--text-muted)',marginTop:3}}>{ROLE_LABELS[selectedMember?.role] || selectedMember?.role}</div>
            </div>
            <button className="modal-close" onClick={() => setShowPermDrawer(false)}>✕</button>
          </div>
          <div className="modal-body" style={{flex:1,overflowY:'auto'}}>
            <div style={{display:'flex',flexDirection:'column',gap:8,paddingTop:4}}>
              {RESOURCES.map((resource) => {
                const current = permDraft?.[resource.key] || {};
                const isManage = resource.actions.every((action) => !!current[action]);
                return (
                  <div key={resource.key} style={{background:'var(--bg-elevated)',border:'1px solid var(--border-subtle)',borderRadius:10,padding:'12px 14px'}}>
                    <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',marginBottom:10}}>
                      <div>
                        <div style={{fontSize:13,fontWeight:600}}>{resource.icon} {resource.label}</div>
                        <div style={{fontSize:11,color:'var(--text-muted)',marginTop:2}}>{resource.desc}</div>
                      </div>
                      <button
                        type="button"
                        onClick={() => setPermDraft((prev) => toggleManagePermissions(prev, resource.key))}
                        style={{fontSize:11,fontWeight:700,padding:'3px 10px',borderRadius:20,border:`1px solid ${isManage ? '#a78bfa' : 'var(--border)'}`,background:isManage ? 'rgba(167,139,250,.15)' : 'transparent',color:isManage ? '#a78bfa' : 'var(--text-muted)',cursor:'pointer',transition:'all .15s',whiteSpace:'nowrap'}}
                      >
                        ★ Manage
                      </button>
                    </div>
                    <div style={{display:'flex',gap:6,flexWrap:'wrap'}}>
                      {resource.actions.map((action) => {
                        const cfg = ACTION_CFG[action];
                        const on = !!current[action];
                        return (
                          <label key={action} style={{display:'flex',alignItems:'center',gap:5,cursor:'pointer',padding:'5px 10px',borderRadius:7,border:`1px solid ${on ? cfg.color : 'var(--border-subtle)'}`,background:on ? `${cfg.color}18` : 'var(--bg-surface)',transition:'all .12s',userSelect:'none'}}>
                            <input
                              type="checkbox"
                              checked={on}
                              onChange={() => setPermDraft((prev) => togglePermission(prev, resource.key, action))}
                              style={{accentColor:cfg.color,width:13,height:13,cursor:'pointer'}}
                            />
                            <span style={{fontSize:12,fontWeight:600,color:on ? cfg.color : 'var(--text-muted)'}}>{cfg.full}</span>
                          </label>
                        );
                      })}
                    </div>
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

      <div className={`modal-overlay ${showDeleteDialog ? 'open' : ''}`} onClick={(e) => e.target === e.currentTarget && closeDeleteDialog()}>
        <div className="modal" style={{width:560,maxHeight:'90vh',display:'flex',flexDirection:'column'}}>
          <div className="modal-header" style={{flexShrink:0}}>
            <div>
              <div className="modal-title" style={{color:'var(--red)'}}>Confirm Team Member Deletion</div>
              <div style={{fontSize:12,color:'var(--text-muted)',marginTop:3}}>This action will archive the account and remove team access.</div>
            </div>
            <button className="modal-close" onClick={closeDeleteDialog}>✕</button>
          </div>
          <div className="modal-body" style={{flex:1,overflowY:'auto'}}>
            <div style={{background:'var(--red-dim)',border:'1px solid color-mix(in srgb, var(--red) 40%, transparent)',borderRadius:10,padding:'12px 14px',marginBottom:14}}>
              <div style={{fontSize:13,fontWeight:700,color:'var(--red)',marginBottom:4}}>Warning</div>
              <div style={{fontSize:12.5,color:'var(--text-secondary)'}}>
                The team member will be soft deleted. Their account will no longer appear in active team lists and they will lose access.
              </div>
            </div>

            <div style={{fontSize:13,color:'var(--text-secondary)',marginBottom:8}}>
              To confirm, type the full name of the user:
            </div>
            <div style={{fontSize:13,fontWeight:700,marginBottom:12}}>{deleteTarget?.name || '—'}</div>

            <div className="form-group" style={{marginBottom:0}}>
              <label className="form-label">Type user name to confirm</label>
              <input
                className="form-input"
                value={deleteConfirmName}
                onChange={(e) => setDeleteConfirmName(e.target.value)}
                placeholder="Enter full name exactly"
              />
            </div>
          </div>
          <div className="modal-footer" style={{flexShrink:0}}>
            <button className="btn-ghost" onClick={closeDeleteDialog} disabled={deleteSubmitting}>Cancel</button>
            <button
              className="btn-danger"
              onClick={confirmDeleteMember}
              disabled={deleteSubmitting || !deleteTarget || deleteConfirmName.trim() !== deleteTarget?.name}
              style={{opacity:(deleteSubmitting || !deleteTarget || deleteConfirmName.trim() !== deleteTarget?.name) ? .6 : 1,cursor:(deleteSubmitting || !deleteTarget || deleteConfirmName.trim() !== deleteTarget?.name) ? 'not-allowed' : 'pointer'}}
            >
              {deleteSubmitting ? 'Deleting…' : 'Delete Team Member'}
            </button>
          </div>
        </div>
      </div>
    </AppLayout>
  );
}
