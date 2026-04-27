import { useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { formatDisplayDateTime } from '@/utils/dateFormat';

const PAGE_SIZE = 10;

const MODULE_COLORS = {
  auth: { bg:'var(--accent-dim)', color:'var(--accent)' },
  lease: { bg:'var(--green-dim)', color:'var(--green)' },
  payment: { bg:'var(--green-dim)', color:'var(--green)' },
  invoice: { bg:'var(--amber-dim)', color:'var(--amber)' },
  maintenance: { bg:'var(--red-dim)', color:'var(--red)' },
  document: { bg:'var(--accent-dim)', color:'var(--accent)' },
  team: { bg:'rgba(167,139,250,.12)', color:'#a78bfa' },
  accounting: { bg:'var(--amber-dim)', color:'var(--amber)' },
  settings: { bg:'var(--bg-elevated)', color:'var(--text-muted)' },
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

function asCsv(rows) {
  const header = ['Timestamp', 'User', 'Action', 'Resource', 'Module', 'IP Address', 'Result'];
  const lines = [header, ...rows.map((r) => [r.ts, r.user, r.action, r.resource, r.module, r.ip, r.result])];
  return lines.map((row) => row.map((v) => `"${String(v ?? '').replaceAll('"', '""')}"`).join(',')).join('\n');
}

export default function AuditIndex({ auditLogs = [] }) {
  const [q, setQ] = useState('');
  const [userFilter, setUserFilter] = useState('');
  const [moduleFilter, setModuleFilter] = useState('');
  const [resultFilter, setResultFilter] = useState('');
  const [dateFilter, setDateFilter] = useState('');
  const [page, setPage] = useState(0);

  const users = useMemo(() => [...new Set(auditLogs.map((r) => r.user))].sort(), [auditLogs]);

  const filtered = useMemo(() => {
    const search = q.toLowerCase().trim();
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const weekAgo = new Date(today);
    weekAgo.setDate(weekAgo.getDate() - 7);
    const monthAgo = new Date(today);
    monthAgo.setDate(monthAgo.getDate() - 30);

    return auditLogs.filter((r) => {
      if (search) {
        const hay = `${r.user} ${r.action} ${r.resource}`.toLowerCase();
        if (!hay.includes(search)) return false;
      }
      if (userFilter && r.user !== userFilter) return false;
      if (moduleFilter && r.module !== moduleFilter) return false;
      if (resultFilter && r.result !== resultFilter) return false;

      if (dateFilter) {
        const d = new Date(r.ts);
        if (dateFilter === 'today' && d < today) return false;
        if (dateFilter === 'week' && d < weekAgo) return false;
        if (dateFilter === 'month' && d < monthAgo) return false;
      }
      return true;
    });
  }, [auditLogs, q, userFilter, moduleFilter, resultFilter, dateFilter]);

  const summary = useMemo(() => {
    const byModule = {};
    filtered.forEach((r) => { byModule[r.module] = (byModule[r.module] || 0) + 1; });
    const failed = filtered.filter((r) => r.result !== 'success').length;
    const activeUsers = new Set(filtered.map((r) => r.user)).size;
    const top = Object.entries(byModule).sort((a, b) => b[1] - a[1]).slice(0, 2);
    return {
      total: filtered.length,
      activeUsers,
      failed,
      top,
    };
  }, [filtered]);

  const totalPages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
  const safePage = Math.min(page, totalPages - 1);
  const pageRows = filtered.slice(safePage * PAGE_SIZE, safePage * PAGE_SIZE + PAGE_SIZE);

  const clearFilters = () => {
    setQ('');
    setUserFilter('');
    setModuleFilter('');
    setResultFilter('');
    setDateFilter('');
    setPage(0);
  };

  const exportCsv = () => {
    const csv = asCsv(filtered);
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `audit-trail-${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  };

  return (
    <AppLayout title="Audit Trail" subtitle="Property-scoped activity log">
      <Head title="Audit Trail" />

      <div style={{display:'flex',alignItems:'center',gap:12,marginBottom:18,flexWrap:'wrap'}}>
        <div className="search-box" style={{width:220}}>
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          <input type="text" placeholder="Search user, action..." value={q} onChange={(e) => { setQ(e.target.value); setPage(0); }} />
        </div>

        <select className="form-input form-select" style={{width:180,height:36,fontSize:13}} value={userFilter} onChange={(e) => { setUserFilter(e.target.value); setPage(0); }}>
          <option value="">All Users</option>
          {users.map((u) => <option key={u} value={u}>{u}</option>)}
        </select>

        <select className="form-input form-select" style={{width:160,height:36,fontSize:13}} value={moduleFilter} onChange={(e) => { setModuleFilter(e.target.value); setPage(0); }}>
          <option value="">All Categories</option>
          <option value="auth">Login / Auth</option>
          <option value="lease">Leases</option>
          <option value="payment">Payments</option>
          <option value="invoice">Invoices</option>
          <option value="maintenance">Maintenance</option>
          <option value="document">Documents</option>
          <option value="team">Team</option>
          <option value="accounting">Accounting</option>
        </select>

        <select className="form-input form-select" style={{width:130,height:36,fontSize:13}} value={resultFilter} onChange={(e) => { setResultFilter(e.target.value); setPage(0); }}>
          <option value="">All Results</option>
          <option value="success">Success</option>
          <option value="failed">Failed</option>
          <option value="blocked">Blocked</option>
        </select>

        <select className="form-input form-select" style={{width:140,height:36,fontSize:13}} value={dateFilter} onChange={(e) => { setDateFilter(e.target.value); setPage(0); }}>
          <option value="">All Time</option>
          <option value="today">Today</option>
          <option value="week">This Week</option>
          <option value="month">This Month</option>
        </select>

        <div style={{marginLeft:'auto',display:'flex',gap:8}}>
          <button className="btn-ghost" onClick={exportCsv}>
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Export CSV
          </button>
          <button className="btn-ghost" onClick={clearFilters}>Clear</button>
        </div>
      </div>

      <div style={{display:'grid',gridTemplateColumns:'repeat(5,1fr)',gap:12,marginBottom:18}}>
        <div className="card" style={{padding:'14px 16px'}}><div style={{fontSize:20,marginBottom:6}}>📋</div><div style={{fontSize:22,fontWeight:700,color:'var(--accent)',letterSpacing:'-.5px'}}>{summary.total}</div><div style={{fontSize:12,color:'var(--text-muted)',marginTop:2}}>Total Events</div></div>
        <div className="card" style={{padding:'14px 16px'}}><div style={{fontSize:20,marginBottom:6}}>👤</div><div style={{fontSize:22,fontWeight:700,color:'var(--green)',letterSpacing:'-.5px'}}>{summary.activeUsers}</div><div style={{fontSize:12,color:'var(--text-muted)',marginTop:2}}>Active Users</div></div>
        <div className="card" style={{padding:'14px 16px'}}><div style={{fontSize:20,marginBottom:6}}>⚠️</div><div style={{fontSize:22,fontWeight:700,color:summary.failed ? 'var(--red)' : 'var(--text-muted)',letterSpacing:'-.5px'}}>{summary.failed}</div><div style={{fontSize:12,color:'var(--text-muted)',marginTop:2}}>Failed / Blocked</div></div>
        {summary.top.map(([mod, count]) => (
          <div key={mod} className="card" style={{padding:'14px 16px'}}>
            <div style={{fontSize:20,marginBottom:6}}>📌</div>
            <div style={{fontSize:22,fontWeight:700,color:'var(--amber)',letterSpacing:'-.5px'}}>{count}</div>
            <div style={{fontSize:12,color:'var(--text-muted)',marginTop:2}}>{mod.charAt(0).toUpperCase() + mod.slice(1)}</div>
          </div>
        ))}
      </div>

      <div className="card" style={{overflow:'hidden'}}>
        <div style={{overflowX:'auto'}}>
          <table className="data-table">
            <thead><tr><th style={{whiteSpace:'nowrap'}}>Timestamp</th><th>User</th><th>Action</th><th>Resource</th><th>Module</th><th>IP Address</th><th>Result</th></tr></thead>
            <tbody>
              {!pageRows.length && <tr><td colSpan={7} style={{textAlign:'center',padding:48,color:'var(--text-muted)'}}>No events match your filters</td></tr>}
              {pageRows.map((r) => {
                const cc = MODULE_COLORS[r.module] || MODULE_COLORS.settings;
                const ok = r.result === 'success';
                const fail = r.result === 'failed' || r.result === 'blocked';
                return (
                  <tr key={r.id}>
                    <td style={{fontSize:12,color:'var(--text-muted)',whiteSpace:'nowrap',fontVariantNumeric:'tabular-nums'}}>{formatDisplayDateTime(r.ts)}</td>
                    <td>
                      <div style={{display:'flex',alignItems:'center',gap:8}}>
                        <div style={{width:26,height:26,borderRadius:'50%',background:'var(--accent-dim)',color:'var(--accent)',display:'flex',alignItems:'center',justifyContent:'center',fontSize:10,fontWeight:700,flexShrink:0}}>{initials(r.user)}</div>
                        <div><div style={{fontSize:13,fontWeight:500}}>{r.user}</div></div>
                      </div>
                    </td>
                    <td style={{fontSize:13}}>{r.action}</td>
                    <td style={{fontSize:12.5,color:'var(--text-secondary)',maxWidth:200,whiteSpace:'nowrap',overflow:'hidden',textOverflow:'ellipsis'}} title={r.resource}>{r.resource}</td>
                    <td><span style={{fontSize:11,fontWeight:600,padding:'2px 8px',borderRadius:20,background:cc.bg,color:cc.color,whiteSpace:'nowrap'}}>{String(r.module || '').charAt(0).toUpperCase() + String(r.module || '').slice(1).replace('_', ' ')}</span></td>
                    <td style={{fontFamily:'monospace',fontSize:12,color:'var(--text-muted)'}}>{r.ip}</td>
                    <td><span style={{fontSize:12,fontWeight:600,color:ok ? 'var(--green)' : fail ? 'var(--red)' : 'var(--amber)'}}>{ok ? '● Success' : fail ? `● ${String(r.result).charAt(0).toUpperCase() + String(r.result).slice(1)}` : '● Warning'}</span></td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
        <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',padding:'12px 20px',borderTop:'1px solid var(--border-subtle)',fontSize:13,color:'var(--text-muted)'}}>
          <span>
            {filtered.length ? `Showing ${safePage * PAGE_SIZE + 1}-${Math.min((safePage + 1) * PAGE_SIZE, filtered.length)} of ${filtered.length} events` : 'Showing 0 events'}
          </span>
          <div style={{display:'flex',gap:6}}>
            <button className="btn-ghost" style={{padding:'5px 12px',fontSize:12.5}} disabled={safePage === 0} onClick={() => setPage((p) => Math.max(0, p - 1))}>← Prev</button>
            <span style={{padding:'5px 12px',fontSize:12.5,color:'var(--text-secondary)'}}>Page {safePage + 1} of {totalPages}</span>
            <button className="btn-ghost" style={{padding:'5px 12px',fontSize:12.5}} disabled={safePage >= totalPages - 1} onClick={() => setPage((p) => Math.min(totalPages - 1, p + 1))}>Next →</button>
          </div>
        </div>
      </div>
    </AppLayout>
  );
}
