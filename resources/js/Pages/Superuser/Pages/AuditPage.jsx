import { useMemo, useState } from 'react';

export default function AuditPage({ auditLogs = [] }) {
  const [search, setSearch] = useState('');
  const [filter, setFilter] = useState('');

  const rows = useMemo(() => auditLogs.map((row) => ({
    ts: row.created_at ? new Date(row.created_at).toLocaleString() : '-',
    user: row.user_name || 'System',
    action: row.action || '-',
    resource: row.resource || '-',
    property: row.property_name || '-',
    ip: row.ip_address || '-',
    result: row.result || 'success',
    cat: row.category || 'settings',
  })), [auditLogs]);

  const filteredRows = useMemo(() => {
    const q = search.trim().toLowerCase();
    return rows.filter((row) => {
      const filterOk = !filter || row.cat === filter;
      const searchOk = !q || [row.user, row.action, row.resource, row.property, row.ip].some((v) => String(v).toLowerCase().includes(q));
      return filterOk && searchOk;
    });
  }, [rows, search, filter]);

  return (
    <>
      <div className="page-header">
        <div><div className="page-title">Audit Trail</div><div className="page-sub">Complete log of all system actions</div></div>
        <div className="ph-actions">
          <div className="search-box" style={{ width: 220 }}>
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <input type="text" value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search logs..." />
          </div>
          <select className="form-input form-select" value={filter} onChange={(e) => setFilter(e.target.value)} style={{ width: 140, padding: '6px 28px 6px 10px', fontSize: '12.5px' }}>
            <option value="">All Actions</option>
            <option value="login">Login / Logout</option>
            <option value="lease">Lease Actions</option>
            <option value="payment">Payments</option>
            <option value="user">User Management</option>
            <option value="settings">Settings</option>
          </select>
          <button type="button" className="btn-ghost">Export CSV</button>
        </div>
      </div>
      <div className="card">
        <table className="data-table">
          <thead><tr><th>Timestamp</th><th>User</th><th>Action</th><th>Resource</th><th>Property</th><th>IP Address</th><th>Result</th></tr></thead>
          <tbody>
            {filteredRows.length === 0 && (
              <tr>
                <td colSpan={7} style={{ textAlign: 'center', color: 'var(--text-muted)' }}>
                  No audit logs yet.
                </td>
              </tr>
            )}
            {filteredRows.map((row, idx) => (
              <tr key={idx}>
                <td>{row.ts}</td>
                <td>{row.user}</td>
                <td>{row.action}</td>
                <td>{row.resource}</td>
                <td>{row.property}</td>
                <td>{row.ip}</td>
                <td><span className={`badge ${row.result === 'blocked' ? 'rejected' : 'active'}`}>{row.result}</span></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </>
  );
}
