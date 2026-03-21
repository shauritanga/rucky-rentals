import { useMemo, useState } from 'react';

export default function ManagersPage({ managers = [], properties = [], onOpenManagerModal }) {
  const [search, setSearch] = useState('');
  const [role, setRole] = useState('');

  const resolvedManagers = managers.map((manager) => {
    const assigned = properties.filter((p) => Number(p.manager_user_id) === Number(manager.id));
    return {
      ...manager,
      assignedProperties: assigned,
    };
  });

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    return resolvedManagers.filter((m) => {
      const roleOk = !role || String(m.role || '').toLowerCase() === role;
      const qOk = !q || [m.name, m.email, m.property_name].filter(Boolean).some((v) => String(v).toLowerCase().includes(q));
      return roleOk && qOk;
    });
  }, [resolvedManagers, search, role]);

  return (
    <>
      <div className="page-header">
        <div><div className="page-title">Managers & Users</div><div className="page-sub">All system users across properties</div></div>
        <div className="ph-actions">
          <div className="search-box" style={{ width: 220 }}>
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <input type="text" value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search users..." />
          </div>
          <select className="form-input form-select" value={role} onChange={(e) => setRole(e.target.value)} style={{ width: 140, padding: '6px 28px 6px 10px', fontSize: '12.5px' }}>
            <option value="">All Roles</option>
            <option value="manager">Manager</option>
            <option value="accountant">Accountant</option>
            <option value="viewer">Viewer</option>
          </select>
          <button type="button" className="btn-primary" onClick={onOpenManagerModal}>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add User
          </button>
        </div>
      </div>

      <div className="grid-3" style={{ marginBottom: 16 }}>
        <div style={{ background: 'var(--bg-surface)', border: '1px solid var(--border)', borderRadius: 13, padding: '16px 18px' }}>
          <div style={{ fontSize: 12, color: 'var(--text-muted)', marginBottom: 5 }}>Total Users</div>
          <div style={{ fontSize: 28, fontWeight: 800 }}>{resolvedManagers.length}</div>
        </div>
        <div style={{ background: 'var(--bg-surface)', border: '1px solid var(--border)', borderRadius: 13, padding: '16px 18px' }}>
          <div style={{ fontSize: 12, color: 'var(--text-muted)', marginBottom: 5 }}>Assigned Managers</div>
          <div style={{ fontSize: 28, fontWeight: 800, color: 'var(--green)' }}>{resolvedManagers.filter((m) => m.assignedProperties.length > 0).length}</div>
        </div>
        <div style={{ background: 'var(--bg-surface)', border: '1px solid var(--border)', borderRadius: 13, padding: '16px 18px' }}>
          <div style={{ fontSize: 12, color: 'var(--text-muted)', marginBottom: 5 }}>Unassigned</div>
          <div style={{ fontSize: 28, fontWeight: 800, color: 'var(--amber)' }}>{resolvedManagers.filter((m) => m.assignedProperties.length === 0).length}</div>
        </div>
      </div>

      <div className="card">
        <table className="data-table">
          <thead>
            <tr><th>User</th><th>Role</th><th>Assigned Property</th><th>Last Active</th><th>2FA</th><th>Status</th><th>Actions</th></tr>
          </thead>
          <tbody>
            {filtered.map((manager) => (
              <tr key={manager.id}>
                <td><div style={{ fontWeight: 600 }}>{manager.name}</div><div style={{ fontSize: 11.5, color: 'var(--text-muted)' }}>{manager.email}</div></td>
                <td><span className={`role-tag ${String(manager.role || '').toLowerCase()}`}>{manager.role || 'manager'}</span></td>
                <td>{manager.assignedProperties.map((p) => p.name).join(', ') || 'Unassigned'}</td>
                <td>{manager.lastActive || 'Recently'}</td>
                <td><span className={`badge ${manager.twoFA ? 'active' : 'inactive'}`}>{manager.twoFA ? 'Enabled' : 'Disabled'}</span></td>
                <td><span className={`badge ${manager.status === 'suspended' ? 'rejected' : 'active'}`}>{manager.status || 'active'}</span></td>
                <td><button type="button" className="btn-ghost" style={{ padding: '5px 10px', fontSize: 12 }}>Manage</button></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </>
  );
}
