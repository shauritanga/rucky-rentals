import { useState } from 'react';

const statusClass = (status) => {
  if (status === 'active') return 'active';
  if (status === 'trial') return 'trial';
  return 'inactive';
};

export default function PropertiesPage({
  properties = [],
  managers = [],
  search,
  setSearch,
  status,
  setStatus,
  onOpenPropertyModal,
  onAssignManager,
}) {
  const [viewMode, setViewMode] = useState('grid');

  const filtered = properties.filter((property) => {
    const q = search.trim().toLowerCase();
    const statusOk = status === 'all' || property.status === status;
    const searchOk = !q || [property.name, property.code, property.city, property.country]
      .filter(Boolean)
      .some((value) => String(value).toLowerCase().includes(q));
    return statusOk && searchOk;
  });

  return (
    <>
      <div className="page-header">
        <div><div className="page-title">Properties</div><div className="page-sub">All buildings under Mwamba Properties management</div></div>
        <div className="ph-actions">
          <div className="search-box" style={{ width: 240 }}>
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <input type="text" value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search properties..." />
          </div>
          <div className="view-toggle" role="tablist" aria-label="Property view mode">
            <button type="button" className={`vt-btn ${viewMode === 'grid' ? 'active' : ''}`} onClick={() => setViewMode('grid')} title="Grid view" aria-label="Grid view" aria-pressed={viewMode === 'grid'}>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg>
            </button>
            <button type="button" className={`vt-btn ${viewMode === 'list' ? 'active' : ''}`} onClick={() => setViewMode('list')} title="List view" aria-label="List view" aria-pressed={viewMode === 'list'}>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
            </button>
          </div>
          <button type="button" className="btn-primary" onClick={onOpenPropertyModal}>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add Property
          </button>
        </div>
      </div>

      <div className="filters" style={{ marginBottom: 14 }}>
        {[['all', 'All'], ['active', 'Active'], ['trial', 'Trial'], ['inactive', 'Inactive']].map(([key, label]) => (
          <button type="button" key={key} className={`filter-pill ${status === key ? 'active' : ''}`} onClick={() => setStatus(key)}>{label}</button>
        ))}
      </div>

      {viewMode === 'grid' && (
        <div className="prop-grid" id="propGrid">
          {filtered.map((property) => {
            const occupancy = Number(property.unit_count || 0) > 0
              ? Math.round((Number(property.occupied_units || 0) / Number(property.unit_count || 1)) * 100)
              : 0;
            const cls = property.status === 'active' ? 'active-prop' : property.status === 'trial' ? 'trial-prop' : 'inactive-prop';
            return (
              <div key={property.id} className={`prop-card ${cls}`}>
                <div className="prop-name">{property.name}</div>
                <div className="prop-addr">{property.address || [property.city, property.country].filter(Boolean).join(', ')}</div>
                <div className="prop-kv-grid">
                  <div className="prop-kv"><div className="prop-kv-label">Units</div><div className="prop-kv-value">{property.unit_count || 0}</div></div>
                  <div className="prop-kv"><div className="prop-kv-label">Occupancy</div><div className="prop-kv-value">{occupancy}%</div></div>
                  <div className="prop-kv"><div className="prop-kv-label">Code</div><div className="prop-kv-value">{property.code || '-'}</div></div>
                  <div className="prop-kv"><div className="prop-kv-label">Revenue</div><div className="prop-kv-value">TZS {Math.round(Number(property.monthly_rent || 0) / 1000)}k</div></div>
                </div>
                <div className="prop-footer">
                  <div className="prop-manager">
                    <div className="pm-avatar">{(property.manager?.name || 'U').split(' ').map((v) => v[0]).join('').slice(0, 2).toUpperCase()}</div>
                    {property.manager?.name || 'Unassigned'}
                  </div>
                  <span className={`badge ${statusClass(property.status)}`}>{property.status}</span>
                </div>
                <div style={{ marginTop: 10 }}>
                  <select
                    className="form-input form-select"
                    value={property.manager_user_id || ''}
                    onChange={(e) => onAssignManager(property.id, e.target.value)}
                  >
                    <option value="">Assign manager...</option>
                    {managers.map((manager) => (
                      <option key={manager.id} value={manager.id}>{manager.name}</option>
                    ))}
                  </select>
                </div>
              </div>
            );
          })}
        </div>
      )}

      {viewMode === 'list' && (
        <div className="card">
          <table className="data-table">
            <thead>
              <tr>
                <th>Property</th>
                <th>Code</th>
                <th>Location</th>
                <th>Units</th>
                <th>Occupancy</th>
                <th>Revenue</th>
                <th>Manager</th>
                <th>Status</th>
                <th>Assign Manager</th>
              </tr>
            </thead>
            <tbody>
              {filtered.map((property) => {
                const occupancy = Number(property.unit_count || 0) > 0
                  ? Math.round((Number(property.occupied_units || 0) / Number(property.unit_count || 1)) * 100)
                  : 0;

                return (
                  <tr key={property.id}>
                    <td style={{ fontWeight: 700 }}>{property.name}</td>
                    <td>{property.code || '-'}</td>
                    <td>{property.address || [property.city, property.country].filter(Boolean).join(', ')}</td>
                    <td>{property.unit_count || 0}</td>
                    <td>{occupancy}%</td>
                    <td>TZS {Math.round(Number(property.monthly_rent || 0) / 1000)}k</td>
                    <td>{property.manager?.name || 'Unassigned'}</td>
                    <td><span className={`badge ${statusClass(property.status)}`}>{property.status}</span></td>
                    <td>
                      <select
                        className="form-input form-select"
                        style={{ width: 180, padding: '6px 28px 6px 10px', fontSize: '12.5px' }}
                        value={property.manager_user_id || ''}
                        onChange={(e) => onAssignManager(property.id, e.target.value)}
                      >
                        <option value="">Assign manager...</option>
                        {managers.map((manager) => (
                          <option key={manager.id} value={manager.id}>{manager.name}</option>
                        ))}
                      </select>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
    </>
  );
}
