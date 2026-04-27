import { useEffect, useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import { formatDisplayDate } from '@/utils/dateFormat';

export default function ManagersPage({ managers = [], properties = [], archivedManagers = [], onOpenManagerModal }) {
    const [search, setSearch] = useState('');
    const [role, setRole] = useState('');
    const [showArchived, setShowArchived] = useState(false);
    const [openMenu, setOpenMenu] = useState(null);
    const [menuPos, setMenuPos] = useState({ top: 0, right: 0 });
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [confirmName, setConfirmName] = useState('');
    const [confirmError, setConfirmError] = useState('');
    const [deleting, setDeleting] = useState(false);

    // Close ⋮ menu when clicking elsewhere
    useEffect(() => {
        const handler = () => setOpenMenu(null);
        document.addEventListener('click', handler);
        return () => document.removeEventListener('click', handler);
    }, []);

    const resolvedManagers = managers.map((manager) => {
        const assigned = properties.filter((p) => Number(p.manager_user_id) === Number(manager.id));
        return { ...manager, assignedProperties: assigned };
    });

    const filtered = useMemo(() => {
        const q = search.trim().toLowerCase();
        return resolvedManagers.filter((m) => {
            const roleOk = !role || String(m.role || '').toLowerCase() === role;
            const qOk = !q || [m.name, m.email].filter(Boolean).some((v) => String(v).toLowerCase().includes(q));
            return roleOk && qOk;
        });
    }, [resolvedManagers, search, role]);

    const openDeleteModal = (manager) => {
        setDeleteTarget(manager);
        setConfirmName('');
        setConfirmError('');
    };

    const closeDeleteModal = () => {
        setDeleteTarget(null);
        setConfirmName('');
        setConfirmError('');
    };

    const handleDelete = () => {
        setDeleting(true);
        router.delete(`/superuser/managers/${deleteTarget.id}`, {
            data: { confirm_name: confirmName },
            onSuccess: () => { setDeleteTarget(null); setDeleting(false); },
            onError: (errors) => {
                setConfirmError(errors.confirm_name ?? 'Something went wrong.');
                setDeleting(false);
            },
        });
    };

    return (
        <>
            {/* Header */}
            <div className="page-header">
                <div>
                    <div className="page-title">Administrators</div>
                    <div className="page-sub">All system users across properties</div>
                </div>
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

            {/* Stats */}
            <div className="grid-3" style={{ marginBottom: 16 }}>
                <div style={{ background: 'var(--bg-surface)', border: '1px solid var(--border)', borderRadius: 13, padding: '16px 18px' }}>
                    <div style={{ fontSize: 12, color: 'var(--text-muted)', marginBottom: 5 }}>Total Users</div>
                    <div style={{ fontSize: 28, fontWeight: 800 }}>{resolvedManagers.length}</div>
                </div>
                <div style={{ background: 'var(--bg-surface)', border: '1px solid var(--border)', borderRadius: 13, padding: '16px 18px' }}>
                    <div style={{ fontSize: 12, color: 'var(--text-muted)', marginBottom: 5 }}>Assigned to Property</div>
                    <div style={{ fontSize: 28, fontWeight: 800, color: 'var(--green)' }}>{resolvedManagers.filter((m) => m.assignedProperties.length > 0).length}</div>
                </div>
                <div style={{ background: 'var(--bg-surface)', border: '1px solid var(--border)', borderRadius: 13, padding: '16px 18px' }}>
                    <div style={{ fontSize: 12, color: 'var(--text-muted)', marginBottom: 5 }}>Unassigned</div>
                    <div style={{ fontSize: 28, fontWeight: 800, color: 'var(--amber)' }}>{resolvedManagers.filter((m) => m.assignedProperties.length === 0).length}</div>
                </div>
            </div>

            {/* Active / Archived toggle */}
            <div className="toolbar" style={{ marginBottom: 14 }}>
                <div className="filters">
                    <button className={`filter-pill ${!showArchived ? 'active' : ''}`} onClick={() => setShowArchived(false)}>
                        Active <span className="pill-count">{resolvedManagers.length}</span>
                    </button>
                    <button className={`filter-pill ${showArchived ? 'active' : ''}`} onClick={() => setShowArchived(true)}>
                        Archived <span className="pill-count">{archivedManagers.length}</span>
                    </button>
                </div>
            </div>

            {/* Active table */}
            {!showArchived && (
                <div className="card">
                    <table className="data-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>Assigned Property</th>
                                <th>Last Active</th>
                                <th>2FA</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {filtered.length === 0 && (
                                <tr><td colSpan={7} style={{ textAlign: 'center', padding: 28, color: 'var(--text-muted)' }}>No users found</td></tr>
                            )}
                            {filtered.map((manager) => (
                                <tr key={manager.id}>
                                    <td>
                                        <div style={{ fontWeight: 600 }}>{manager.name}</div>
                                        <div style={{ fontSize: 11.5, color: 'var(--text-muted)' }}>{manager.email}</div>
                                    </td>
                                    <td><span className={`role-tag ${String(manager.role || '').toLowerCase()}`}>{manager.role || 'manager'}</span></td>
                                    <td>
                                        {manager.role === 'superuser'
                                            ? <span style={{ color: 'var(--text-muted)', fontSize: 12 }}>All Properties</span>
                                            : manager.assignedProperties.length > 0
                                                ? manager.assignedProperties.map((p) => p.name).join(', ')
                                                : manager.property_id
                                                    ? (properties.find((p) => Number(p.id) === Number(manager.property_id))?.name ?? 'Unassigned')
                                                    : 'Unassigned'}
                                    </td>
                                    <td>{manager.lastActive || 'Never'}</td>
                                    <td><span className={`badge ${manager.twoFA ? 'active' : 'inactive'}`}>{manager.twoFA ? 'Enabled' : 'Disabled'}</span></td>
                                    <td><span className={`badge ${manager.online ? 'active' : 'inactive'}`}>{manager.online ? 'Online' : 'Offline'}</span></td>
                                    <td>
                                        {manager.role !== 'superuser' ? (
                                            <button
                                                type="button"
                                                className="btn-ghost"
                                                style={{ padding: '4px 12px', fontSize: 18, lineHeight: 1 }}
                                                onClick={(e) => {
                                                    e.stopPropagation();
                                                    if (openMenu === manager.id) { setOpenMenu(null); return; }
                                                    const rect = e.currentTarget.getBoundingClientRect();
                                                    setMenuPos({ top: rect.bottom + window.scrollY, right: window.innerWidth - rect.right });
                                                    setOpenMenu(manager.id);
                                                }}
                                            >
                                                ⋮
                                            </button>
                                        ) : (
                                            <span style={{ fontSize: 12, color: 'var(--text-muted)' }}>—</span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {/* Archived table */}
            {showArchived && (
                <div className="card">
                    <table className="data-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>Removed On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {archivedManagers.length === 0 && (
                                <tr><td colSpan={4} style={{ textAlign: 'center', padding: 28, color: 'var(--text-muted)' }}>No archived users</td></tr>
                            )}
                            {archivedManagers.map((m) => (
                                <tr key={m.id}>
                                    <td>
                                        <div style={{ fontWeight: 600 }}>{m.name}</div>
                                        <div style={{ fontSize: 11.5, color: 'var(--text-muted)' }}>{m.email}</div>
                                    </td>
                                    <td><span className={`role-tag ${String(m.role || '').toLowerCase()}`}>{m.role}</span></td>
                                    <td style={{ color: 'var(--text-muted)', fontSize: 13 }}>
                                        {formatDisplayDate(m.deleted_at)}
                                    </td>
                                    <td>
                                        <button
                                            type="button"
                                            className="btn-ghost"
                                            style={{ fontSize: 12, padding: '5px 12px' }}
                                            onClick={() => router.post(`/superuser/managers/${m.id}/restore`)}
                                        >
                                            Restore
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {/* Actions dropdown — fixed position so it never gets clipped by table overflow */}
            {openMenu !== null && (() => {
                const manager = filtered.find((m) => m.id === openMenu);
                if (!manager) return null;
                return (
                    <div
                        style={{ position: 'fixed', top: menuPos.top, right: menuPos.right, zIndex: 200, background: 'var(--bg-surface)', border: '1px solid var(--border)', borderRadius: 8, minWidth: 140, boxShadow: '0 4px 16px rgba(0,0,0,.14)' }}
                        onClick={(e) => e.stopPropagation()}
                    >
                        <button
                            type="button"
                            style={{ display: 'block', width: '100%', padding: '10px 16px', textAlign: 'left', fontSize: 13, color: 'var(--danger)', background: 'none', border: 'none', cursor: 'pointer' }}
                            onClick={(e) => { e.stopPropagation(); openDeleteModal(manager); setOpenMenu(null); }}
                        >
                            Remove
                        </button>
                    </div>
                );
            })()}

            {/* Delete confirmation dialog — uses 'open' class like all other modals in this app */}
            <div className={`modal-overlay ${deleteTarget ? 'open' : ''}`} onClick={(e) => e.target === e.currentTarget && closeDeleteModal()}>
                <div className="modal" onClick={(e) => e.stopPropagation()} style={{ maxWidth: 420 }}>
                    <div className="modal-header">
                        <h3>Remove User</h3>
                        <button type="button" className="modal-close" onClick={closeDeleteModal}>×</button>
                    </div>
                    <div className="modal-body">
                        <p style={{ marginBottom: 14, lineHeight: 1.6 }}>
                            You are about to remove <strong>{deleteTarget?.name}</strong>
                            {deleteTarget?.assignedProperties?.length > 0 && (
                                <> and unassign them from <strong>{deleteTarget.assignedProperties.map((p) => p.name).join(', ')}</strong></>
                            )}. This action can be undone from the Archived tab.
                        </p>
                        <label style={{ display: 'block', fontSize: 12.5, color: 'var(--text-muted)', marginBottom: 6 }}>
                            Type <strong>{deleteTarget?.name}</strong> to confirm:
                        </label>
                        <input
                            className="form-input"
                            placeholder={deleteTarget?.name ?? ''}
                            value={confirmName}
                            onChange={(e) => { setConfirmName(e.target.value); setConfirmError(''); }}
                        />
                        {confirmError && (
                            <p style={{ color: 'var(--danger)', fontSize: 12, marginTop: 6 }}>{confirmError}</p>
                        )}
                    </div>
                    <div className="modal-footer">
                        <button type="button" className="btn-ghost" onClick={closeDeleteModal}>Cancel</button>
                        <button
                            type="button"
                            className="btn-danger"
                            disabled={deleting || confirmName !== deleteTarget?.name}
                            onClick={handleDelete}
                        >
                            {deleting ? 'Removing…' : 'Remove'}
                        </button>
                    </div>
                </div>
            </div>
        </>
    );
}
