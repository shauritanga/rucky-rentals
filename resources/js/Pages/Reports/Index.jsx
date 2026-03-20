import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';

const KPI_CARDS = [
  { title: 'Q1 Revenue', value: 'TZS 124k', delta: '↑ 6.2%', trend: 'up', tone: 'green' },
  { title: 'Avg Occupancy', value: '87.5%', delta: '↑ 3%', trend: 'up', tone: 'blue' },
  { title: 'Maintenance Cost', value: 'TZS 8.4k', delta: '↓ 12%', trend: 'down', tone: 'amber' },
  { title: 'Net Operating Income', value: 'TZS 115.6k', delta: '↑ 8.1%', trend: 'up', tone: 'green' },
];

const MONTHLY_REVENUE = [
  { month: 'Jan', value: 'TZS 40.1k', fill: 76, accent: false },
  { month: 'Feb', value: 'TZS 41.5k', fill: 88, accent: false },
  { month: 'Mar', value: 'TZS 42k', fill: 100, accent: true },
];

const EXPENSE_BREAKDOWN = [
  { label: 'Maintenance & Repairs', value: 'TZS 4,380', width: 52, color: 'var(--amber)' },
  { label: 'Cleaning & Sanitation', value: 'TZS 2,360', width: 28, color: 'var(--accent)' },
  { label: 'Security', value: 'TZS 1,680', width: 20, color: 'var(--green)' },
  { label: 'Utilities (Common)', value: 'TZS 1,340', width: 16, color: 'var(--red)' },
  { label: 'Admin & Insurance', value: 'TZS 840', width: 10, color: 'var(--text-muted)' },
];

const TOP_UNITS = [
  { unit: 'F-601 - Penthouse', meta: 'Floor 6 - Charles Kiprop', amount: 'TZS 4,500' },
  { unit: 'F-605 - Penthouse', meta: 'Floor 6 - David Kamau', amount: 'TZS 4,200' },
  { unit: 'F-602 - 3 Bed', meta: 'Floor 6 - Lydia Wambui', amount: 'TZS 2,400' },
];

export default function ReportsIndex() {
  return (
    <AppLayout title="Reports" subtitle="Q1 2026">
      <Head title="Reports" />

      <div className="toolbar" style={{ marginBottom: 20 }}>
        <div style={{ fontSize: 13, color: 'var(--text-secondary)' }}>Q1 2026 - Jan through Mar</div>
        <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
          <select className="form-input form-select" style={{ width: 130, padding: '6px 28px 6px 10px', fontSize: '12.5px' }}>
            <option>Q1 2026</option>
            <option>Q4 2025</option>
            <option>Q3 2025</option>
            <option>Full Year 2025</option>
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
              <div className="card-sub">Jan - Mar 2026</div>
            </div>
          </div>
          <div style={{ padding: 20 }}>
            <div style={{ display: 'flex', alignItems: 'flex-end', gap: 8, height: 120 }}>
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
              <div style={{ fontSize: 36, fontWeight: 700, letterSpacing: -1 }}>87.5%</div>
              <div>
                <div style={{ fontSize: 12, color: 'var(--green)', fontWeight: 500 }}>↑ 3% from last quarter</div>
                <div style={{ fontSize: 11.5, color: 'var(--text-muted)' }}>28 of 32 units occupied</div>
              </div>
            </div>
            <div style={{ height: 6, background: 'var(--bg-elevated)', borderRadius: 20, overflow: 'hidden' }}>
              <div style={{ height: '100%', width: '87.5%', background: 'var(--green)', borderRadius: 20 }}></div>
            </div>
          </div>
        </div>

        <div className="card">
          <div className="card-header"><div className="card-title">Collection Rate</div></div>
          <div style={{ padding: '16px 20px' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 16, marginBottom: 16 }}>
              <div style={{ fontSize: 36, fontWeight: 700, letterSpacing: -1 }}>91%</div>
              <div>
                <div style={{ fontSize: 12, color: 'var(--green)', fontWeight: 500 }}>↑ 2% from last month</div>
                <div style={{ fontSize: 11.5, color: 'var(--text-muted)' }}>TZS 38.2k of TZS 42k collected</div>
              </div>
            </div>
            <div style={{ height: 6, background: 'var(--bg-elevated)', borderRadius: 20, overflow: 'hidden' }}>
              <div style={{ height: '100%', width: '91%', background: 'var(--accent)', borderRadius: 20 }}></div>
            </div>
          </div>
        </div>

        <div className="card">
          <div className="card-header"><div className="card-title">Top Revenue Units</div></div>
          <div style={{ padding: '8px 0' }}>
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
