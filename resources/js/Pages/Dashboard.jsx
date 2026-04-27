import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';
import useExchangeRate from '@/hooks/useExchangeRate';
import { formatDisplayDate } from '@/utils/dateFormat';

const STATUS_CLASS = { occupied: 'occupied', vacant: 'vacant', overdue: 'overdue', maintenance: 'maintenance' };
const STATUS_LABEL = { occupied: 'Occupied', vacant: 'Vacant', overdue: 'Overdue', maintenance: 'Maintenance' };

export default function Dashboard({ stats, recentPayments, maintenanceItems, units, occupancyByFloor, upcomingEvents = [] }) {
  const { formatCompactTzs, formatMoney } = useExchangeRate();

  return (
    <AppLayout title="Dashboard" subtitle={new Date().toLocaleDateString('en', { month: 'long', year: 'numeric' })}>
      <Head title="Dashboard" />

      {/* Stats */}
      <div className="stats-grid">
        <div className="stat-card">
          <div className="stat-top">
            <div className="stat-icon blue"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg></div>
            {!!stats.totalUnitsDelta && (
              <span className={`stat-delta ${stats.totalUnitsDelta > 0 ? 'up' : 'down'}`}>
                {stats.totalUnitsDelta > 0 ? '↑' : '↓'} {Math.abs(stats.totalUnitsDelta)}
              </span>
            )}
          </div>
          <div className="stat-value">{stats.totalUnits}</div>
          <div className="stat-label">Total Units</div>
        </div>
        <div className="stat-card">
          <div className="stat-top">
            <div className="stat-icon green"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
            {!!stats.occupancyDelta && (
              <span className={`stat-delta ${stats.occupancyDelta > 0 ? 'up' : 'down'}`}>
                {stats.occupancyDelta > 0 ? '↑' : '↓'} {Math.abs(stats.occupancyDelta)}%
              </span>
            )}
          </div>
          <div className="stat-value">{stats.occupiedUnits}</div>
          <div className="stat-label">Occupied Units</div>
        </div>
        <div className="stat-card">
          <div className="stat-top">
            <div className="stat-icon amber"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></div>
            {!!stats.revenueDelta && (
              <span className={`stat-delta ${stats.revenueDelta > 0 ? 'up' : 'down'}`}>
                {stats.revenueDelta > 0 ? '↑' : '↓'} {formatCompactTzs(Math.abs(stats.revenueDelta))}
              </span>
            )}
          </div>
          <div className="stat-value">{formatCompactTzs(stats.monthlyRevenue)}</div>
          <div className="stat-label">Revenue / Month</div>
        </div>
        <div className="stat-card">
          <div className="stat-top">
            <div className="stat-icon red"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
            <span className="stat-delta down">{stats.overdueUnits} pending</span>
          </div>
          <div className="stat-value">{formatCompactTzs(stats.overdueBalance)}</div>
          <div className="stat-label">Overdue Rent</div>
        </div>
      </div>

      <div className="grid-2">
        <div className="card">
          <div className="card-header">
            <div>
              <div className="card-title">All Units</div>
              <div className="card-sub">{stats.totalUnits} units · {stats.occupiedUnits} occupied · {stats.vacantUnits} vacant</div>
            </div>
            <button className="card-action" onClick={() => window.location.href='/units'}>View all</button>
          </div>
          <div className="card-body">
            <table className="units-table">
              <thead>
                <tr>
                  <th>Unit</th>
                  <th>Tenant</th>
                  <th>Status</th>
                  <th>Rent</th>
                  <th>Due</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                {units.slice(0, 7).map((u) => {
                  const lease = u.leases?.[0];
                  const tenant = lease?.tenant;
                  const isOverdue     = u.status === 'overdue';
                  const isOccupied    = u.status === 'occupied';
                  const latestPayment = u.payments?.[0];
                  const paymentStatus = latestPayment?.status;

                  let dueLabel, dueClass;
                  if (paymentStatus === 'paid') {
                    dueLabel = 'Paid';                        dueClass = 'paid';
                  } else if (isOverdue || paymentStatus === 'overdue') {
                    dueLabel = formatMoney(u.rent, u.currency); dueClass = 'due';
                  } else if (paymentStatus === 'pending' || isOccupied) {
                    dueLabel = 'Pending';                     dueClass = '';
                  } else {
                    dueLabel = '—';                           dueClass = '';
                  }

                  return (
                    <tr key={u.id}>
                      <td>
                        <div className="unit-id">{u.unit_number}</div>
                        <div className="unit-floor">Floor {u.floor}</div>
                      </td>
                      <td>
                        {tenant ? (
                          <div className="tenant-cell">
                            <div className="t-avatar" style={{ background: tenant.color, color: tenant.text_color }}>{tenant.initials}</div>
                            {tenant.name}
                          </div>
                        ) : (
                          '—'
                        )}
                      </td>
                      <td><span className={`badge ${STATUS_CLASS[u.status]}`}>{STATUS_LABEL[u.status]}</span></td>
                      <td className="amount">{formatMoney(u.rent, u.currency)}</td>
                      <td className={`amount ${dueClass}`}>{dueLabel}</td>
                      <td><button className="action-dots">···</button></td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </div>

        <div className="card">
          <div className="card-header">
            <div>
              <div className="card-title">Upcoming</div>
              <div className="card-sub">Payments, moves & repairs</div>
            </div>
            <button className="card-action">Calendar</button>
          </div>
          <div className="events-list">
            {upcomingEvents.length === 0 ? (
              <div style={{ padding: '24px 16px', textAlign: 'center', color: 'var(--muted)', fontSize: 13 }}>
                No upcoming events in the next 7 days
              </div>
            ) : upcomingEvents.map((e) => (
              <div className="event-item" key={`${e.day}-${e.title}`}>
                <div className="event-date"><div className="event-day">{e.day}</div><div className="event-mon">{e.mon}</div></div>
                <div>
                  <div className="event-title">{e.title}</div>
                  <div className="event-meta">{e.meta}</div>
                </div>
                <span className={`event-type ${e.type}`}>{e.label}</span>
              </div>
            ))}
          </div>
        </div>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 16 }}>
        <div className="card">
          <div className="card-header">
            <div className="card-title">Occupancy by Floor</div>
          </div>
          <div className="floor-chart">
            {occupancyByFloor.map((f) => {
              const pct = f.total > 0 ? Math.round((f.occupied / f.total) * 100) : 0;
              const fillClass = pct === 100 ? 'full' : pct <= 60 ? 'low' : '';
              return (
                <div className="floor-row" key={f.floor}>
                  <span className="floor-label">Floor {f.floor}</span>
                  <div className="floor-bar-bg"><div className={`floor-bar-fill ${fillClass}`} style={{ width: `${pct}%` }}></div></div>
                  <span className="floor-pct">{pct}%</span>
                </div>
              );
            })}
          </div>
        </div>

        <div className="card">
          <div className="card-header">
            <div className="card-title">Recent Payments</div>
            <button className="card-action" onClick={() => window.location.href='/payments'}>All</button>
          </div>
          <div>
            {recentPayments.slice(0, 4).map((p) => (
              <div className="pay-item" key={p.id}>
                <div className="pay-icon" style={{ background: p.status === 'paid' ? 'var(--green-dim)' : 'var(--red-dim)', color: p.status === 'paid' ? 'var(--green)' : 'var(--red)' }}>
                  {p.status === 'paid'
                    ? <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    : <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                  }
                </div>
                <div className="pay-info">
                  <div className="pay-name">{p.tenant?.name || 'Unknown Tenant'}</div>
                  <div className="pay-unit">Unit {p.unit?.unit_number || '—'}</div>
                </div>
                <div>
                  <div className="pay-amount" style={{ color: p.status === 'paid' ? 'var(--green)' : 'var(--red)' }}>{p.status === 'paid' ? '+ ' : ''}{formatMoney(p.amount, p.currency)}</div>
                  <div className="pay-date">{p.paid_date ? formatDisplayDate(p.paid_date) : 'Overdue'}</div>
                </div>
              </div>
            ))}
          </div>
        </div>

        <div className="card">
          <div className="card-header">
            <div className="card-title">Maintenance</div>
            <button className="card-action" onClick={() => window.location.href='/maintenance'}>All</button>
          </div>
          <div>
            {maintenanceItems.slice(0, 4).map((t) => (
              <div className="maint-item" key={t.id}>
                <div className="maint-icon">{t.category === 'Plumbing' ? '🔧' : t.category === 'Electrical' ? '💡' : t.category === 'HVAC' ? '❄️' : t.category === 'Security' ? '🔒' : '🪛'}</div>
                <div className="maint-info">
                  <div className="maint-title">{t.title}</div>
                  <div className="maint-meta">Reported {formatDisplayDate(t.reported_date)} · {t.category}</div>
                </div>
                <span className={`priority ${t.priority}`}>{t.priority === 'med' ? 'Med' : t.priority.charAt(0).toUpperCase() + t.priority.slice(1)}</span>
              </div>
            ))}
          </div>
        </div>
      </div>
    </AppLayout>
  );
}
