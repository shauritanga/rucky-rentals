import { useCallback, useEffect, useRef, useState } from 'react';

const fmtTzs = (v) => {
  const n = Number(v ?? 0);
  if (Math.abs(n) >= 1_000_000) return `TZS ${(n / 1_000_000).toFixed(2)}M`;
  if (Math.abs(n) >= 1_000)    return `TZS ${(n / 1_000).toFixed(1)}K`;
  return `TZS ${n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
};

const PERIOD_OPTIONS = [
  { value: 'this_month',   label: 'This Month' },
  { value: 'last_month',   label: 'Last Month' },
  { value: 'this_quarter', label: 'This Quarter' },
  { value: 'last_quarter', label: 'Last Quarter' },
  { value: 'this_year',    label: 'This Year' },
  { value: 'custom',       label: 'Custom Range' },
];

function WaterfallRow({ label, value, color, indent, bold, note }) {
  const isDeduction = value < 0 || (color === 'red' || color === 'amber');
  const displayAmt  = isDeduction && value > 0 ? -value : value;
  return (
    <div style={{
      display: 'flex',
      alignItems: 'flex-start',
      padding: '8px 0',
      borderBottom: bold ? '2px solid var(--border)' : '1px solid var(--border-dim)',
    }}>
      <div style={{ flex: 1, paddingLeft: indent ? 20 : 0 }}>
        <div style={{ fontSize: 13.5, fontWeight: bold ? 600 : 400, color: 'var(--text)' }}>{label}</div>
        {note && <div style={{ fontSize: 11.5, color: 'var(--text-muted)', marginTop: 2 }}>{note}</div>}
      </div>
      <div style={{
        fontSize: bold ? 15 : 13.5,
        fontWeight: bold ? 700 : 500,
        color: color === 'green' ? 'var(--green)'
             : color === 'red'   ? 'var(--red)'
             : color === 'amber' ? 'var(--amber)'
             : 'var(--text)',
        minWidth: 160,
        textAlign: 'right',
      }}>
        {isDeduction && displayAmt > 0 ? `(${fmtTzs(Math.abs(displayAmt))})` : fmtTzs(Math.abs(displayAmt))}
      </div>
    </div>
  );
}

export default function OwnerRevenuePage({ properties = [] }) {
  const [period, setPeriod]       = useState('this_month');
  const [fromDate, setFromDate]   = useState('');
  const [toDate, setToDate]       = useState('');
  const [propertyId, setPropertyId] = useState('');
  const [data, setData]           = useState(null);
  const [loading, setLoading]     = useState(false);
  const [error, setError]         = useState('');
  const abortRef = useRef(null);

  const fetchRevenue = useCallback(() => {
    if (abortRef.current) abortRef.current.abort();
    abortRef.current = new AbortController();

    const params = new URLSearchParams({ period });
    if (propertyId) params.set('property_id', propertyId);
    if (period === 'custom' && fromDate) params.set('from', fromDate);
    if (period === 'custom' && toDate)   params.set('to',   toDate);

    setLoading(true);
    setError('');

    fetch(`/superuser/revenue?${params}`, { signal: abortRef.current.signal })
      .then((r) => {
        if (!r.ok) throw new Error('Failed to load revenue data');
        return r.json();
      })
      .then((json) => { setData(json); setLoading(false); })
      .catch((e) => { if (e.name !== 'AbortError') { setError(e.message); setLoading(false); } });
  }, [period, propertyId, fromDate, toDate]);

  useEffect(() => { fetchRevenue(); }, [fetchRevenue]);

  const w = data?.waterfall ?? {};

  return (
    <>
      <div className="page-header">
        <div>
          <div className="page-title">Revenue &amp; Disbursements</div>
          <div className="page-sub">
            {data?.period
              ? `${data.period.from} → ${data.period.to}`
              : 'Owner financial waterfall'}
          </div>
        </div>
        <div className="ph-actions" style={{ gap: 8, flexWrap: 'wrap' }}>
          <select
            className="form-input form-select"
            style={{ minWidth: 160 }}
            value={period}
            onChange={(e) => setPeriod(e.target.value)}
          >
            {PERIOD_OPTIONS.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
          </select>

          {period === 'custom' && (
            <>
              <input type="date" className="form-input" style={{ minWidth: 140 }} value={fromDate} onChange={(e) => setFromDate(e.target.value)} />
              <input type="date" className="form-input" style={{ minWidth: 140 }} value={toDate}   onChange={(e) => setToDate(e.target.value)} />
            </>
          )}

          <select
            className="form-input form-select"
            style={{ minWidth: 180 }}
            value={propertyId}
            onChange={(e) => setPropertyId(e.target.value)}
          >
            <option value="">All Properties</option>
            {properties.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
          </select>

          <button className="btn btn-secondary" onClick={fetchRevenue} disabled={loading} style={{ minWidth: 90 }}>
            {loading ? 'Loading…' : 'Refresh'}
          </button>
        </div>
      </div>

      {error && (
        <div style={{ padding: '12px 16px', background: 'var(--red-dim)', color: 'var(--red)', borderRadius: 8, marginBottom: 16 }}>
          {error}
        </div>
      )}

      <div className="grid-2" style={{ gridTemplateColumns: '1fr 1fr', gap: 16, alignItems: 'start' }}>
        {/* Revenue Collected */}
        <div className="card">
          <div className="card-header">
            <div className="card-title">Revenue Collected</div>
          </div>
          <div style={{ padding: '4px 20px 16px' }}>
            <WaterfallRow
              label="Gross Rent Collected"
              value={w.gross ?? 0}
              color="green"
              bold
            />
            <WaterfallRow
              label={`WHT Withheld (${w.gross > 0 ? ((w.wht / w.gross) * 100).toFixed(1) : 0}%)`}
              value={-(w.wht ?? 0)}
              color="amber"
              indent
              note="Held by tenant · remitted to TRA · owner claims as annual tax credit"
            />
            <WaterfallRow
              label="VAT Output"
              value={-(w.vat ?? 0)}
              color="amber"
              indent
              note="Remittable to TRA by 20th of following month"
            />
            <WaterfallRow
              label="Service Charge"
              value={-(w.sc ?? 0)}
              color="blue"
              indent
              note="Covers building operations — not owner profit"
            />
            <WaterfallRow
              label="Net Rental Income"
              value={w.netRent ?? 0}
              color={w.netRent >= 0 ? 'green' : 'red'}
              bold
            />
          </div>
        </div>

        {/* Operating Expenses */}
        <div className="card">
          <div className="card-header">
            <div className="card-title">Operating Expenses</div>
          </div>
          <div style={{ padding: '4px 20px 16px' }}>
            <WaterfallRow
              label="Maintenance"
              value={-(w.maintenance ?? 0)}
              color="red"
              indent
              note="Resolved maintenance costs in period"
            />
            <WaterfallRow
              label="Fuel / Generator"
              value={-(w.fuel ?? 0)}
              color="red"
              indent
              note="Diesel and generator running costs"
            />
            <WaterfallRow
              label={`Management Fee (${data?.fee_rate ?? 0}%)`}
              value={-(w.mgmtFee ?? 0)}
              color="red"
              indent
              note="Property management service fee"
            />
            <WaterfallRow
              label="Total Expenses"
              value={-((w.maintenance ?? 0) + (w.fuel ?? 0) + (w.mgmtFee ?? 0))}
              color="red"
              bold
            />
          </div>
        </div>
      </div>

      {/* Net Owner Revenue */}
      <div className="card" style={{ marginTop: 16 }}>
        <div style={{
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center',
          padding: '20px 24px',
        }}>
          <div>
            <div style={{ fontSize: 16, fontWeight: 600, color: 'var(--text)' }}>Net Owner Revenue</div>
            <div style={{ fontSize: 12.5, color: 'var(--text-muted)', marginTop: 4 }}>
              After WHT, VAT, service charge, maintenance, fuel, and management fee
            </div>
          </div>
          <div style={{
            fontSize: 28,
            fontWeight: 700,
            color: (w.net ?? 0) >= 0 ? 'var(--green)' : 'var(--red)',
          }}>
            {fmtTzs(w.net ?? 0)}
          </div>
        </div>
      </div>

      {/* Per-Property Breakdown */}
      {(data?.properties?.length ?? 0) > 1 && (
        <div className="card" style={{ marginTop: 16 }}>
          <div className="card-header">
            <div className="card-title">Per-Property Breakdown</div>
          </div>
          <table className="data-table">
            <thead>
              <tr>
                <th>Property</th>
                <th>Manager</th>
                <th>Gross</th>
                <th>Maintenance</th>
                <th>Fuel</th>
                <th>Mgmt Fee</th>
                <th>Net</th>
              </tr>
            </thead>
            <tbody>
              {data.properties.map((p) => (
                <tr key={p.id}>
                  <td style={{ fontWeight: 600 }}>{p.name}</td>
                  <td>{p.manager ?? '—'}</td>
                  <td style={{ color: 'var(--green)', fontWeight: 500 }}>{fmtTzs(p.gross)}</td>
                  <td style={{ color: 'var(--red)' }}>{fmtTzs(p.maintenance)}</td>
                  <td style={{ color: 'var(--red)' }}>{fmtTzs(p.fuel)}</td>
                  <td style={{ color: 'var(--red)' }}>{fmtTzs(p.fee)}</td>
                  <td style={{ fontWeight: 700, color: p.net >= 0 ? 'var(--green)' : 'var(--red)' }}>{fmtTzs(p.net)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </>
  );
}
