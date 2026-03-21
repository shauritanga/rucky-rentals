import { useMemo, useState } from 'react';

export default function AuditPage({ properties = [], managers = [] }) {
  const [search, setSearch] = useState('');
  const [filter, setFilter] = useState('');

  const rows = [
    { ts: '2026-03-19 14:32', user: 'James Mwangi', action: 'Lease approved', resource: 'Lease L-B202-NEW', property: 'Rucky Heights', ip: '196.201.4.12', result: 'success', cat: 'lease' },
    { ts: '2026-03-19 13:58', user: 'Grace Wanjiru', action: 'Payment recorded', resource: 'Invoice INV-1042', property: 'Rucky Gardens', ip: '196.201.4.18', result: 'success', cat: 'payment' },
    { ts: '2026-03-19 12:44', user: 'Super Admin', action: 'User created', resource: 'User Patrick Kimani', property: 'All', ip: '41.80.96.4', result: 'success', cat: 'user' },
    { ts: '2026-03-19 12:00', user: 'Diana Ochieng', action: 'Journal entry posted', resource: 'JE-015', property: 'All', ip: '196.201.4.22', result: 'success', cat: 'settings' },
    { ts: '2026-03-19 10:15', user: 'Kevin Otieno', action: 'Maintenance ticket created', resource: 'TK-016', property: 'Rucky Towers', ip: '197.232.80.44', result: 'success', cat: 'lease' },
    { ts: '2026-03-18 18:04', user: 'Unknown', action: 'Failed login (3 attempts)', resource: 'james@ruckyrentals.co.tz', property: '-', ip: '41.57.12.200', result: 'blocked', cat: 'login' },
  ];

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
