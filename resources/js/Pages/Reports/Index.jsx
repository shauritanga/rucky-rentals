import { useState, useEffect } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import ReactApexChart from 'react-apexcharts';
import { formatDisplayDate } from '@/utils/dateFormat';

const getCSSVar = (name) =>
  typeof window !== 'undefined'
    ? getComputedStyle(document.documentElement).getPropertyValue(name).trim()
    : '#6366f1';

const toNum = (v) => Number(v ?? 0) || 0;
const fmtMoney = (v) => `TZS ${toNum(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const fmtPct   = (v) => `${toNum(v).toFixed(1)}%`;

const deltaMeta = (current, previous, inverse = false) => {
  const curr = toNum(current);
  const prev = toNum(previous);
  if (prev === 0) {
    if (curr === 0) return { delta: '0.0%', trend: 'flat' };
    return { delta: '100.0%', trend: inverse ? 'down' : 'up' };
  }
  const change = ((curr - prev) / Math.abs(prev)) * 100;
  const trend  = change === 0 ? 'flat' : (change > 0 ? (inverse ? 'down' : 'up') : (inverse ? 'up' : 'down'));
  return { delta: `${Math.abs(change).toFixed(1)}%`, trend };
};

const TABS = [
  { key: 'overview',  label: 'Overview' },
  { key: 'ar_aging',  label: 'AR Aging' },
  { key: 'leases',    label: 'Lease Expiry' },
  { key: 'tenants',   label: 'Tenant Summary' },
];

const AGE_COLORS = { green: 'var(--green)', amber: 'var(--amber)', orange: 'var(--amber)', red: 'var(--red)' };

export default function ReportsIndex({ report = {}, availablePeriods = [], properties = [], filters = {} }) {
  const period = report.period ?? { label: 'Current Quarter', from: '', to: '', preset: 'this_quarter' };
  const kpis   = report.kpis ?? {};

  const [activeTab,    setActiveTab]    = useState('overview');
  const [selPeriod,    setSelPeriod]    = useState(filters.period ?? 'this_quarter');
  const [customFrom,   setCustomFrom]   = useState(filters.from ?? '');
  const [customTo,     setCustomTo]     = useState(filters.to ?? '');
  const [selProperty,  setSelProperty]  = useState(filters.property_id ?? '');
  const [chartMounted, setChartMounted] = useState(false);
  useEffect(() => setChartMounted(true), []);

  const isCustom = selPeriod === 'custom';

  function applyFilters(overrides = {}) {
    const params = {
      period:      overrides.period      ?? selPeriod,
      property_id: (overrides.property_id ?? selProperty) || undefined,
    };
    if ((overrides.period ?? selPeriod) === 'custom') {
      params.from = overrides.from ?? customFrom;
      params.to   = overrides.to   ?? customTo;
    }
    Object.keys(params).forEach((k) => (params[k] === '' || params[k] === undefined) && delete params[k]);
    router.get(route('reports'), params, { preserveState: true, replace: true });
  }

  function handlePeriodChange(e) {
    const val = e.target.value;
    setSelPeriod(val);
    if (val !== 'custom') applyFilters({ period: val });
  }

  function handleCustomApply() {
    if (customFrom && customTo) applyFilters({ period: 'custom', from: customFrom, to: customTo });
  }

  function buildExportUrl(type) {
    const params = new URLSearchParams();
    params.set('type', type);
    params.set('period', selPeriod);
    if (selPeriod === 'custom') {
      if (customFrom) params.set('from', customFrom);
      if (customTo)   params.set('to', customTo);
    }
    if (selProperty) params.set('property_id', selProperty);
    return `/reports/export?${params.toString()}`;
  }

  const exportType = activeTab === 'ar_aging' ? 'ar_aging' : activeTab === 'leases' ? 'leases' : activeTab === 'tenants' ? 'tenants' : 'overview';

  const totalExpenses     = kpis.totalExpenses ?? kpis.maintenanceCost;
  const prevTotalExpenses = kpis.prevTotalExpenses ?? kpis.prevMaintenanceCost;
  const revenueDelta      = deltaMeta(kpis.revenue, kpis.prevRevenue);
  const expenseDelta      = deltaMeta(totalExpenses, prevTotalExpenses, true);
  const noiDelta          = deltaMeta(kpis.noi, kpis.prevNoi);

  const KPI_CARDS = [
    { title: 'Gross Revenue',  value: fmtMoney(kpis.revenue),    delta: `${revenueDelta.trend === 'up' ? '↑' : revenueDelta.trend === 'down' ? '↓' : '→'} ${revenueDelta.delta}`, trend: revenueDelta.trend === 'flat' ? 'up' : revenueDelta.trend, tone: 'green' },
    { title: 'Avg Occupancy',  value: fmtPct(kpis.occupancyRate), delta: `${kpis.occupiedUnits ?? 0} of ${kpis.totalUnits ?? 0} units`,                                      trend: 'up',                                                       tone: 'blue'  },
    { title: 'Total Expenses', value: fmtMoney(totalExpenses),    delta: `${expenseDelta.trend === 'up' ? '↑' : expenseDelta.trend === 'down' ? '↓' : '→'} ${expenseDelta.delta}`, trend: expenseDelta.trend === 'flat' ? 'down' : expenseDelta.trend, tone: 'amber' },
    { title: 'Net Revenue',    value: fmtMoney(kpis.noi),         delta: `${noiDelta.trend === 'up' ? '↑' : noiDelta.trend === 'down' ? '↓' : '→'} ${noiDelta.delta}`,           trend: noiDelta.trend === 'flat' ? 'up' : noiDelta.trend,         tone: 'green' },
  ];

  const MONTHLY_REVENUE   = report.monthlyRevenue   ?? [];
  const EXPENSE_BREAKDOWN = (report.expenseBreakdown ?? []).map((r) => ({ ...r, value: fmtMoney(r.value) }));
  const TOP_UNITS         = (report.topUnits         ?? []).map((r) => ({ ...r, amount: fmtMoney(r.amount) }));
  const AR_AGING          = (report.arAging          ?? []);
  const LEASE_EXPIRY      = (report.leaseExpiry      ?? []);
  const TENANT_SUMMARY    = (report.tenantSummary    ?? []);

  // Financial obligation KPI cards
  const FIN_CARDS = [
    { title: 'VAT Collected',          value: fmtMoney(kpis.vatTotal),     sub: 'Electricity, generator & lease VAT on paid invoices', tone: 'blue'  },
    { title: 'WHT Withheld',           value: fmtMoney(kpis.whtTotal),     sub: 'Confirmed withholding tax in period',                  tone: 'amber' },
    { title: 'Security Deposits Held', value: fmtMoney(kpis.depositsHeld), sub: 'Across all active leases — live balance',              tone: 'green' },
  ];

  // ApexCharts config — resolved at render time so CSS vars are available
  const accentColor  = getCSSVar('--accent');
  const greenColor   = getCSSVar('--green');
  const amberColor   = getCSSVar('--amber');
  const monthLabels  = MONTHLY_REVENUE.map(r => r.month);

  const areaOptions = {
    chart: { type: 'area', toolbar: { show: false }, background: 'transparent', animations: { enabled: true, speed: 600 } },
    stroke: { curve: 'smooth', width: 2 },
    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.02, stops: [0, 100] } },
    dataLabels: { enabled: false },
    xaxis: { categories: monthLabels, axisBorder: { show: false }, axisTicks: { show: false }, labels: { style: { colors: '#888', fontSize: '11px' } } },
    yaxis: { labels: { formatter: v => v >= 1e9 ? `${(v/1e9).toFixed(1)}B` : v >= 1e6 ? `${(v/1e6).toFixed(1)}M` : v >= 1e3 ? `${(v/1e3).toFixed(0)}k` : String(Math.round(v)), style: { colors: '#888', fontSize: '11px' } } },
    tooltip: { theme: 'dark', y: { formatter: v => fmtMoney(v) } },
    grid: { borderColor: 'rgba(255,255,255,0.06)', strokeDashArray: 4, xaxis: { lines: { show: false } } },
    colors: [accentColor || '#6366f1'],
  };
  const areaSeries = [{ name: 'Revenue', data: MONTHLY_REVENUE.map(r => r.value) }];

  const stackedOptions = {
    chart: { type: 'bar', stacked: false, toolbar: { show: false }, background: 'transparent', animations: { enabled: true, speed: 600 } },
    plotOptions: { bar: { borderRadius: 3, columnWidth: '55%' } },
    dataLabels: { enabled: false },
    xaxis: { categories: monthLabels, axisBorder: { show: false }, axisTicks: { show: false }, labels: { style: { colors: '#888', fontSize: '11px' } } },
    yaxis: { labels: { formatter: v => v >= 1e9 ? `${(v/1e9).toFixed(1)}B` : v >= 1e6 ? `${(v/1e6).toFixed(1)}M` : v >= 1e3 ? `${(v/1e3).toFixed(0)}k` : String(Math.round(v)), style: { colors: '#888', fontSize: '11px' } } },
    tooltip: { theme: 'dark', y: { formatter: v => fmtMoney(v) } },
    legend: { position: 'top', horizontalAlign: 'right', labels: { colors: '#aaa' }, fontSize: '12px' },
    grid: { borderColor: 'rgba(255,255,255,0.06)', strokeDashArray: 4 },
    colors: [accentColor || '#6366f1', greenColor || '#22c55e', amberColor || '#f59e0b'],
    fill: { opacity: 1 },
  };
  const stackedSeries = [
    { name: 'Rent',           data: MONTHLY_REVENUE.map(r => r.rent           ?? 0) },
    { name: 'Service Charge', data: MONTHLY_REVENUE.map(r => r.service_charge ?? 0) },
    { name: 'Electricity',    data: MONTHLY_REVENUE.map(r => r.electricity    ?? 0) },
  ];

  return (
    <AppLayout title="Reports" subtitle={period.label}>
      <Head title="Reports" />

      <div className="reports-page">
      {/* Toolbar */}
      <div className="toolbar" style={{ marginBottom: 20, flexWrap: 'wrap', gap: 8 }}>
        <div style={{ fontSize: 13, color: 'var(--text-secondary)' }}>
          {period.from && period.to ? `${period.label} · ${period.from} through ${period.to}` : period.label}
        </div>

        <div style={{ display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap' }}>
          {/* Property selector — superusers only */}
          {properties.length > 0 && (
            <select
              className="form-input form-select"
              style={{ width: 160, padding: '6px 28px 6px 10px', fontSize: '12.5px' }}
              value={selProperty}
              onChange={(e) => { setSelProperty(e.target.value); applyFilters({ property_id: e.target.value }); }}
            >
              <option value="">All Properties</option>
              {properties.map((p) => (
                <option key={p.id} value={p.id}>{p.name}</option>
              ))}
            </select>
          )}

          {/* Period selector */}
          <select
            className="form-input form-select"
            style={{ width: 190, padding: '6px 28px 6px 10px', fontSize: '12.5px' }}
            value={selPeriod}
            onChange={handlePeriodChange}
          >
            {availablePeriods.map((p) => (
              <option key={p.value} value={p.value}>{p.label}</option>
            ))}
          </select>

          {/* Custom date range */}
          {isCustom && (
            <>
              <input
                type="date"
                className="form-input"
                style={{ width: 140, padding: '6px 10px', fontSize: '12.5px' }}
                value={customFrom}
                onChange={(e) => setCustomFrom(e.target.value)}
              />
              <span style={{ fontSize: 12, color: 'var(--text-muted)' }}>to</span>
              <input
                type="date"
                className="form-input"
                style={{ width: 140, padding: '6px 10px', fontSize: '12.5px' }}
                value={customTo}
                onChange={(e) => setCustomTo(e.target.value)}
              />
              <button
                className="btn-primary"
                onClick={handleCustomApply}
                disabled={!customFrom || !customTo}
              >
                Apply
              </button>
            </>
          )}

          {/* Export CSV */}
          <a href={buildExportUrl(exportType)} className="btn-primary" style={{ textDecoration: 'none', display: 'flex', alignItems: 'center', gap: 6 }}>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4" />
              <polyline points="7 10 12 15 17 10" />
              <line x1="12" y1="15" x2="12" y2="3" />
            </svg>
            Export CSV
          </a>
        </div>
      </div>

      {/* Tab Bar */}
      <div className="reports-tabbar" style={{ display: 'flex', gap: 2, marginBottom: 20, borderBottom: '1px solid var(--border)' }}>
        {TABS.map((tab) => (
          <button
            key={tab.key}
            onClick={() => setActiveTab(tab.key)}
            style={{
              padding: '8px 16px',
              fontSize: 13,
              fontWeight: activeTab === tab.key ? 600 : 400,
              color: activeTab === tab.key ? 'var(--accent)' : 'var(--text-secondary)',
              background: 'none',
              border: 'none',
              borderBottom: activeTab === tab.key ? '2px solid var(--accent)' : '2px solid transparent',
              cursor: 'pointer',
              marginBottom: -1,
            }}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {/* ── Overview Tab ── */}
      {activeTab === 'overview' && (
        <>
          <div className="stats-grid reports-kpi-grid" style={{ marginBottom: 20 }}>
            {KPI_CARDS.map((card) => (
              <div key={card.title} className="stat-card reports-stat-card">
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

          {/* Financial Obligations row */}
            <div style={{ marginBottom: 8 }}>
              <div style={{ fontSize: 11, fontWeight: 700, letterSpacing: '.8px', textTransform: 'uppercase', color: 'var(--text-muted)', marginBottom: 10 }}>Financial Obligations</div>
            <div className="reports-fin-grid" style={{ display: 'grid', gridTemplateColumns: 'repeat(3,1fr)', gap: 16, marginBottom: 20 }}>
              {FIN_CARDS.map((card) => (
                <div key={card.title} className="stat-card reports-stat-card">
                  <div className="stat-top">
                    <div className={`stat-icon ${card.tone}`}>
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
                    </div>
                  </div>
                  <div className="stat-value">{card.value}</div>
                  <div className="stat-label">{card.title}</div>
                  <div className="reports-card-note" style={{ fontSize: 10.5, color: 'var(--text-muted)', marginTop: 3, lineHeight: 1.35 }}>{card.sub}</div>
                </div>
              ))}
            </div>
          </div>

          <div className="reports-dual-grid" style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16, marginBottom: 16 }}>
            <div className="card">
              <div className="card-header">
                <div>
                  <div className="card-title">Monthly Revenue</div>
                  <div className="card-sub">{period.label}</div>
                </div>
              </div>
              <div style={{ padding: '8px 12px 16px' }}>
                {MONTHLY_REVENUE.length === 0
                  ? <div style={{ fontSize: 12, color: 'var(--text-muted)', padding: '24px 8px' }}>No revenue data in selected period</div>
                  : chartMounted && <ReactApexChart type="area" options={areaOptions} series={areaSeries} height={180} />
                }
              </div>
            </div>

            <div className="card">
              <div className="card-header"><div className="card-title">Expense Breakdown</div></div>
              <div style={{ padding: '16px 20px', display: 'flex', flexDirection: 'column', gap: 10 }}>
                {EXPENSE_BREAKDOWN.length === 0 && (
                  <div style={{ fontSize: 12, color: 'var(--text-muted)' }}>No expenses in this period</div>
                )}
                {EXPENSE_BREAKDOWN.map((row) => (
                  <div key={row.label} style={{ display: 'grid', gridTemplateColumns: '130px 1fr 80px', alignItems: 'center', gap: 10 }}>
                    <span style={{ fontSize: 11.5, color: 'var(--text-secondary)' }}>{row.label}</span>
                    <div style={{ height: 6, background: 'var(--bg-elevated)', borderRadius: 20, overflow: 'hidden' }}>
                      <div style={{ height: '100%', width: `${row.width}%`, background: row.color, borderRadius: 20 }} />
                    </div>
                    <span style={{ fontSize: 11.5, color: 'var(--text-secondary)', textAlign: 'right' }}>{row.value}</span>
                  </div>
                ))}
              </div>
            </div>
          </div>

          {/* Revenue breakdown by type — full width stacked bar */}
          <div className="card" style={{ marginBottom: 16 }}>
            <div className="card-header">
              <div>
                <div className="card-title">Revenue Breakdown by Type</div>
                <div className="card-sub">Grouped monthly bars for rent, service charge, and electricity — {period.label}</div>
              </div>
            </div>
            <div style={{ padding: '8px 12px 16px' }}>
              {MONTHLY_REVENUE.length === 0
                ? <div style={{ fontSize: 12, color: 'var(--text-muted)', padding: '24px 8px' }}>No data in selected period</div>
                : chartMounted && <ReactApexChart type="bar" options={stackedOptions} series={stackedSeries} height={340} />
              }
            </div>
          </div>

          <div className="reports-triple-grid" style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 16 }}>
            <div className="card">
              <div className="card-header"><div className="card-title">Occupancy Rate</div></div>
              <div style={{ padding: '16px 20px' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 16, marginBottom: 16 }}>
                  <div style={{ fontSize: 22, fontWeight: 700, letterSpacing: -0.5 }}>{fmtPct(kpis.occupancyRate)}</div>
                  <div>
                    <div style={{ fontSize: 12, color: 'var(--green)', fontWeight: 500 }}>Live occupancy</div>
                    <div style={{ fontSize: 11.5, color: 'var(--text-muted)' }}>{kpis.occupiedUnits ?? 0} of {kpis.totalUnits ?? 0} units occupied</div>
                  </div>
                </div>
                <div style={{ height: 6, background: 'var(--bg-elevated)', borderRadius: 20, overflow: 'hidden' }}>
                  <div style={{ height: '100%', width: `${Math.min(100, toNum(kpis.occupancyRate))}%`, background: 'var(--green)', borderRadius: 20 }} />
                </div>
              </div>
            </div>

            <div className="card">
              <div className="card-header"><div className="card-title">Collection Rate</div></div>
              <div style={{ padding: '16px 20px' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 16, marginBottom: 16 }}>
                  <div style={{ fontSize: 22, fontWeight: 700, letterSpacing: -0.5 }}>{fmtPct(kpis.collectionRate)}</div>
                  <div>
                    <div style={{ fontSize: 12, color: 'var(--green)', fontWeight: 500 }}>Invoiced collection in period</div>
                    <div style={{ fontSize: 11.5, color: 'var(--text-muted)' }}>{fmtMoney(kpis.paidAmount)} of {fmtMoney(kpis.invoicedAmount)} collected</div>
                  </div>
                </div>
                <div style={{ height: 6, background: 'var(--bg-elevated)', borderRadius: 20, overflow: 'hidden' }}>
                  <div style={{ height: '100%', width: `${Math.min(100, toNum(kpis.collectionRate))}%`, background: 'var(--accent)', borderRadius: 20 }} />
                </div>
              </div>
            </div>

            <div className="card">
              <div className="card-header"><div className="card-title">Top Revenue Units</div></div>
              <div style={{ padding: '8px 0' }}>
                {TOP_UNITS.length === 0 && (
                  <div className="pay-item"><div className="pay-info"><div className="pay-name">No paid unit revenue this period</div></div></div>
                )}
                {TOP_UNITS.map((unit) => (
                  <div key={unit.unit} className="pay-item">
                    <div className="pay-info">
                      <div className="pay-name">{unit.unit}</div>
                      <div className="pay-unit">{unit.meta}</div>
                    </div>
                    <div style={{ textAlign: 'right' }}>
                      <div className="pay-amount" style={{ color: 'var(--green)' }}>{unit.amount}</div>
                      <div className="pay-date">/period</div>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </>
      )}

      {/* ── AR Aging Tab ── */}
      {activeTab === 'ar_aging' && (
        <div className="card">
          <div className="card-header">
            <div>
              <div className="card-title">Accounts Receivable Aging</div>
              <div className="card-sub">All unpaid &amp; overdue invoices, grouped by age</div>
            </div>
          </div>
          <div style={{ padding: '0 0 8px' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 13 }}>
              <thead>
                <tr style={{ borderBottom: '1px solid var(--border)' }}>
                  {['Age Bucket', 'Invoice Count', 'Outstanding Amount', ''].map((h) => (
                    <th key={h} style={{ padding: '10px 20px', textAlign: h === 'Outstanding Amount' ? 'right' : 'left', fontSize: 11.5, fontWeight: 600, color: 'var(--text-muted)', textTransform: 'uppercase', letterSpacing: '0.05em' }}>{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {AR_AGING.map((row) => {
                  const barColor = AGE_COLORS[row.color] ?? 'var(--accent)';
                  const totalAmount = AR_AGING.reduce((s, r) => s + r.amount, 0);
                  const pct = totalAmount > 0 ? Math.round((row.amount / totalAmount) * 100) : 0;
                  return (
                    <tr key={row.label} style={{ borderBottom: '1px solid var(--border-dim)' }}>
                      <td style={{ padding: '12px 20px' }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                          <div style={{ width: 8, height: 8, borderRadius: '50%', background: barColor, flexShrink: 0 }} />
                          <span style={{ fontWeight: 500 }}>{row.label}</span>
                        </div>
                      </td>
                      <td style={{ padding: '12px 20px', color: 'var(--text-secondary)' }}>{row.count} invoice{row.count !== 1 ? 's' : ''}</td>
                      <td style={{ padding: '12px 20px', textAlign: 'right', fontWeight: 600, color: row.amount > 0 ? barColor : 'var(--text-muted)' }}>{fmtMoney(row.amount)}</td>
                      <td style={{ padding: '12px 20px', width: 140 }}>
                        {row.amount > 0 && (
                          <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                            <div style={{ flex: 1, height: 4, background: 'var(--bg-elevated)', borderRadius: 20, overflow: 'hidden' }}>
                              <div style={{ height: '100%', width: `${pct}%`, background: barColor, borderRadius: 20 }} />
                            </div>
                            <span style={{ fontSize: 11, color: 'var(--text-muted)', minWidth: 28 }}>{pct}%</span>
                          </div>
                        )}
                      </td>
                    </tr>
                  );
                })}
                {AR_AGING.every((r) => r.count === 0) && (
                  <tr>
                    <td colSpan={4} style={{ padding: '24px 20px', textAlign: 'center', color: 'var(--text-muted)', fontSize: 13 }}>
                      No outstanding invoices
                    </td>
                  </tr>
                )}
              </tbody>
              {AR_AGING.some((r) => r.amount > 0) && (
                <tfoot>
                  <tr style={{ borderTop: '2px solid var(--border)' }}>
                    <td style={{ padding: '12px 20px', fontWeight: 600 }}>Total Outstanding</td>
                    <td style={{ padding: '12px 20px', color: 'var(--text-secondary)' }}>{AR_AGING.reduce((s, r) => s + r.count, 0)} invoices</td>
                    <td style={{ padding: '12px 20px', textAlign: 'right', fontWeight: 700, color: 'var(--red)' }}>{fmtMoney(AR_AGING.reduce((s, r) => s + r.amount, 0))}</td>
                    <td />
                  </tr>
                </tfoot>
              )}
            </table>
          </div>
        </div>
      )}

      {/* ── Lease Expiry Tab ── */}
      {activeTab === 'leases' && (
        <div className="card">
          <div className="card-header">
            <div>
              <div className="card-title">Lease Expiry</div>
              <div className="card-sub">Leases expiring within the next 90 days</div>
            </div>
            <div style={{ fontSize: 13, fontWeight: 600, color: LEASE_EXPIRY.length > 0 ? 'var(--amber)' : 'var(--text-muted)' }}>
              {LEASE_EXPIRY.length} lease{LEASE_EXPIRY.length !== 1 ? 's' : ''} expiring soon
            </div>
          </div>
          <div style={{ padding: '0 0 8px' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 13 }}>
              <thead>
                <tr style={{ borderBottom: '1px solid var(--border)' }}>
                  {['Unit', 'Tenant', 'End Date', 'Days Left', 'Status'].map((h) => (
                    <th key={h} style={{ padding: '10px 20px', textAlign: 'left', fontSize: 11.5, fontWeight: 600, color: 'var(--text-muted)', textTransform: 'uppercase', letterSpacing: '0.05em' }}>{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {LEASE_EXPIRY.map((row, i) => {
                  const urgency = row.days_left <= 14 ? 'var(--red)' : row.days_left <= 30 ? 'var(--amber)' : 'var(--green)';
                  return (
                    <tr key={i} style={{ borderBottom: '1px solid var(--border-dim)' }}>
                      <td style={{ padding: '12px 20px', fontWeight: 500 }}>{row.unit}</td>
                      <td style={{ padding: '12px 20px', color: 'var(--text-secondary)' }}>{row.tenant}</td>
                      <td style={{ padding: '12px 20px', color: 'var(--text-secondary)' }}>{formatDisplayDate(row.end_date)}</td>
                      <td style={{ padding: '12px 20px' }}>
                        <span style={{ fontWeight: 700, color: urgency }}>{row.days_left}d</span>
                      </td>
                      <td style={{ padding: '12px 20px' }}>
                        <span className={`badge ${row.status}`}>{row.status}</span>
                      </td>
                    </tr>
                  );
                })}
                {LEASE_EXPIRY.length === 0 && (
                  <tr>
                    <td colSpan={5} style={{ padding: '24px 20px', textAlign: 'center', color: 'var(--text-muted)', fontSize: 13 }}>
                      No leases expiring in the next 90 days
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* ── Tenant Summary Tab ── */}
      {activeTab === 'tenants' && (
        <div className="card">
          <div className="card-header">
            <div>
              <div className="card-title">Tenant Payment Summary</div>
              <div className="card-sub">{period.label} · invoiced vs collected per tenant</div>
            </div>
          </div>
          <div style={{ padding: '0 0 8px' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 13 }}>
              <thead>
                <tr style={{ borderBottom: '1px solid var(--border)' }}>
                  {['Tenant', 'Invoiced', 'Paid', 'Balance', 'Last Payment'].map((h) => (
                    <th key={h} style={{ padding: '10px 20px', textAlign: h === 'Tenant' || h === 'Last Payment' ? 'left' : 'right', fontSize: 11.5, fontWeight: 600, color: 'var(--text-muted)', textTransform: 'uppercase', letterSpacing: '0.05em' }}>{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {TENANT_SUMMARY.map((row, i) => (
                  <tr key={i} style={{ borderBottom: '1px solid var(--border-dim)' }}>
                    <td style={{ padding: '12px 20px', fontWeight: 500 }}>{row.tenant}</td>
                    <td style={{ padding: '12px 20px', textAlign: 'right', color: 'var(--text-secondary)' }}>{fmtMoney(row.invoiced)}</td>
                    <td style={{ padding: '12px 20px', textAlign: 'right', color: 'var(--green)', fontWeight: 500 }}>{fmtMoney(row.paid)}</td>
                    <td style={{ padding: '12px 20px', textAlign: 'right', fontWeight: row.balance > 0 ? 700 : 400, color: row.balance > 0 ? 'var(--red)' : 'var(--text-muted)' }}>
                      {row.balance > 0 ? fmtMoney(row.balance) : '—'}
                    </td>
                    <td style={{ padding: '12px 20px', color: 'var(--text-secondary)' }}>{row.last_payment ?? '—'}</td>
                  </tr>
                ))}
                {TENANT_SUMMARY.length === 0 && (
                  <tr>
                    <td colSpan={5} style={{ padding: '24px 20px', textAlign: 'center', color: 'var(--text-muted)', fontSize: 13 }}>
                      No tenant activity in this period
                    </td>
                  </tr>
                )}
              </tbody>
              {TENANT_SUMMARY.length > 0 && (
                <tfoot>
                  <tr style={{ borderTop: '2px solid var(--border)' }}>
                    <td style={{ padding: '12px 20px', fontWeight: 600 }}>Total</td>
                    <td style={{ padding: '12px 20px', textAlign: 'right', fontWeight: 600 }}>{fmtMoney(TENANT_SUMMARY.reduce((s, r) => s + r.invoiced, 0))}</td>
                    <td style={{ padding: '12px 20px', textAlign: 'right', fontWeight: 600, color: 'var(--green)' }}>{fmtMoney(TENANT_SUMMARY.reduce((s, r) => s + r.paid, 0))}</td>
                    <td style={{ padding: '12px 20px', textAlign: 'right', fontWeight: 700, color: 'var(--red)' }}>{fmtMoney(TENANT_SUMMARY.reduce((s, r) => s + r.balance, 0))}</td>
                    <td />
                  </tr>
                </tfoot>
              )}
            </table>
          </div>
        </div>
      )}
      </div>
    </AppLayout>
  );
}
