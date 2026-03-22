import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';

const toNum = (v) => Number(v ?? 0) || 0;
const fmtMoney = (v) => `TZS ${toNum(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const fmtPct = (v) => `${toNum(v).toFixed(1)}%`;

const deltaMeta = (current, previous, inverse = false) => {
  const curr = toNum(current);
  const prev = toNum(previous);
  if (prev === 0) {
    if (curr === 0) return { delta: '0.0%', trend: 'flat' };
    return { delta: '100.0%', trend: inverse ? 'down' : 'up' };
  }

  const change = ((curr - prev) / Math.abs(prev)) * 100;
  const trend = change === 0 ? 'flat' : (change > 0 ? (inverse ? 'down' : 'up') : (inverse ? 'up' : 'down'));
  return { delta: `${Math.abs(change).toFixed(1)}%`, trend };
};

export default function ReportsIndex({ report = {} }) {
  const period = report.period ?? { label: 'Current Quarter', from: '', to: '' };
  const kpis = report.kpis ?? {};

  const revenueDelta = deltaMeta(kpis.revenue, kpis.prevRevenue);
  const maintenanceDelta = deltaMeta(kpis.maintenanceCost, kpis.prevMaintenanceCost, true);
  const noiDelta = deltaMeta(kpis.noi, kpis.prevNoi);

  const KPI_CARDS = [
    {
      title: `${period.label} Revenue`,
      value: fmtMoney(kpis.revenue),
      delta: `${revenueDelta.trend === 'up' ? '↑' : revenueDelta.trend === 'down' ? '↓' : '→'} ${revenueDelta.delta}`,
      trend: revenueDelta.trend === 'flat' ? 'up' : revenueDelta.trend,
      tone: 'green',
    },
    {
      title: 'Avg Occupancy',
      value: fmtPct(kpis.occupancyRate),
      delta: `${kpis.occupiedUnits ?? 0} of ${kpis.totalUnits ?? 0} units`,
      trend: 'up',
      tone: 'blue',
    },
    {
      title: 'Maintenance Cost',
      value: fmtMoney(kpis.maintenanceCost),
      delta: `${maintenanceDelta.trend === 'up' ? '↑' : maintenanceDelta.trend === 'down' ? '↓' : '→'} ${maintenanceDelta.delta}`,
      trend: maintenanceDelta.trend === 'flat' ? 'down' : maintenanceDelta.trend,
      tone: 'amber',
    },
    {
      title: 'Net Operating Income',
      value: fmtMoney(kpis.noi),
      delta: `${noiDelta.trend === 'up' ? '↑' : noiDelta.trend === 'down' ? '↓' : '→'} ${noiDelta.delta}`,
      trend: noiDelta.trend === 'flat' ? 'up' : noiDelta.trend,
      tone: 'green',
    },
  ];

  const MONTHLY_REVENUE = (report.monthlyRevenue ?? []).map((row) => ({
    month: row.month,
    value: fmtMoney(row.value),
    fill: row.fill,
    accent: Boolean(row.accent),
  }));

  const EXPENSE_BREAKDOWN = (report.expenseBreakdown ?? []).map((row) => ({
    label: row.label,
    value: fmtMoney(row.value),
    width: row.width,
    color: row.color,
  }));

  const TOP_UNITS = (report.topUnits ?? []).map((row) => ({
    unit: row.unit,
    meta: row.meta,
    amount: fmtMoney(row.amount),
  }));

  return (
    <AppLayout title="Reports" subtitle={period.label}>
      <Head title="Reports" />

      <div className="toolbar" style={{ marginBottom: 20 }}>
        <div style={{ fontSize: 13, color: 'var(--text-secondary)' }}>
          {period.from && period.to ? `${period.label} - ${period.from} through ${period.to}` : period.label}
        </div>
        <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
          <select className="form-input form-select" style={{ width: 130, padding: '6px 28px 6px 10px', fontSize: '12.5px' }}>
            <option>{period.label}</option>
          </select>
          <button className="btn-primary">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4" />
              <polyline points="7 10 12 15 17 10" />
              <line x1="12" y1="15" x2="12" y2="3" />
            </svg>
            Export PDF
          </button>
        </div>
      </div>

      <div className="stats-grid" style={{ marginBottom: 20 }}>
        {KPI_CARDS.map((card) => (
          <div key={card.title} className="stat-card">
            <div className="stat-top">
              <div className={`stat-icon ${card.tone}`}>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <line x1="18" y1="20" x2="18" y2="10" />
                  <line x1="12" y1="20" x2="12" y2="4" />
                  <line x1="6" y1="20" x2="6" y2="14" />
                </svg>
              </div>
              <span className={`stat-delta ${card.trend}`}>{card.delta}</span>
            </div>
            <div className="stat-value">{card.value}</div>
            <div className="stat-label">{card.title}</div>
          </div>
        ))}
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16, marginBottom: 16 }}>
        <div className="card">
          <div className="card-header">
            <div>
              <div className="card-title">Monthly Revenue</div>
              <div className="card-sub">{period.label}</div>
            </div>
          </div>
          <div style={{ padding: 20 }}>
            <div style={{ display: 'flex', alignItems: 'flex-end', gap: 8, height: 120 }}>
              {MONTHLY_REVENUE.length === 0 && (
                <div style={{ fontSize: 12, color: 'var(--text-muted)' }}>No revenue data in selected period</div>
              )}
              {MONTHLY_REVENUE.map((row) => (
                <div key={row.month} style={{ flex: 1, display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 6 }}>
                  <div style={{ fontSize: 11, fontWeight: 600, color: row.accent ? 'var(--accent)' : 'var(--text-secondary)' }}>{row.value}</div>
                  <div style={{ width: '100%', background: 'var(--accent-dim)', borderRadius: '6px 6px 0 0', height: 80, position: 'relative', overflow: 'hidden' }}>
                    <div style={{ position: 'absolute', bottom: 0, width: '100%', background: 'var(--accent)', height: `${row.fill}%`, borderRadius: '6px 6px 0 0', opacity: row.accent ? 1 : 0.8 }}></div>
                  </div>
                  <div style={{ fontSize: 11, color: 'var(--text-muted)' }}>{row.month}</div>
                </div>
              ))}
            </div>
          </div>
        </div>

        <div className="card">
          <div className="card-header"><div className="card-title">Expense Breakdown</div></div>
          <div style={{ padding: '16px 20px', display: 'flex', flexDirection: 'column', gap: 10 }}>
            {EXPENSE_BREAKDOWN.length === 0 && (
              <div style={{ fontSize: 12, color: 'var(--text-muted)' }}>No resolved maintenance expenses in this period</div>
            )}
            {EXPENSE_BREAKDOWN.map((row) => (
              <div key={row.label} style={{ display: 'grid', gridTemplateColumns: '130px 1fr 80px', alignItems: 'center', gap: 10 }}>
                <span style={{ fontSize: 11.5, color: 'var(--text-secondary)' }}>{row.label}</span>
                <div style={{ height: 6, background: 'var(--bg-elevated)', borderRadius: 20, overflow: 'hidden' }}>
                  <div style={{ height: '100%', width: `${row.width}%`, background: row.color, borderRadius: 20 }}></div>
                </div>
                <span style={{ fontSize: 11.5, color: 'var(--text-secondary)', textAlign: 'right' }}>{row.value}</span>
              </div>
            ))}
          </div>
        </div>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 16 }}>
        <div className="card">
          <div className="card-header"><div className="card-title">Occupancy Rate</div></div>
          <div style={{ padding: '16px 20px' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 16, marginBottom: 16 }}>
              <div style={{ fontSize: 36, fontWeight: 700, letterSpacing: -1 }}>{fmtPct(kpis.occupancyRate)}</div>
              <div>
                <div style={{ fontSize: 12, color: 'var(--green)', fontWeight: 500 }}>Live occupancy</div>
                <div style={{ fontSize: 11.5, color: 'var(--text-muted)' }}>{kpis.occupiedUnits ?? 0} of {kpis.totalUnits ?? 0} units occupied</div>
              </div>
            </div>
            <div style={{ height: 6, background: 'var(--bg-elevated)', borderRadius: 20, overflow: 'hidden' }}>
              <div style={{ height: '100%', width: `${Math.min(100, toNum(kpis.occupancyRate))}%`, background: 'var(--green)', borderRadius: 20 }}></div>
            </div>
          </div>
        </div>

        <div className="card">
          <div className="card-header"><div className="card-title">Collection Rate</div></div>
          <div style={{ padding: '16px 20px' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 16, marginBottom: 16 }}>
              <div style={{ fontSize: 36, fontWeight: 700, letterSpacing: -1 }}>{fmtPct(kpis.collectionRate)}</div>
              <div>
                <div style={{ fontSize: 12, color: 'var(--green)', fontWeight: 500 }}>Invoiced collection in period</div>
                <div style={{ fontSize: 11.5, color: 'var(--text-muted)' }}>{fmtMoney(kpis.paidAmount)} of {fmtMoney(kpis.invoicedAmount)} collected</div>
              </div>
            </div>
            <div style={{ height: 6, background: 'var(--bg-elevated)', borderRadius: 20, overflow: 'hidden' }}>
              <div style={{ height: '100%', width: `${Math.min(100, toNum(kpis.collectionRate))}%`, background: 'var(--accent)', borderRadius: 20 }}></div>
            </div>
          </div>
        </div>

        <div className="card">
          <div className="card-header"><div className="card-title">Top Revenue Units</div></div>
          <div style={{ padding: '8px 0' }}>
            {TOP_UNITS.length === 0 && <div className="pay-item"><div className="pay-info"><div className="pay-name">No paid unit revenue this period</div></div></div>}
            {TOP_UNITS.map((unit) => (
              <div key={unit.unit} className="pay-item">
                <div className="pay-info">
                  <div className="pay-name">{unit.unit}</div>
                  <div className="pay-unit">{unit.meta}</div>
                </div>
                <div style={{ textAlign: 'right' }}>
                  <div className="pay-amount" style={{ color: 'var(--green)' }}>{unit.amount}</div>
                  <div className="pay-date">/mo</div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </AppLayout>
  );
}
