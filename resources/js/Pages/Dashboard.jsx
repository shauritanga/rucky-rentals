import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';

const fmt = (n) => Number(n).toLocaleString();
const fmtK = (n) => n >= 1000 ? '$' + (n / 1000).toFixed(0) + 'k' : '$' + fmt(n);

const STATUS_CLASS = { occupied: 'occupied', vacant: 'vacant', overdue: 'overdue', maintenance: 'maintenance' };
const STATUS_LABEL = { occupied: 'Occupied', vacant: 'Vacant', overdue: 'Overdue', maintenance: 'Maintenance' };

export default function Dashboard({ stats, recentPayments, maintenanceItems, units, occupancyByFloor }) {
  return (
    <AppLayout title="Dashboard" subtitle="March 2026">
      <Head title="Dashboard" />

      {/* Stats */}
      <div className="stats-grid">
        <div className="stat-card">
          <div className="stat-top">
            <div className="stat-icon blue"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg></div>
            <span className="stat-delta up">↑ 2</span>
          </div>
          <div className="stat-value">{stats.totalUnits}</div>
          <div className="stat-label">Total Units</div>
        </div>
        <div className="stat-card">
          <div className="stat-top">
            <div className="stat-icon green"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
            <span className="stat-delta up">↑ 3%</span>
          </div>
          <div className="stat-value">{stats.occupiedUnits}</div>
          <div className="stat-label">Occupied Units</div>
        </div>
        <div className="stat-card">
          <div className="stat-top">
            <div className="stat-icon amber"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></div>
            <span className="stat-delta up">↑ $800</span>
          </div>
          <div className="stat-value">{fmtK(stats.monthlyRevenue)}</div>
          <div className="stat-label">Revenue / Month</div>
        </div>
        <div className="stat-card">
          <div className="stat-top">
            <div className="stat-icon red"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
            <span className="stat-delta down">{stats.overdueUnits} pending</span>
          </div>
          <div className="stat-value">{fmtK(stats.overdueBalance)}</div>
          <div className="stat-label">Overdue Rent</div>
        </div>
      </div>

      <div className="grid-2">
        {/* Units table */}
        <div className="card">
          <div className="card-header">
            <div>
              <div className="card-title">All Units</div>
              <div className="card-sub">{stats.totalUnits} units · {stats.occupiedUnits} occupied · {stats.vacantUnits} vacant</div>
            </div>
            <a href="/units" style={{fontSize:'12.5px',color:'var(--accent)',fontWeight:500,textDecoration:'none'}}>View all</a>
          </div>
          <table className="data-table">
            <thead><tr><th>Unit</th><th>Tenant</th><th>Status</th><th>Rent</th></tr></thead>
            <tbody>
              {units.map(u => {
                const lease = u.leases?.[0];
                const tenant = lease?.tenant;
                return (
                  <tr key={u.id}>
                    <td>
                      <div style={{fontWeight:600}}>{u.unit_number}</div>
                      <div style={{fontSize:'12px',color:'var(--text-muted)'}}>Floor {u.floor}</div>
                    </td>
                    <td>
                      {tenant
                        ? <div className="tenant-cell">
                            <div className="t-avatar" style={{background:tenant.color,color:tenant.text_color}}>{tenant.initials}</div>
                            {tenant.name}
                          </div>
                        : <span style={{color:'var(--text-muted)'}}>—</span>
                      }
                    </td>
                    <td><span className={`badge ${STATUS_CLASS[u.status]}`}>{STATUS_LABEL[u.status]}</span></td>
                    <td style={{fontWeight:600}}>${fmt(u.rent)}</td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>

        {/* Upcoming / Recent */}
        <div style={{display:'flex',flexDirection:'column',gap:16}}>
          {/* Occupancy by floor */}
          <div className="card">
            <div className="card-header"><div className="card-title">Occupancy by Floor</div></div>
            <div style={{padding:'16px 20px'}}>
              {occupancyByFloor.map(f => {
                const pct = f.total > 0 ? Math.round((f.occupied / f.total) * 100) : 0;
                const color = pct === 100 ? 'var(--green)' : pct >= 75 ? 'var(--accent)' : 'var(--amber)';
                return (
                  <div className="bar-row" key={f.floor}>
                    <span className="bar-label">Floor {f.floor}</span>
                    <div className="bar-track"><div className="bar-fill" style={{width:`${pct}%`,background:color}}></div></div>
                    <span className="bar-val">{pct}%</span>
                  </div>
                );
              })}
            </div>
          </div>

          {/* Recent payments */}
          <div className="card">
            <div className="card-header">
              <div className="card-title">Recent Payments</div>
              <a href="/payments" style={{fontSize:'12.5px',color:'var(--accent)',fontWeight:500,textDecoration:'none'}}>All</a>
            </div>
            <div style={{padding:'4px 0'}}>
              {recentPayments.map(p => (
                <div key={p.id} style={{display:'flex',alignItems:'center',gap:10,padding:'10px 20px',borderBottom:'1px solid var(--border-subtle)'}}>
                  <div style={{width:28,height:28,borderRadius:'50%',background:p.status==='paid'?'var(--green-dim)':'var(--red-dim)',color:p.status==='paid'?'var(--green)':'var(--red)',display:'flex',alignItems:'center',justifyContent:'center',flexShrink:0}}>
                    {p.status === 'paid'
                      ? <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                      : <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    }
                  </div>
                  <div style={{flex:1}}>
                    <div style={{fontSize:13,fontWeight:500}}>{p.tenant?.name}</div>
                    <div style={{fontSize:'11.5px',color:'var(--text-muted)'}}>Unit {p.unit?.unit_number}</div>
                  </div>
                  <div style={{textAlign:'right'}}>
                    <div style={{fontSize:13,fontWeight:600,color:p.status==='paid'?'var(--green)':'var(--red)'}}>{p.status==='paid'?'+':''}${fmt(p.amount)}</div>
                    <div style={{fontSize:'11.5px',color:'var(--text-muted)'}}>{p.paid_date || 'Overdue'}</div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>

      {/* Maintenance */}
      <div className="card">
        <div className="card-header">
          <div className="card-title">Open Maintenance</div>
          <a href="/maintenance" style={{fontSize:'12.5px',color:'var(--accent)',fontWeight:500,textDecoration:'none'}}>All tickets</a>
        </div>
        <div style={{padding:'4px 0'}}>
          {maintenanceItems.map(t => (
            <div key={t.id} style={{display:'flex',alignItems:'center',gap:12,padding:'10px 20px',borderBottom:'1px solid var(--border-subtle)'}}>
              <div style={{width:34,height:34,borderRadius:9,background:'var(--bg-elevated)',display:'flex',alignItems:'center',justifyContent:'center',fontSize:16,flexShrink:0}}>
                {t.category === 'Plumbing' ? '🔧' : t.category === 'Electrical' ? '💡' : t.category === 'HVAC' ? '❄️' : t.category === 'Security' ? '🔒' : '🪛'}
              </div>
              <div style={{flex:1}}>
                <div style={{fontSize:13,fontWeight:600}}>{t.title}</div>
                <div style={{fontSize:'11.5px',color:'var(--text-muted)'}}>Unit {t.unit_ref} · {t.category} · {t.reported_date}</div>
              </div>
              <span className={`priority ${t.priority}`}>{t.priority === 'med' ? 'Med' : t.priority.charAt(0).toUpperCase() + t.priority.slice(1)}</span>
            </div>
          ))}
        </div>
      </div>
    </AppLayout>
  );
}
