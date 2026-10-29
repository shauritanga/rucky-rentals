import { useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import useExchangeRate from '@/hooks/useExchangeRate';

const statusClass = (status) => {
  if (status === 'active') return 'active';
  if (status === 'trial') return 'trial';
  return 'inactive';
};


const Icon = ({ children }) => (
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">{children}</svg>
);
const ICONS = {
  cash:   <Icon><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/></Icon>,
  home:   <Icon><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></Icon>,
  alert:  <Icon><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></Icon>,
  shield: <Icon><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></Icon>,
  check:  <Icon><polyline points="20 6 9 17 4 12"/></Icon>,
  clock:  <Icon><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></Icon>,
  doc:    <Icon><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></Icon>,
  tool:   <Icon><path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94z"/></Icon>,
  save:   <Icon><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></Icon>,
};

const pct = (part, whole) => (whole > 0 ? Math.min(100, Math.round((part / whole) * 100)) : 0);

// Rows for "Needs Your Attention": each has a tone, a headline figure and an optional page to open.
function attentionRows(o, lastBackupAt, tzs) {
  if (!o) return [];
  const rows = [
    { key: 'approvals', icon: ICONS.check, title: 'Waiting for your approval', sub: 'Leases, invoices, units, team and clearances', value: o.approvals, tone: o.approvals ? 'amber' : 'ok', go: o.approvals ? 'approvals' : null },
    { key: 'due', icon: ICONS.clock, title: 'Rent due in 30 days', sub: `${o.dueSoonCount} invoice${o.dueSoonCount === 1 ? '' : 's'} coming due`, value: o.dueSoonCount ? tzs(o.dueSoonAmount) : 0, tone: o.dueSoonCount ? 'amber' : 'ok' },
    { key: 'ending', icon: ICONS.doc, title: 'Leases ending soon', sub: 'Within the next 90 days', value: o.leasesEnding, tone: o.leasesEnding ? 'amber' : 'ok' },
    { key: 'maint', icon: ICONS.tool, title: 'Open repair requests', sub: 'Reported and in progress', value: o.openMaintenance, tone: o.openMaintenance ? 'amber' : 'ok' },
  ];
  const hours = lastBackupAt ? (Date.now() - new Date(lastBackupAt).getTime()) / 36e5 : null;
  if (hours === null) rows.push({ key: 'backup', icon: ICONS.save, title: 'Data backup', sub: 'No backup has run yet', value: 'Not run', tone: 'red' });
  else if (hours > 36) rows.push({ key: 'backup', icon: ICONS.save, title: 'Data backup', sub: `Last backup ${new Date(lastBackupAt).toLocaleString()}`, value: 'Overdue', tone: 'amber' });
  return rows;
}

function Kpi({ icon, tone, label, value, delta, foot, bar }) {
  return (
    <div className="stat-card ov-kpi">
      <div className="stat-top">
        <div className={`stat-icon ${tone}`}>{icon}</div>
        {delta}
      </div>
      <div className="stat-value">{value}</div>
      <div className="stat-label">{label}</div>
      {bar !== undefined && <div className="ov-bar"><div className={`ov-bar-fill ${tone}`} style={{ width: `${bar}%` }} /></div>}
      <div className="ov-foot">{foot}</div>
    </div>
  );
}

export default function OverviewPage({ properties = [], managers = [], lastBackupAt = null, ownerSummaries = {}, onNavigate = () => {} }) {
  const [selectedPropertyId, setSelectedPropertyId] = useState('all');
  // formatCompactTzs formats a pre-converted TZS value — no rate multiplication.
  // Revenue is converted server-side using getLiveRate() so all views are consistent.
  const { formatCompactTzs } = useExchangeRate();

  const selectedProperty = useMemo(
    () => properties.find((property) => String(property.id) === String(selectedPropertyId)) || null,
    [properties, selectedPropertyId],
  );

  const scopedProperties = selectedProperty ? [selectedProperty] : properties;

  const scopedStats = useMemo(() => {
    const total = scopedProperties.length;
    const units = scopedProperties.reduce((sum, p) => sum + Number(p.unit_count || 0), 0);
    const occupied = scopedProperties.reduce((sum, p) => sum + Number(p.occupied_units || 0), 0);
    // monthly_revenue_tzs is already in TZS — computed by the backend, never by the browser
    const revenue = scopedProperties.reduce((sum, p) => sum + Number(p.monthly_revenue_tzs || 0), 0);
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

  const o = ownerSummaries[selectedPropertyId] || ownerSummaries.all || null;
  const tzs = (n) => formatCompactTzs(Number(n || 0));
  const occPct = o ? pct(o.occupied, o.units) : 0;
  const change = o && o.collectedLast > 0 ? Math.round(((o.collectedThis - o.collectedLast) / o.collectedLast) * 100) : null;
  const attention = attentionRows(o, lastBackupAt, tzs);

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
        </div>
      </div>

      {o && (
        <>
            <div className="stats-grid">
              <Kpi
                icon={ICONS.cash} tone="green" label="Collected this month" value={tzs(o.collectedThis)}
                delta={change !== null && change !== 0 ? <span className={`stat-delta ${change > 0 ? 'up' : 'down'}`}>{change > 0 ? '↑' : '↓'} {Math.abs(change)}% vs last month</span> : null}
                foot={o.collectedCount ? `${o.collectedCount} payment${o.collectedCount > 1 ? 's' : ''} received this month · deposits excluded` : 'No payments received yet this month'}
              />
              <Kpi
                icon={ICONS.home} tone="blue" label="Occupancy" value={`${occPct}%`} bar={occPct}
                foot={`${o.occupied} of ${o.units} units occupied${o.vacant ? ` · ${o.vacant} vacant` : ''}`}
              />
              <Kpi
                icon={ICONS.alert} tone={o.overdueCount ? 'red' : 'green'} label="Overdue rent" value={tzs(o.overdueAmount)}
                foot={o.overdueCount ? `${o.overdueCount} invoice${o.overdueCount > 1 ? 's' : ''} past due` : 'Everyone is paid up'}
              />
              <Kpi
                icon={ICONS.shield} tone="amber" label="Deposits held" value={tzs(o.depositsHeld)}
                foot="Refundable to tenants at move-out"
              />
            </div>
        </>
      )}

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
                  <tr
                    key={property.id}
                    onClick={() => router.post(`/superuser/property/${property.id}/enter`)}
                    style={{ cursor: 'pointer' }}
                    title="Open property panel"
                  >
                    <td><div style={{ fontWeight: 600 }}>{property.name}</div><div style={{ fontSize: 11.5, color: 'var(--text-muted)' }}>{property.city || '-'}</div></td>
                    <td>{property.manager?.name || 'Unassigned'}</td>
                    <td>{occupancy}%</td>
                    <td style={{ fontWeight: 600 }}>{formatCompactTzs(property.monthly_revenue_tzs)}</td>
                    <td><span className={`badge ${statusClass(property.status)}`}>{property.status}</span></td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>

        <div className="card">
          <div className="card-header"><div><div className="card-title">Administrator Activity</div><div className="card-sub">Last 24 hours</div></div></div>
          <table className="data-table">
            <thead><tr><th>User</th><th>Property</th><th>Last Active</th><th>Status</th></tr></thead>
            <tbody>
              {scopedManagers.map((m) => (
                <tr key={m.id}>
                  <td><div style={{ fontWeight: 600 }}>{m.name}</div><div style={{ fontSize: 11.5, color: 'var(--text-muted)' }}>{m.email}</div></td>
                  <td>{m.role === 'superuser' ? <span style={{ color: 'var(--text-muted)', fontSize: 12 }}>All Properties</span> : (m.property?.name || m.property_name || 'Unassigned')}</td>
                  <td>{m.lastActive || 'Never'}</td>
                  <td><span className={`badge ${m.online ? 'active' : 'inactive'}`}>{m.online ? 'Online' : 'Offline'}</span></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      <div style={{ marginTop: 16 }} />
      {o && (
      <div className="ov-two">
        <div className="card">
          <div className="card-header"><div><div className="card-title">Needs Your Attention</div><div className="card-sub">{attention.every((r) => r.tone === 'ok') ? 'Nothing needs action right now' : 'Items waiting on you'}</div></div></div>
          <div className="ov-list">
            {attention.map((r) => (
              <button key={r.key} type="button" className={`ov-todo ${r.go ? 'clickable' : ''}`} onClick={() => r.go && onNavigate(r.go)} disabled={!r.go}>
                <span className={`ov-ico ${r.tone}`}>{r.icon}</span>
                <span className="ov-todo-text"><span className="ov-todo-title">{r.title}</span><span className="ov-todo-sub">{r.sub}</span></span>
                <span className={`ov-pill ${r.tone}`}>{r.tone === 'ok' ? 'All clear' : r.value}</span>
              </button>
            ))}
          </div>
        </div>

        <div className="card">
          <div className="card-header"><div><div className="card-title">Who owes the most</div><div className="card-sub">Overdue rent by tenant</div></div></div>
          <div className="ov-list">
            {o.debtors.length === 0 && <div className="ov-empty">{ICONS.check}<span>No overdue rent. Everyone is paid up.</span></div>}
            {o.debtors.map((d) => (
              <div key={d.tenant} className="ov-debtor">
                <span className="ov-avatar">{String(d.tenant || '?').trim().charAt(0).toUpperCase()}</span>
                <span className="ov-todo-text"><span className="ov-todo-title">{d.tenant}</span><span className="ov-todo-sub">{d.invoices} invoice{d.invoices > 1 ? 's' : ''} · {d.days} day{d.days === 1 ? '' : 's'} overdue</span></span>
                <span className="ov-amount">{tzs(d.amount)}</span>
              </div>
            ))}
          </div>
        </div>
      </div>
      )}
    </>
  );
}
