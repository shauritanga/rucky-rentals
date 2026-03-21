const statusClass = (status) => {
  if (status === 'active') return 'active';
  if (status === 'trial') return 'trial';
  return 'inactive';
};

const SYSTEM_HEALTH = [
  { name: 'Application Server', sub: 'API & web interface', status: 'Operational', uptime: 99.98, color: 'green' },
  { name: 'Database', sub: 'PostgreSQL primary', status: 'Operational', uptime: 99.99, color: 'green' },
  { name: 'File Storage', sub: 'Documents & images', status: 'Operational', uptime: 100, color: 'green' },
  { name: 'Email Service', sub: 'Transactional mail', status: 'Operational', uptime: 99.85, color: 'green' },
  { name: 'SMS Gateway', sub: 'Tenant notifications', status: 'Degraded', uptime: 98.2, color: 'amber' },
  { name: 'Backup Service', sub: 'Daily automated backup', status: 'Operational', uptime: 100, color: 'green' },
];

const RECENT_ACTIVITY = [
  { dot: 'purple', text: 'James Mwangi - Lease approved', meta: 'Lease L-B202-NEW · 14:32' },
  { dot: 'green', text: 'Grace Wanjiru - Payment recorded', meta: 'Invoice INV-1042 · 13:58' },
  { dot: 'amber', text: 'Super Admin - User created', meta: 'User Patrick Kimani · 12:44' },
  { dot: 'red', text: 'Super Admin - Settings changed', meta: 'Lease policy defaults · 10:30' },
  { dot: 'blue', text: 'James Mwangi - Login', meta: 'Session start · 09:32' },
];

export default function OverviewPage({ properties = [], managers = [], stats = {}, onGoProperties, onOpenPropertyModal }) {
  return (
    <>
      <div className="page-header">
        <div>
          <div className="page-title">System Overview</div>
          <div className="page-sub">All properties - Real-time snapshot</div>
        </div>
        <div className="ph-actions">
          <button type="button" className="btn-ghost" onClick={onGoProperties}>View Properties</button>
          <button type="button" className="btn-primary" onClick={onOpenPropertyModal}>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add Property
          </button>
        </div>
      </div>

      <div className="stats-grid">
        <div className="stat-card">
          <div className="stat-top"><div className="stat-icon blue"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg></div><span className="stat-delta up">portfolio</span></div>
          <div className="stat-value">{stats.total || 0}</div><div className="stat-label">Total Properties</div>
        </div>
        <div className="stat-card">
          <div className="stat-top"><div className="stat-icon blue"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg></div><span className="stat-delta up">units</span></div>
          <div className="stat-value">{stats.units || 0}</div><div className="stat-label">Total Units</div>
        </div>
        <div className="stat-card">
          <div className="stat-top"><div className="stat-icon green"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div><span className="stat-delta up">occupied</span></div>
          <div className="stat-value">{stats.occupied || 0}</div><div className="stat-label">Occupied Units</div>
        </div>
        <div className="stat-card">
          <div className="stat-top"><div className="stat-icon green"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></div><span className="stat-delta up">revenue</span></div>
          <div className="stat-value">TZS {(stats.revenue || 0).toLocaleString()}</div><div className="stat-label">Monthly Revenue</div>
        </div>
      </div>

      <div className="grid-2" style={{ gridTemplateColumns: '1fr 1fr' }}>
        <div className="card">
          <div className="card-header"><div><div className="card-title">Properties at a Glance</div><div className="card-sub">All 4 active properties</div></div></div>
          <table className="data-table">
            <thead><tr><th>Property</th><th>Manager</th><th>Occupancy</th><th>Revenue</th><th>Status</th></tr></thead>
            <tbody>
              {properties.slice(0, 6).map((property) => {
                const units = Number(property.unit_count || 0);
                const occupied = Number(property.occupied_units || 0);
                const occupancy = units > 0 ? Math.round((occupied / units) * 100) : 0;
                return (
                  <tr key={property.id}>
                    <td><div style={{ fontWeight: 600 }}>{property.name}</div><div style={{ fontSize: 11.5, color: 'var(--text-muted)' }}>{property.city || '-'}</div></td>
                    <td>{property.manager?.name || 'Unassigned'}</td>
                    <td>{occupancy}%</td>
                    <td style={{ fontWeight: 600 }}>TZS {Math.round(Number(property.monthly_rent || 0) / 1000)}k</td>
                    <td><span className={`badge ${statusClass(property.status)}`}>{property.status}</span></td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>

        <div className="card">
          <div className="card-header"><div><div className="card-title">Manager Activity</div><div className="card-sub">Last 24 hours</div></div></div>
          <table className="data-table">
            <thead><tr><th>Manager</th><th>Property</th><th>Last Active</th><th>Status</th></tr></thead>
            <tbody>
              {managers.map((m) => (
                <tr key={m.id}>
                  <td><div style={{ fontWeight: 600 }}>{m.name}</div><div style={{ fontSize: 11.5, color: 'var(--text-muted)' }}>{m.email}</div></td>
                  <td>{m.property?.name || m.property_name || 'Unassigned'}</td>
                  <td>{m.lastActive || (m.updated_at ? new Date(m.updated_at).toLocaleString() : 'Recently')}</td>
                  <td><span className={`badge ${m.online === false ? 'inactive' : 'active'}`}>{m.online === false ? 'Offline' : 'Online'}</span></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      <div className="grid-2" style={{ gridTemplateColumns: '1fr 1fr', marginTop: 16 }}>
        <div className="card">
          <div className="card-header"><div className="card-title">System Health</div></div>
          <div style={{ padding: '6px 16px' }}>
            {SYSTEM_HEALTH.map((h) => {
              const bg = h.color === 'green' ? 'var(--green-dim)' : h.color === 'amber' ? 'var(--amber-dim)' : 'var(--red-dim)';
              const color = h.color === 'green' ? 'var(--green)' : h.color === 'amber' ? 'var(--amber)' : 'var(--red)';
              return (
                <div key={h.name} className="health-row">
                  <div className="health-icon" style={{ background: bg, color }}>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                  </div>
                  <div className="health-name"><div style={{ fontSize: 13.5, fontWeight: 500 }}>{h.name}</div><div className="health-sub">{h.sub}</div></div>
                  <div className="uptime-bar"><div className="uptime-fill" style={{ width: `${h.uptime}%`, background: color }}></div></div>
                  <div className="health-status" style={{ color }}>{h.status}</div>
                </div>
              );
            })}
          </div>
        </div>

        <div className="card">
          <div className="card-header"><div><div className="card-title">Recent Activity</div></div></div>
          <div style={{ padding: '4px 16px' }}>
            {RECENT_ACTIVITY.map((a, idx) => (
              <div key={idx} className="act-item">
                <div className={`act-dot ${a.dot}`}></div>
                <div>
                  <div className="act-text">{a.text}</div>
                  <div className="act-meta">{a.meta}</div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </>
  );
}
