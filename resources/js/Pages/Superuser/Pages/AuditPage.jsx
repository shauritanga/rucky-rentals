import { useMemo, useState } from 'react';

const PAGE_SIZE = 10;

const MODULE_COLORS = {
  auth:        { bg:'var(--accent-dim)',    color:'var(--accent)' },
  lease:       { bg:'var(--green-dim)',     color:'var(--green)' },
  payment:     { bg:'var(--green-dim)',     color:'var(--green)' },
  invoice:     { bg:'var(--amber-dim)',     color:'var(--amber)' },
  maintenance: { bg:'var(--red-dim)',       color:'var(--red)' },
  document:    { bg:'var(--accent-dim)',    color:'var(--accent)' },
  team:        { bg:'rgba(167,139,250,.12)', color:'#a78bfa' },
  accounting:  { bg:'var(--amber-dim)',     color:'var(--amber)' },
  user:        { bg:'var(--accent-dim)',    color:'var(--accent)' },
  settings:    { bg:'var(--bg-elevated)',   color:'var(--text-muted)' },
};

function initials(name) {
  return String(name || '')
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0])
    .join('')
    .toUpperCase() || '?';
}

export default function AuditPage({ auditLogs = [] }) {
  const [search, setSearch]       = useState('');
  const [catFilter, setCatFilter] = useState('');
  const [resFilter, setResFilter] = useState('');
  const [page, setPage]           = useState(0);

  const rows = useMemo(() => auditLogs.map((r) => ({
    id:       r.id,
    ts:       r.created_at ? new Date(r.created_at).toLocaleString() : '—',
    user:     r.user_name   || 'System',
    action:   r.action      || '—',
    resource: r.resource    || '—',
    property: r.property_name || '—',
    ip:       r.ip_address  || '—',
    result:   r.result      || 'success',
    cat:      r.category    || 'settings',
  })), [auditLogs]);

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    return rows.filter((r) => {
      if (catFilter && r.cat !== catFilter) return false;
      if (resFilter && r.result !== resFilter) return false;
      if (q && !`${r.user} ${r.action} ${r.resource} ${r.property}`.toLowerCase().includes(q)) return false;
      return true;
    });
  }, [rows, search, catFilter, resFilter]);

  const totalPages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
  const safePage   = Math.min(page, totalPages - 1);
  const pageRows   = filtered.slice(safePage * PAGE_SIZE, safePage * PAGE_SIZE + PAGE_SIZE);

  const resetPage = () => setPage(0);

  return (
    <>
      <div className="page-header">
        <div>
          <div className="page-title">Audit Trail</div>
          <div className="page-sub">Complete log of all system actions across all properties</div>
        </div>
        <div className="ph-actions">
          <div className="search-box" style={{ width: 220 }}>
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <input type="text" value={search} onChange={(e) => { setSearch(e.target.value); resetPage(); }} placeholder="Search logs..." />
          </div>

          <select className="form-input form-select" value={catFilter} onChange={(e) => { setCatFilter(e.target.value); resetPage(); }} style={{ width: 160, padding: '6px 28px 6px 10px', fontSize: '12.5px' }}>
            <option value="">All Categories</option>
            <option value="auth">Login / Auth</option>
            <option value="lease">Leases</option>
            <option value="payment">Payments</option>
            <option value="invoice">Invoices</option>
            <option value="maintenance">Maintenance</option>
            <option value="document">Documents</option>
            <option value="team">Team</option>
            <option value="accounting">Accounting</option>
            <option value="user">User Management</option>
            <option value="settings">Settings</option>
          </select>

          <select className="form-input form-select" value={resFilter} onChange={(e) => { setResFilter(e.target.value); resetPage(); }} style={{ width: 130, padding: '6px 28px 6px 10px', fontSize: '12.5px' }}>
            <option value="">All Results</option>
            <option value="success">Success</option>
            <option value="failed">Failed</option>
            <option value="blocked">Blocked</option>
          </select>
        </div>
      </div>

      <div className="card">
        <table className="data-table">
          <thead>
            <tr>
              <th>Timestamp</th>
              <th>User</th>
              <th>Action</th>
              <th>Resource</th>
              <th>Property</th>
              <th>Category</th>
              <th>IP Address</th>
              <th>Result</th>
            </tr>
          </thead>
          <tbody>
            {pageRows.length === 0 && (
              <tr>
                <td colSpan={8} style={{ textAlign: 'center', padding: 48, color: 'var(--text-muted)' }}>
                  No audit logs found.
                </td>
              </tr>
            )}
            {pageRows.map((r, idx) => {
              const cc  = MODULE_COLORS[r.cat] || MODULE_COLORS.settings;
              const ok  = r.result === 'success';
              const bad = r.result === 'failed' || r.result === 'blocked';
              return (
                <tr key={r.id ?? idx}>
                  <td style={{ fontSize: 12, color: 'var(--text-muted)', whiteSpace: 'nowrap', fontVariantNumeric: 'tabular-nums' }}>{r.ts}</td>
                  <td>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                      <div style={{ width: 26, height: 26, borderRadius: '50%', background: 'var(--accent-dim)', color: 'var(--accent)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 10, fontWeight: 700, flexShrink: 0 }}>
                        {initials(r.user)}
                      </div>
                      <span style={{ fontSize: 13, fontWeight: 500 }}>{r.user}</span>
                    </div>
                  </td>
                  <td style={{ fontSize: 13 }}>{r.action}</td>
                  <td style={{ fontSize: 12.5, color: 'var(--text-secondary)', maxWidth: 180, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }} title={r.resource}>{r.resource}</td>
                  <td style={{ fontSize: 12.5, color: 'var(--text-muted)' }}>{r.property}</td>
                  <td>
                    <span style={{ fontSize: 11, fontWeight: 600, padding: '2px 8px', borderRadius: 20, background: cc.bg, color: cc.color, whiteSpace: 'nowrap' }}>
                      {String(r.cat).charAt(0).toUpperCase() + String(r.cat).slice(1)}
                    </span>
                  </td>
                  <td style={{ fontFamily: 'monospace', fontSize: 12, color: 'var(--text-muted)' }}>{r.ip}</td>
                  <td>
                    <span style={{ fontSize: 12, fontWeight: 600, color: ok ? 'var(--green)' : bad ? 'var(--red)' : 'var(--amber)' }}>
                      {ok ? '● Success' : bad ? `● ${String(r.result).charAt(0).toUpperCase() + String(r.result).slice(1)}` : '● Warning'}
                    </span>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>

        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '12px 20px', borderTop: '1px solid var(--border-subtle)', fontSize: 13, color: 'var(--text-muted)' }}>
          <span>
            {filtered.length
              ? `Showing ${safePage * PAGE_SIZE + 1}–${Math.min((safePage + 1) * PAGE_SIZE, filtered.length)} of ${filtered.length} events`
              : 'Showing 0 events'}
          </span>
          <div style={{ display: 'flex', gap: 6 }}>
            <button className="btn-ghost" style={{ padding: '5px 12px', fontSize: 12.5 }} disabled={safePage === 0} onClick={() => setPage((p) => Math.max(0, p - 1))}>← Prev</button>
            <span style={{ padding: '5px 12px', fontSize: 12.5, color: 'var(--text-secondary)' }}>Page {safePage + 1} of {totalPages}</span>
            <button className="btn-ghost" style={{ padding: '5px 12px', fontSize: 12.5 }} disabled={safePage >= totalPages - 1} onClick={() => setPage((p) => Math.min(totalPages - 1, p + 1))}>Next →</button>
          </div>
        </div>
      </div>
    </>
  );
}
