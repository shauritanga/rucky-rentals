import { useEffect, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import ReportTabs from './ReportTabs';

const money = (v) => Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

// key, heading, kind ('money' | 'text' | 'center')
const COLUMNS = [
  ['tenant', 'Tenant', 'text'],
  ['unit', 'Unit', 'text'],
  ['period', 'Invoice date', 'text'],
  ['terms', 'No. of terms', 'center'],
  ['rent_per_month', 'Rent per month', 'money'],
  ['sc_per_month', 'Service charge', 'money'],
  ['total_rent', 'Total rent', 'money'],
  ['total_sc', 'Total service charge', 'money'],
  ['excl', 'Invoice amount (exclusive)', 'money'],
  ['vat', 'VAT amount', 'money'],
  ['incl', 'Invoice amount (inclusive)', 'money'],
  ['wht_rent', 'WHT on rent', 'money'],
  ['wht_sc', 'WHT on service charge', 'money'],
  ['wht_total', 'Total WHT amount', 'money'],
  ['net_after_wht', 'Invoice net of WHT', 'money'],
  ['wht_paid', 'WHT paid amount', 'money'],
  ['paid', 'Invoice amount paid', 'money'],
  ['balance', 'Invoice balance amount', 'money'],
  ['efd', 'EFD receipt', 'text'],
  ['remark', 'Remarks', 'remark'],
];

function RemarkCell({ row }) {
  const [value, setValue] = useState(row.remark ?? '');
  useEffect(() => { setValue(row.remark ?? ''); }, [row.remark]);

  const save = () => {
    if (value === (row.remark ?? '')) return;
    router.patch(route('reports.floor-schedule.remark', row.installment_id), { remark: value }, { preserveScroll: true, preserveState: true });
  };

  return (
    <input
      className="fs-remark"
      value={value}
      maxLength={255}
      placeholder="Add remark"
      onChange={(e) => setValue(e.target.value)}
      onBlur={save}
      onKeyDown={(e) => e.key === 'Enter' && e.currentTarget.blur()}
    />
  );
}

export default function FloorSchedule({ report, properties = [], floors = [], filters, canPickProperty }) {
  const [form, setForm] = useState(filters);
  const set = (key, value) => setForm((f) => ({ ...f, [key]: value }));

  const query = (extra = {}) => {
    const q = { ...form, ...extra };
    Object.keys(q).forEach((k) => (q[k] === '' || q[k] == null) && delete q[k]);
    return q;
  };

  const apply = () => router.get(route('reports.floor-schedule'), query(), { preserveScroll: true });
  const exportAs = (format) => { window.location.href = route('reports.floor-schedule.export', query({ format })); };

  return (
    <AppLayout title="Reports" subtitle="Floor tenant schedule">
      <Head title="Floor Tenant Schedule" />

      <ReportTabs
        active="floor"
        tabs={[
          { key: 'overview', label: 'Overview', href: route('reports') },
          { key: 'ar_aging', label: 'AR Aging', href: route('reports') },
          { key: 'leases', label: 'Lease Expiry', href: route('reports') },
          { key: 'tenants', label: 'Tenant Summary', href: route('reports') },
          { key: 'floor', label: 'Floor Schedule', href: route('reports.floor-schedule') },
          { key: 'builder', label: 'Custom Reports', href: route('reports.builder') },
        ]}
      />

      <div className="page-header">
        <div>
          <div className="page-title">Floor Tenant Schedule</div>
          <div className="page-sub">Every billing period by floor: rent, service charge, VAT, WHT, what was paid and what is still owed. Amounts are in TZS.</div>
        </div>
        <div className="actions">
          <button className="btn-secondary" onClick={() => exportAs('csv')}>Export CSV</button>
          <button className="btn-secondary" onClick={() => exportAs('pdf')}>Export PDF</button>
        </div>
      </div>

      <div className="card" style={{ padding: 16, marginBottom: 16 }}>
        <div className="form-row" style={{ marginBottom: 0, alignItems: 'flex-end' }}>
          {canPickProperty && (
            <div className="form-group">
              <label className="form-label">Property</label>
              <select className="form-input form-select" value={form.property_id ?? ''} onChange={(e) => set('property_id', e.target.value)}>
                {properties.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
              </select>
            </div>
          )}
          <div className="form-group">
            <label className="form-label">Floor</label>
            <select className="form-input form-select" value={form.floor ?? ''} onChange={(e) => set('floor', e.target.value)}>
              <option value="">All floors</option>
              {floors.map((f) => <option key={f.id} value={f.id}>{f.label}</option>)}
            </select>
          </div>
          <div className="form-group">
            <label className="form-label">Billing periods from</label>
            <input className="form-input" type="date" value={form.from} onChange={(e) => set('from', e.target.value)} />
          </div>
          <div className="form-group">
            <label className="form-label">to</label>
            <input className="form-input" type="date" value={form.to} onChange={(e) => set('to', e.target.value)} />
          </div>
          <div className="form-group" style={{ flex: '0 0 auto' }}>
            <button className="btn-primary" onClick={apply}>Show report</button>
          </div>
        </div>
      </div>

      {report.sections.length === 0 && (
        <div className="card" style={{ padding: 40, textAlign: 'center', color: 'var(--text-muted)' }}>No billing periods fall in the selected range.</div>
      )}

      {report.sections.map((section) => (
        <div className="card fs-card" key={section.floor}>
          <div className="fs-floor-title">{section.label} tenants</div>
          <div className="fs-scroll">
            <table className="fs-table">
              <thead>
                <tr>
                  <th>SN</th>
                  {COLUMNS.map(([key, label]) => <th key={key}>{label}</th>)}
                </tr>
              </thead>
              <tbody>
                {section.rows.map((row) => (
                  <tr key={row.installment_id} className={row.first_of_lease ? 'fs-first' : ''}>
                    <td className="c">{row.first_of_lease ? row.sn : ''}</td>
                    {COLUMNS.map(([key, , kind]) => {
                      if (key === 'tenant') return <td key={key} className="fs-tenant">{row.first_of_lease ? row.tenant : ''}</td>;
                      if (kind === 'money') return <td key={key} className={`num ${key === 'balance' && row.balance > 0 ? 'owing' : ''}`}>{money(row[key])}</td>;
                      if (kind === 'remark') return <td key={key} className="fs-remark-cell"><RemarkCell row={row} /></td>;
                      return <td key={key} className={kind === 'center' ? 'c' : ''}>{row[key] ?? ''}</td>;
                    })}
                  </tr>
                ))}
                <tr className="fs-total">
                  <td></td>
                  {COLUMNS.map(([key, , kind]) => (
                    <td key={key} className="num">{kind === 'money' ? money(section.totals[key]) : (key === 'tenant' ? 'Total' : '')}</td>
                  ))}
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      ))}

      {report.sections.length > 1 && (
        <div className="card fs-card">
          <div className="fs-floor-title">All floors</div>
          <div className="fs-scroll">
            <table className="fs-table">
              <tbody>
                <tr className="fs-total">
                  <td></td>
                  {COLUMNS.map(([key, , kind]) => (
                    <td key={key} className="num">{kind === 'money' ? money(report.grand[key]) : (key === 'tenant' ? 'Grand total' : '')}</td>
                  ))}
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      )}
    </AppLayout>
  );
}
