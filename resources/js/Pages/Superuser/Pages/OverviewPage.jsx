import { useMemo, useState } from 'react';
import { router } from '@inertiajs/react';

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

const DOT_COLOR_MAP = {
  settings: 'red',
  user:     'amber',
  lease:    'purple',
  payment:  'green',
  finance:  'green',
  default:  'accent',
};

function dotColor(log) {
  return DOT_COLOR_MAP[log.category] ?? DOT_COLOR_MAP.default;
}

function formatLogTime(ts) {
  if (!ts) return '';
  const d = new Date(ts);
  return d.toLocaleString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
}

export default function OverviewPage({ properties = [], managers = [], auditLogs = [] }) {
  const [selectedPropertyId, setSelectedPropertyId] = useState('all');

  const selectedProperty = useMemo(
    () => properties.find((property) => String(property.id) === String(selectedPropertyId)) || null,
    [properties, selectedPropertyId],
  );

  const scopedProperties = selectedProperty ? [selectedProperty] : properties;

  const scopedStats = useMemo(() => {
    const total = scopedProperties.length;
    const units = scopedProperties.reduce((sum, p) => sum + Number(p.unit_count || 0), 0);
    const occupied = scopedProperties.reduce((sum, p) => sum + Number(p.occupied_units || 0), 0);
    const revenue = scopedProperties.reduce((sum, p) => sum + Number(p.monthly_revenue || p.monthly_rent || p.revenue || 0), 0);
    return { total, units, occupied, revenue };
  }, [scopedProperties]);

  const scopedManagers = useMemo(() => {
    if (!selectedProperty) return managers;
    return managers.filter((manager) => {
      if (manager.property_id != null) {
        return String(manager.property_id) === String(selectedProperty.id);
      }
      const managerPropertyName = (manager.property?.name || manager.property_name || '').toString();
      return managerPropertyName.includes(selectedProperty.name);
    });
  }, [managers, selectedProperty]);

  return (
    <>
      <div className="page-header">
        <div>
          <div className="page-title">System Overview</div>
          <div className="page-sub">{selectedProperty ? `${selectedProperty.name} - Real-time snapshot` : 'All properties - Real-time snapshot'}</div>
        </div>
        <div className="ph-actions">
          <select
            className="form-input form-select"
            style={{ minWidth: 220 }}
            value={selectedPropertyId}
            onChange={(e) => setSelectedPropertyId(e.target.value)}
            aria-label="Filter overview by property"
          >
            <option value="all">All Properties</option>
            {properties.map((property) => (
              <option key={property.id} value={property.id}>{property.name}</option>
            ))}
          </select>
          {selectedPropertyId !== 'all' && (
            <button
              className="btn btn-primary"
              onClick={() => router.post(`/superuser/property/${selectedPropertyId}/enter`)}
            >
              View Property Panel
            </button>
          )}
        </div>
      </div>

      <div className="stats-grid">
        <div className="stat-card">
          <div className="stat-top"><div className="stat-icon blue"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg></div><span className="stat-delta up">portfolio</span></div>
          <div className="stat-value">{scopedStats.total || 0}</div><div className="stat-label">Total Properties</div>
        </div>
        <div className="stat-card">
          <div className="stat-top"><div className="stat-icon blue"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg></div><span className="stat-delta up">units</span></div>
          <div className="stat-value">{scopedStats.units || 0}</div><div className="stat-label">Total Units</div>
        </div>
        <div className="stat-card">
          <div className="stat-top"><div className="stat-icon green"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div><span className="stat-delta up">occupied</span></div>
          <div className="stat-value">{scopedStats.occupied || 0}</div><div className="stat-label">Occupied Units</div>
        </div>
        <div className="stat-card">
          <div className="stat-top"><div className="stat-icon green"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></div><span className="stat-delta up">revenue</span></div>
          <div className="stat-value">TZS {(scopedStats.revenue || 0).toLocaleString()}</div><div className="stat-label">Monthly Revenue</div>
        </div>
      </div>

      <div className="grid-2" style={{ gridTemplateColumns: '1fr 1fr' }}>
        <div className="card">
          <div className="card-header"><div><div className="card-title">Properties at a Glance</div><div className="card-sub">{selectedProperty ? 'Selected property snapshot' : `All ${properties.length} properties`}</div></div></div>
          <table className="data-table">
            <thead><tr><th>Property</th><th>Manager</th><th>Occupancy</th><th>Revenue</th><th>Status</th></tr></thead>
            <tbody>
              {scopedProperties.slice(0, 6).map((property) => {
                const units = Number(property.unit_count || 0);
                const occupied = Number(property.occupied_units || 0);
                const occupancy = units > 0 ? Math.round((occupied / units) * 100) : 0;
                return (
                  <tr key={property.id}>
                    <td><div style={{ fontWeight: 600 }}>{property.name}</div><div style={{ fontSize: 11.5, color: 'var(--text-muted)' }}>{property.city || '-'}</div></td>
                    <td>{property.manager?.name || 'Unassigned'}</td>
                    <td>{occupancy}%</td>
                    <td style={{ fontWeight: 600 }}>TZS {Math.round(Number(property.monthly_revenue || property.monthly_rent || 0) / 1000)}k</td>
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
              {scopedManagers.map((m) => (
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
            {auditLogs.length === 0 && (
              <div style={{ padding: '16px 0', color: 'var(--text-muted)', fontSize: 13 }}>No activity recorded yet.</div>
            )}
            {auditLogs.slice(0, 8).map((log) => (
              <div key={log.id} className="act-item">
                <div className={`act-dot ${dotColor(log)}`}></div>
                <div>
                  <div className="act-text">{log.user_name} — {log.action}</div>
                  <div className="act-meta">{log.resource}{log.property_name ? ` · ${log.property_name}` : ''} · {formatLogTime(log.created_at)}</div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </>
  );
}
