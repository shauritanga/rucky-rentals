import { useState, useEffect } from 'react';
import { router, usePage, useRemember } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

const fmtNum = (value) => Number(value ?? 0).toLocaleString();
const fmtTZS = (value) => `TZS ${fmtNum(Math.round(Number(value ?? 0)))}`;
const fmtMonth = (value) => new Date(`${value}-02`).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
const fmtQuarter = (value) => {
    const date = new Date(`${value}T00:00:00`);

    if (Number.isNaN(date.getTime())) {
        return 'Select reading date';
    }

    return `Q${Math.floor(date.getMonth() / 3) + 1} ${date.getFullYear()}`;
};
const electricityTabs = ['overview', 'direct', 'submeter', 'generator', 'outages'];

const resolveActiveTab = (url) => {
    const value = new URL(url, 'http://localhost').searchParams.get('tab');

    return electricityTabs.includes(value) ? value : 'overview';
};

function InvoiceBadge({ status }) {
    if (!status) return <span className="badge muted">No Bill</span>;
    const badgeClass = status === 'paid' ? 'paid' : status === 'proforma' ? 'proforma' : status === 'draft' ? 'muted' : status === 'overdue' ? 'overdue' : 'unpaid';
    const label = status === 'partially_paid'
        ? 'Part Paid'
        : status.charAt(0).toUpperCase() + status.slice(1);

    return <span className={`badge ${badgeClass}`}>{label}</span>;
}

export default function Electricity({
    currentMonth,
    directReadings = [],
    submeterSales = [],
    directUnits = [],
    submeterUnits = [],
    submeterModuleReady = true,
    outages = [],
    fuelLogs = [],
    generatorSettings = {},
    submeterSettings = {},
    runtimeHistory = [],
    directReadingPrompt = {},
}) {
    const { props, url } = usePage();
    const flash = props.flash ?? {};
    const errors = props.errors ?? {};
    const floorOptions = props.floorOptions ?? [];
    const [toast, setToast] = useState({ msg: '', type: '' });
    const [tab, setTab] = useRemember(resolveActiveTab(url), 'electricity.active-tab');
    const [savingReading, setSavingReading] = useState(false);

    useEffect(() => {
        const show = (msg, type, ms) => {
            setToast({ msg, type });
            window.setTimeout(() => setToast({ msg: '', type: '' }), ms);
        };
        if (flash.success) show(flash.success, 'success', 3500);
        if (flash.warning) show(flash.warning, 'warning', 5000);
        if (flash.error)   show(flash.error,   'error',   4000);
    }, [flash.success, flash.warning, flash.error]);
    const [generatorDataTab, setGeneratorDataTab] = useState('fuel-logs');
    const today = new Date().toISOString().slice(0, 10);
    const generatorRate = Number(generatorSettings.generator_rate_per_kwh ?? 1400);
    const generatorVat = Number(generatorSettings.generator_vat_percent ?? 18);
    const submeterRate = Number(submeterSettings.unit_price ?? 500);
    const submeterVat  = Number(submeterSettings.vat_percent ?? 18);

    const [readingForm, setReadingForm] = useState({
        unit_id: directUnits[0]?.id ? String(directUnits[0].id) : '',
        reading_date: today,
        prev_reading: '',
        curr_reading: '',
    });
    const [saleForm, setSaleForm] = useState({
        unit_id: submeterUnits[0]?.id ? String(submeterUnits[0].id) : '',
        sale_date: today,
        amount_paid: '',
        unit_price: submeterRate,
        notes: '',
    });
    const [generatorForm, setGeneratorForm] = useState({
        generator_rate_per_kwh: generatorRate,
        generator_vat_percent: generatorVat,
        diesel_price: generatorSettings.diesel_price ?? 185,
        l_per_hr: generatorSettings.l_per_hr ?? 6,
        output_kw: generatorSettings.output_kw ?? 36,
        maint_levy: generatorSettings.maint_levy ?? 50,
        tank_size: generatorSettings.tank_size ?? 200,
    });
    const [submeterPricingForm, setSubmeterPricingForm] = useState({ unit_price: submeterRate, vat_percent: submeterVat });
    const [fuelForm, setFuelForm] = useState({
        log_date: today,
        litres: '',
        price_per_litre: generatorSettings.diesel_price ?? 185,
        supplier: '',
        level_after: '',
    });
    const [outageForm, setOutageForm] = useState({
        outage_date: today,
        start_time: '',
        end_time: '',
        type: 'minor',
        floors_affected: 'All floors',
        generator_activated: false,
        fuel_used: '',
        notes: '',
    });

    const prevReading = Number(readingForm.prev_reading || 0);
    const currReading = Number(readingForm.curr_reading || 0);
    const readingGenKwh = Math.max(currReading - prevReading, 0);
    const readingBase = readingGenKwh * generatorRate;
    const readingVat = readingBase * (generatorVat / 100);
    const readingTotal = readingBase + readingVat;
    const saleAmount = Number(saleForm.amount_paid || 0);
    const saleUnitPrice = Number(saleForm.unit_price || 0);
    const computedUnits = saleUnitPrice > 0 ? saleAmount / saleUnitPrice : 0;
    const saleNetAmount = saleAmount > 0 ? Math.round(saleAmount / (1 + submeterVat / 100)) : 0;
    const saleVatAmount = saleAmount > 0 ? saleAmount - saleNetAmount : 0;

    const directDraftCount = directReadings.filter((reading) => reading.invoice_status === 'proforma').length;
    const submeterDraftCount = submeterSales.filter((sale) => sale.invoice_status === 'draft').length;
    const directTotal = directReadings.reduce((sum, reading) => sum + Number(reading.generator_total_amount ?? 0), 0);
    const submeterTotal = submeterSales.reduce((sum, sale) => sum + Number(sale.amount ?? 0), 0);
    const fuelTotal = fuelLogs.reduce((sum, log) => sum + Number(log.total_cost ?? 0), 0);
    const totalGenKwh = directReadings.reduce((sum, reading) => sum + Number(reading.gen_kwh ?? 0), 0);
    const directReadingError = errors.direct_reading ?? null;
    const submeterSaleError = errors.submeter_sale ?? null;
    const flashError = flash.error ?? null;
    const flashSuccess = flash.success ?? null;
    const floorSelectOptions = ['All floors', ...floorOptions.filter((label) => label !== 'All floors')];
    const selectedDirectUnit = directUnits.find((unit) => String(unit.id) === String(readingForm.unit_id));
    const quarterlyMissingUnits = directReadingPrompt.missing_units ?? [];
    const quarterlyPromptComplete = (directReadingPrompt.total_units ?? 0) > 0 && quarterlyMissingUnits.length === 0;

    const setActiveTab = (nextTab) => {
        setTab(nextTab);

        if (typeof window === 'undefined') {
            return;
        }

        const nextUrl = new URL(window.location.href);

        if (nextTab === 'overview') {
            nextUrl.searchParams.delete('tab');
        } else {
            nextUrl.searchParams.set('tab', nextTab);
        }

        window.history.replaceState(window.history.state, '', `${nextUrl.pathname}${nextUrl.search}${nextUrl.hash}`);
    };

    useEffect(() => {
        if (!readingForm.unit_id) {
            return;
        }

        const selectedUnitId = Number(readingForm.unit_id);
        const existingReading = directReadings.find((reading) =>
            Number(reading.unit_id) === selectedUnitId && reading.reading_date === readingForm.reading_date
        );

        if (existingReading) {
            const nextPrev = String(existingReading.prev_reading ?? '');
            const nextCurr = String(existingReading.curr_reading ?? '');

            setReadingForm((form) => (
                form.prev_reading === nextPrev && form.curr_reading === nextCurr
                    ? form
                    : { ...form, prev_reading: nextPrev, curr_reading: nextCurr }
            ));

            return;
        }

        const latestReading = selectedDirectUnit?.latest_reading ?? null;
        const nextPrev = latestReading ? String(latestReading.curr_reading ?? '') : '';
        const nextCurr = latestReading?.reading_date === readingForm.reading_date
            ? String(latestReading.curr_reading ?? '')
            : '';

        setReadingForm((form) => (
            form.prev_reading === nextPrev && form.curr_reading === nextCurr
                ? form
                : { ...form, prev_reading: nextPrev, curr_reading: nextCurr }
        ));
    }, [readingForm.unit_id, readingForm.reading_date, directReadings, selectedDirectUnit]);

    const post = (url, payload, onSuccess) => {
        router.post(url, payload, {
            preserveState: true,
            preserveScroll: true,
            onSuccess,
            onError: (errs) => {
                const first = Object.values(errs)[0];
                setToast({ msg: first || 'Something went wrong. Please try again.', type: 'error' });
                window.setTimeout(() => setToast({ msg: '', type: '' }), 5000);
            },
        });
    };

    return (
        <AppLayout title="Electricity">
            {toast.msg && (
                <div style={{
                    position: 'fixed', bottom: 24, right: 24, zIndex: 9999,
                    background: toast.type === 'success' ? 'var(--green)' : toast.type === 'warning' ? '#f59e0b' : '#e53935',
                    color: '#fff', borderRadius: 10, padding: '12px 20px',
                    fontSize: 13.5, fontWeight: 500, boxShadow: '0 4px 20px rgba(0,0,0,.25)',
                    maxWidth: 360,
                }}>
                    {toast.msg}
                </div>
            )}
            <div className="acc-layout">
                <aside className="acc-sidebar">
                    {[
                        ['overview', 'Overview'],
                        ['direct', 'Generator Billing'],
                        ['submeter', 'Submeter Sales'],
                        ['generator', 'Generator & Fuel'],
                        ['outages', 'Outages'],
                    ].map(([id, label]) => (
                        <div
                            key={id}
                            className={`acc-nav-item ${tab === id ? 'active' : ''}`}
                            onClick={() => setActiveTab(id)}
                        >
                            {label}
                        </div>
                    ))}
                </aside>

                <main className="acc-content" style={{ overflowY: 'auto', padding: 26 }}>
                    {tab === 'overview' && (
                        <OverviewTab
                            currentMonth={currentMonth}
                            directUnits={directUnits}
                            submeterUnits={submeterUnits}
                            directDraftCount={directDraftCount}
                            submeterDraftCount={submeterDraftCount}
                            directTotal={directTotal}
                            submeterTotal={submeterTotal}
                            fuelTotal={fuelTotal}
                            totalGenKwh={totalGenKwh}
                            outages={outages}
                        />
                    )}

                    {tab === 'direct' && (
                        <div>
                            {flashSuccess && (
                                <div className="info-box" style={{ marginBottom: 16, border: '1px solid rgba(34, 197, 94, 0.35)', background: 'var(--green-dim)' }}>
                                    <div className="info-box-text" style={{ color: 'var(--green)' }}>
                                        {flashSuccess}
                                    </div>
                                </div>
                            )}
                            {(directReadingError || flashError) && (
                                <div className="info-box" style={{ marginBottom: 16, border: '1px solid rgba(239, 68, 68, 0.35)', background: 'var(--red-dim)' }}>
                                    <div className="info-box-text" style={{ color: 'var(--red)' }}>
                                        {directReadingError ?? flashError}
                                    </div>
                                </div>
                            )}
                            <div className="page-header">
                                <div>
                                    <div className="page-title">Direct Generator Billing</div>
                                    <div className="page-sub">
                                        Direct units are prompted quarterly and billed only for generator use at {fmtTZS(generatorRate)} per kWh plus {generatorVat}% VAT.
                                    </div>
                                </div>
                                <div className="actions">
                                    <button className="btn-primary" onClick={() => post('/electricity/invoices/issue', { kind: 'direct' })}>
                                        Issue Generator Proforma
                                    </button>
                                </div>
                            </div>

                            <div
                                className="info-box"
                                style={{
                                    marginBottom: 16,
                                    borderColor: quarterlyMissingUnits.length ? 'var(--amber)' : 'rgba(34, 197, 94, 0.35)',
                                    background: quarterlyMissingUnits.length ? 'var(--amber-dim)' : 'var(--green-dim)',
                                }}
                            >
                                <div className="info-box-text">
                                    <strong>{directReadingPrompt.label ?? 'Current quarter'} electricity reading prompt:</strong>{' '}
                                    {quarterlyPromptComplete
                                        ? 'all direct units have a reading for this quarter.'
                                        : `${quarterlyMissingUnits.length} of ${directReadingPrompt.total_units ?? directUnits.length} direct unit(s) still need a quarterly reading.`}
                                    {quarterlyMissingUnits.length > 0 && (
                                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, marginTop: 10 }}>
                                            {quarterlyMissingUnits.map((unit) => (
                                                <span key={unit.id} className="badge muted">
                                                    {unit.unit_number}{unit.tenant_name ? ` - ${unit.tenant_name}` : ''}
                                                </span>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </div>

                            <div className="grid-2-equal" style={{ marginBottom: 16 }}>
                                <div className="card">
                                    <div className="card-header"><div className="card-title">New Reading</div></div>
                                    <div style={{ padding: '16px 18px' }}>
                                        <div className="form-row">
                                            <div className="form-group">
                                                <label className="form-label">Unit</label>
                                                <select className="form-input form-select" value={readingForm.unit_id} onChange={(e) => setReadingForm((form) => ({ ...form, unit_id: e.target.value }))}>
                                                    <option value="">Select direct unit...</option>
                                                    {directUnits.map((unit) => (
                                                        <option key={unit.id} value={unit.id}>{unit.unit_number}{unit.tenant_name ? ` - ${unit.tenant_name}` : ''}</option>
                                                    ))}
                                                </select>
                                            </div>
                                            <div className="form-group">
                                                <label className="form-label">Billing Quarter</label>
                                                <div className="form-input" style={{ display: 'flex', alignItems: 'center' }}>
                                                    {fmtQuarter(readingForm.reading_date)}
                                                </div>
                                            </div>
                                        </div>
                                        <div className="form-row">
                                            <div className="form-group">
                                                <label className="form-label">Reading Date</label>
                                                <input className="form-input" type="date" value={readingForm.reading_date} onChange={(e) => setReadingForm((form) => ({ ...form, reading_date: e.target.value }))} />
                                            </div>
                                            <div className="form-group">
                                                <label className="form-label">Units Used</label>
                                                <div className="form-input" style={{ display: 'flex', alignItems: 'center', color: 'var(--accent)', fontWeight: 700 }}>
                                                    {fmtNum(readingGenKwh)}
                                                </div>
                                            </div>
                                        </div>
                                        <div className="form-row">
                                            <div className="form-group">
                                                <label className="form-label">Previous Reading</label>
                                                <input className="form-input" type="number" min="0" step="0.01" value={readingForm.prev_reading} onChange={(e) => setReadingForm((form) => ({ ...form, prev_reading: e.target.value }))} />
                                            </div>
                                            <div className="form-group">
                                                <label className="form-label">Current Reading</label>
                                                <input className="form-input" type="number" min="0" step="0.01" value={readingForm.curr_reading} onChange={(e) => setReadingForm((form) => ({ ...form, curr_reading: e.target.value }))} />
                                            </div>
                                        </div>
                                        <div style={{ background: 'var(--bg-elevated)', borderRadius: 10, padding: '12px 14px', marginBottom: 12 }}>
                                            <div className="bill-summary-row"><span>Units billed from meter</span><span>{fmtNum(readingGenKwh)} units</span></div>
                                            <div className="bill-summary-row"><span>Generator base</span><span>{fmtTZS(readingBase)}</span></div>
                                            <div className="bill-summary-row"><span>VAT</span><span>{fmtTZS(readingVat)}</span></div>
                                            <div className="bill-summary-row"><span>Total generator bill</span><span style={{ color: 'var(--green)' }}>{fmtTZS(readingTotal)}</span></div>
                                        </div>
                                        <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
                                            <button
                                                className="btn-primary"
                                                disabled={savingReading}
                                                onClick={() => {
                                                    setActiveTab('direct');
                                                    setSavingReading(true);
                                                    router.post('/electricity/readings', readingForm, {
                                                        preserveState: true,
                                                        preserveScroll: true,
                                                        onFinish: () => setSavingReading(false),
                                                    });
                                                }}
                                            >
                                                {savingReading ? 'Saving...' : 'Save Reading'}
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div className="card">
                                    <div className="card-header"><div className="card-title">Billing Rules</div></div>
                                    <div style={{ padding: '16px 18px', display: 'grid', gap: 12 }}>
                                        <div className="info-box">
                                            <div className="info-box-text">The system calculates billable units automatically from <strong>current reading minus previous reading</strong>. No manual generator entry is required.</div>
                                        </div>
                                        <div className="info-box">
                                            <div className="info-box-text">Saving a direct reading creates or updates one proforma per <strong>unit and reading date</strong>, only when the calculated units are greater than zero.</div>
                                        </div>
                                        <div className="info-box">
                                            <div className="info-box-text">Direct proformas this quarter: <strong>{directDraftCount}</strong></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div className="card">
                                <div className="card-header"><div className="card-title">Meter Readings</div></div>
                                <table className="ledger-table direct-readings-table">
                                    <colgroup>
                                        <col style={{ width: 112 }} />
                                        <col style={{ width: 144 }} />
                                        <col />
                                        <col style={{ width: 148 }} />
                                        <col style={{ width: 156 }} />
                                        <col style={{ width: 144 }} />
                                        <col style={{ width: 132 }} />
                                        <col style={{ width: 148 }} />
                                        <col style={{ width: 140 }} />
                                        <col style={{ width: 164 }} />
                                    </colgroup>
                                    <thead>
                                        <tr>
                                            <th>Unit</th>
                                            <th>Date</th>
                                            <th>Tenant</th>
                                            <th className="num">Consumption</th>
                                            <th className="num">Billable Units</th>
                                            <th className="num">Base</th>
                                            <th className="num">VAT</th>
                                            <th className="num">Total</th>
                                            <th>Status</th>
                                            <th>Invoice</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {directReadings.map((reading) => (
                                            <tr key={reading.id}>
                                                <td style={{ fontWeight: 700, color: 'var(--accent)' }}>{reading.unit_number}</td>
                                                <td>{reading.reading_date}</td>
                                                <td>{reading.tenant_name ?? 'No active lease'}</td>
                                                <td className="num">{fmtNum(reading.consumption)} kWh</td>
                                                <td className="num">{fmtNum(reading.gen_kwh)} kWh</td>
                                                <td className="num">{fmtTZS(reading.generator_base_amount)}</td>
                                                <td className="num">{fmtTZS(reading.generator_vat_amount)}</td>
                                                <td className="num" style={{ color: 'var(--green)', fontWeight: 700 }}>{fmtTZS(reading.generator_total_amount)}</td>
                                                <td><InvoiceBadge status={reading.invoice_status} /></td>
                                                <td>{reading.invoice_number ?? 'Pending'}</td>
                                            </tr>
                                        ))}
                                        {directReadings.length === 0 && <tr><td colSpan={10} style={{ textAlign: 'center', padding: 28, color: 'var(--text-muted)' }}>No direct readings recorded for {directReadingPrompt.label ?? fmtMonth(currentMonth)}</td></tr>}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}

                    {tab === 'submeter' && (
                        <div>
                            {(submeterSaleError || flashError) && (
                                <div className="info-box" style={{ marginBottom: 16, border: '1px solid rgba(239, 68, 68, 0.35)', background: 'var(--red-dim)' }}>
                                    <div className="info-box-text" style={{ color: 'var(--red)' }}>
                                        {submeterSaleError ?? flashError}
                                    </div>
                                </div>
                            )}
                            <div className="page-header">
                                <div>
                                    <div className="page-title">Submeter Sales</div>
                                    <div className="page-sub">Submeter tenants buy electricity on demand. These sales never attract generator billing.</div>
                                </div>
                                <div className="actions">
                                    <button className="btn-primary" onClick={() => post('/electricity/invoices/issue', { kind: 'submeter' })}>
                                        Issue Submeter Invoices
                                    </button>
                                </div>
                            </div>

                            {!submeterModuleReady && (
                                <div className="info-box" style={{ marginBottom: 16, borderColor: 'var(--amber)', background: 'var(--amber-dim)' }}>
                                    <div className="info-box-text">
                                        Submeter sales are not available yet because the `electricity_sales` table has not been migrated. Run `php artisan migrate`.
                                    </div>
                                </div>
                            )}

                            <div className="grid-2-equal" style={{ marginBottom: 16 }}>
                                <div className="card">
                                    <div className="card-header"><div className="card-title">Sale Entry</div></div>
                                    <div style={{ padding: '16px 18px' }}>
                                        <div className="form-row">
                                            <div className="form-group">
                                                <label className="form-label">Unit</label>
                                                <select className="form-input form-select" value={saleForm.unit_id} onChange={(e) => setSaleForm((form) => ({ ...form, unit_id: e.target.value }))}>
                                                    <option value="">Select submeter unit...</option>
                                                    {submeterUnits.map((unit) => (
                                                        <option key={unit.id} value={unit.id}>{unit.unit_number}{unit.tenant_name ? ` - ${unit.tenant_name}` : ''}</option>
                                                    ))}
                                                </select>
                                            </div>
                                            <div className="form-group">
                                                <label className="form-label">Sale Date</label>
                                                <input className="form-input" type="date" value={saleForm.sale_date} onChange={(e) => setSaleForm((form) => ({ ...form, sale_date: e.target.value }))} />
                                            </div>
                                        </div>
                                        <div className="form-row">
                                            <div className="form-group">
                                                <label className="form-label">Amount Paid</label>
                                                <input className="form-input" type="number" min="0" step="0.01" value={saleForm.amount_paid} onChange={(e) => setSaleForm((form) => ({ ...form, amount_paid: e.target.value }))} />
                                            </div>
                                            <div className="form-group">
                                                <label className="form-label">Unit Price</label>
                                                <input className="form-input" type="number" min="0" step="0.01" value={saleForm.unit_price} onChange={(e) => setSaleForm((form) => ({ ...form, unit_price: e.target.value }))} />
                                            </div>
                                        </div>
                                        <div className="form-group" style={{ marginBottom: 12 }}>
                                            <label className="form-label">Units to Issue</label>
                                            <div style={{ background: 'var(--bg-elevated)', borderRadius: 8, padding: '8px 12px', fontSize: 14, fontWeight: 700, color: 'var(--accent)' }}>
                                                {saleAmount > 0 && saleUnitPrice > 0 ? `${computedUnits.toFixed(2)} units` : 'Enter amount and price'}
                                            </div>
                                            <div style={{ fontSize: 12, color: 'var(--text-muted)', marginTop: 6 }}>
                                                The system calculates units automatically from the money received. Decimal units are allowed.
                                            </div>
                                        </div>
                                        <div className="form-group" style={{ marginBottom: 12 }}>
                                            <label className="form-label">Notes</label>
                                            <textarea className="form-input" rows={2} value={saleForm.notes} onChange={(e) => setSaleForm((form) => ({ ...form, notes: e.target.value }))} style={{ resize: 'vertical' }} />
                                        </div>
                                        <div style={{ background: 'var(--bg-elevated)', borderRadius: 10, padding: '12px 14px', marginBottom: 12 }}>
                                            <div className="bill-summary-row"><span>Amount received (gross)</span><span style={{ color: 'var(--green)' }}>{fmtTZS(saleAmount)}</span></div>
                                            <div className="bill-summary-row"><span>Net electricity charge</span><span>{fmtTZS(saleNetAmount)}</span></div>
                                            <div className="bill-summary-row"><span>VAT ({submeterVat}% inclusive)</span><span style={{ color: 'var(--text-muted)' }}>{fmtTZS(saleVatAmount)}</span></div>
                                            <div className="bill-summary-row" style={{ borderTop: '1px solid var(--border)', marginTop: 6, paddingTop: 6 }}><span>Computed units</span><span>{saleAmount > 0 && saleUnitPrice > 0 ? computedUnits.toFixed(2) : '0.00'} units</span></div>
                                        </div>
                                        <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
                                            <button className="btn-primary" disabled={!submeterModuleReady || saleAmount <= 0 || saleUnitPrice <= 0} onClick={() => post('/electricity/sales', saleForm, () => {
                                                setSaleForm((f) => ({ ...f, amount_paid: '', notes: '' }));
                                            })}>
                                                Record Sale
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div className="card">
                                    <div className="card-header"><div className="card-title">Submeter Pricing</div></div>
                                    <div style={{ padding: '16px 18px' }}>
                                        <div className="form-group" style={{ marginBottom: 12 }}>
                                            <label className="form-label">Default Unit Price (VAT-inclusive)</label>
                                            <input className="form-input" type="number" min="0" step="0.01" value={submeterPricingForm.unit_price} onChange={(e) => setSubmeterPricingForm((f) => ({ ...f, unit_price: e.target.value }))} />
                                        </div>
                                        <div className="form-group" style={{ marginBottom: 12 }}>
                                            <label className="form-label">VAT Rate (%)</label>
                                            <input className="form-input" type="number" min="0" max="100" step="0.01" value={submeterPricingForm.vat_percent} onChange={(e) => setSubmeterPricingForm((f) => ({ ...f, vat_percent: e.target.value }))} />
                                        </div>
                                        <div className="info-box" style={{ marginBottom: 12 }}>
                                            <div className="info-box-text">
                                                Unit price <strong>{fmtTZS(submeterPricingForm.unit_price)}</strong> includes {submeterPricingForm.vat_percent}% VAT.
                                                Net per unit: <strong>{fmtTZS(Math.round(Number(submeterPricingForm.unit_price) / (1 + Number(submeterPricingForm.vat_percent) / 100)))}</strong>,
                                                VAT: <strong>{fmtTZS(Math.round(Number(submeterPricingForm.unit_price) - Number(submeterPricingForm.unit_price) / (1 + Number(submeterPricingForm.vat_percent) / 100)))}</strong>.
                                            </div>
                                        </div>
                                        <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
                                            <button className="btn-primary" disabled={!submeterModuleReady} onClick={() => post('/electricity/settings/submeter', submeterPricingForm)}>
                                                Save Pricing
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div className="card">
                                <div className="card-header"><div className="card-title">Recent Submeter Sales</div></div>
                                <table className="ledger-table electricity-sales-table">
                                    <colgroup>
                                        <col style={{ width: 144 }} />
                                        <col style={{ width: 96 }} />
                                        <col />
                                        <col style={{ width: 144 }} />
                                        <col style={{ width: 152 }} />
                                        <col style={{ width: 176 }} />
                                        <col style={{ width: 140 }} />
                                        <col style={{ width: 160 }} />
                                    </colgroup>
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Unit</th>
                                            <th>Tenant</th>
                                            <th className="num">Units Sold</th>
                                            <th className="num">Unit Price</th>
                                            <th className="num">Amount</th>
                                            <th>Status</th>
                                            <th>Invoice</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {submeterSales.map((sale) => (
                                            <tr key={sale.id}>
                                                <td>{sale.sale_date}</td>
                                                <td style={{ fontWeight: 700, color: 'var(--accent)' }}>{sale.unit_number}</td>
                                                <td>{sale.tenant_name ?? 'No active lease'}</td>
                                                <td className="num">{fmtNum(sale.units_sold)}</td>
                                                <td className="num">{fmtTZS(sale.unit_price)}</td>
                                                <td className="num" style={{ color: 'var(--green)', fontWeight: 700 }}>{fmtTZS(sale.amount)}</td>
                                                <td><InvoiceBadge status={sale.invoice_status} /></td>
                                                <td>{sale.invoice_number ?? 'Pending'}</td>
                                            </tr>
                                        ))}
                                        {submeterSales.length === 0 && <tr><td colSpan={8} style={{ textAlign: 'center', padding: 28, color: 'var(--text-muted)' }}>No submeter sales recorded yet.</td></tr>}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}

                    {tab === 'generator' && (
                        <div>
                            <div className="page-header">
                                <div>
                                    <div className="page-title">Generator & Fuel</div>
                                    <div className="page-sub">Operational settings and runtime tracking for backup power.</div>
                                </div>
                            </div>

                            <div className="grid-2-equal" style={{ marginBottom: 16 }}>
                                <div className="card">
                                    <div className="card-header"><div className="card-title">Generator Billing Settings</div></div>
                                    <div style={{ padding: '16px 18px' }}>
                                        <div className="form-row">
                                            <div className="form-group">
                                                <label className="form-label">Generator Rate per kWh</label>
                                                <input className="form-input" type="number" min="0" step="0.01" value={generatorForm.generator_rate_per_kwh} onChange={(e) => setGeneratorForm((form) => ({ ...form, generator_rate_per_kwh: e.target.value }))} />
                                            </div>
                                            <div className="form-group">
                                                <label className="form-label">Generator VAT %</label>
                                                <input className="form-input" type="number" min="0" step="0.01" value={generatorForm.generator_vat_percent} onChange={(e) => setGeneratorForm((form) => ({ ...form, generator_vat_percent: e.target.value }))} />
                                            </div>
                                        </div>
                                        <div className="form-row">
                                            <div className="form-group">
                                                <label className="form-label">Diesel Price</label>
                                                <input className="form-input" type="number" min="0" step="0.01" value={generatorForm.diesel_price} onChange={(e) => setGeneratorForm((form) => ({ ...form, diesel_price: e.target.value }))} />
                                            </div>
                                            <div className="form-group">
                                                <label className="form-label">Litres per Hour</label>
                                                <input className="form-input" type="number" min="0" step="0.01" value={generatorForm.l_per_hr} onChange={(e) => setGeneratorForm((form) => ({ ...form, l_per_hr: e.target.value }))} />
                                            </div>
                                        </div>
                                        <div className="form-row">
                                            <div className="form-group">
                                                <label className="form-label">Output kW</label>
                                                <input className="form-input" type="number" min="0" step="0.01" value={generatorForm.output_kw} onChange={(e) => setGeneratorForm((form) => ({ ...form, output_kw: e.target.value }))} />
                                            </div>
                                            <div className="form-group">
                                                <label className="form-label">Maintenance Levy</label>
                                                <input className="form-input" type="number" min="0" step="0.01" value={generatorForm.maint_levy} onChange={(e) => setGeneratorForm((form) => ({ ...form, maint_levy: e.target.value }))} />
                                            </div>
                                        </div>
                                        <div className="form-group" style={{ marginBottom: 12 }}>
                                            <label className="form-label">Tank Size</label>
                                            <input className="form-input" type="number" min="0" step="0.01" value={generatorForm.tank_size} onChange={(e) => setGeneratorForm((form) => ({ ...form, tank_size: e.target.value }))} />
                                        </div>
                                        <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
                                            <button className="btn-primary" onClick={() => post('/electricity/settings/generator', generatorForm)}>
                                                Save Generator Settings
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div className="card">
                                    <div className="card-header"><div className="card-title">Fuel Log</div></div>
                                    <div style={{ padding: '16px 18px' }}>
                                        <div className="form-row">
                                            <div className="form-group">
                                                <label className="form-label">Date</label>
                                                <input className="form-input" type="date" value={fuelForm.log_date} onChange={(e) => setFuelForm((form) => ({ ...form, log_date: e.target.value }))} />
                                            </div>
                                            <div className="form-group">
                                                <label className="form-label">Litres Added</label>
                                                <input className="form-input" type="number" min="0" step="0.01" value={fuelForm.litres} onChange={(e) => setFuelForm((form) => ({ ...form, litres: e.target.value }))} />
                                            </div>
                                        </div>
                                        <div className="form-row">
                                            <div className="form-group">
                                                <label className="form-label">Price per Litre</label>
                                                <input className="form-input" type="number" min="0" step="0.01" value={fuelForm.price_per_litre} onChange={(e) => setFuelForm((form) => ({ ...form, price_per_litre: e.target.value }))} />
                                            </div>
                                            <div className="form-group">
                                                <label className="form-label">Tank Level After %</label>
                                                <input className="form-input" type="number" min="0" max="100" step="1" value={fuelForm.level_after} onChange={(e) => setFuelForm((form) => ({ ...form, level_after: e.target.value }))} />
                                            </div>
                                        </div>
                                        <div className="form-group" style={{ marginBottom: 12 }}>
                                            <label className="form-label">Supplier</label>
                                            <input className="form-input" type="text" value={fuelForm.supplier} onChange={(e) => setFuelForm((form) => ({ ...form, supplier: e.target.value }))} />
                                        </div>
                                        <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
                                            <button className="btn-primary" onClick={() => post('/electricity/fuel', fuelForm)}>
                                                Save Fuel Log
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div className="card">
                                <div className="card-header"><div className="card-title">Operations History</div></div>
                                <div style={{ padding: '16px 18px 0' }}>
                                    <div className="team-tabs" style={{ marginBottom: 0 }}>
                                        <button className={`team-tab ${generatorDataTab === 'fuel-logs' ? 'active' : ''}`} onClick={() => setGeneratorDataTab('fuel-logs')}>
                                            Fuel Logs
                                            <span className="team-tab-count">{fuelLogs.length}</span>
                                        </button>
                                        <button className={`team-tab ${generatorDataTab === 'runtime-history' ? 'active' : ''}`} onClick={() => setGeneratorDataTab('runtime-history')}>
                                            Runtime History
                                            <span className="team-tab-count">{runtimeHistory.length}</span>
                                        </button>
                                    </div>
                                </div>

                                {generatorDataTab === 'fuel-logs' && (
                                    <table className="ledger-table">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th className="num">Litres</th>
                                                <th className="num">Price/L</th>
                                                <th className="num">Total</th>
                                                <th>Supplier</th>
                                                <th>Level After</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {fuelLogs.map((log) => (
                                                <tr key={log.id}>
                                                    <td>{log.log_date}</td>
                                                    <td className="num">{fmtNum(log.litres)} L</td>
                                                    <td className="num">{fmtTZS(log.price_per_litre)}</td>
                                                    <td className="num">{fmtTZS(log.total_cost)}</td>
                                                    <td>{log.supplier ?? ' - '}</td>
                                                    <td>{log.level_after}%</td>
                                                </tr>
                                            ))}
                                            {fuelLogs.length === 0 && <tr><td colSpan={6} style={{ textAlign: 'center', padding: 28, color: 'var(--text-muted)' }}>No fuel logs recorded yet.</td></tr>}
                                        </tbody>
                                    </table>
                                )}

                                {generatorDataTab === 'runtime-history' && (
                                    <table className="ledger-table">
                                        <thead>
                                            <tr>
                                                <th>Month</th>
                                                <th className="num">Outages</th>
                                                <th className="num">Runtime</th>
                                                <th className="num">Fuel Used</th>
                                                <th className="num">Fuel Cost</th>
                                                <th className="num">Generator kWh</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {runtimeHistory.map((row) => (
                                                <tr key={row.month}>
                                                    <td>{fmtMonth(row.month)}</td>
                                                    <td className="num">{row.outage_count}</td>
                                                    <td className="num">{row.runtime_hrs} hrs</td>
                                                    <td className="num">{fmtNum(row.fuel_used)} L</td>
                                                    <td className="num">{fmtTZS(row.fuel_cost)}</td>
                                                    <td className="num">{fmtNum(row.gen_kwh)} kWh</td>
                                                </tr>
                                            ))}
                                            {runtimeHistory.length === 0 && <tr><td colSpan={6} style={{ textAlign: 'center', padding: 28, color: 'var(--text-muted)' }}>No runtime history yet.</td></tr>}
                                        </tbody>
                                    </table>
                                )}
                            </div>
                        </div>
                    )}

                    {tab === 'outages' && (
                        <div>
                            <div className="page-header">
                                <div>
                                    <div className="page-title">Outages</div>
                                    <div className="page-sub">Track grid failures and generator activation events.</div>
                                </div>
                            </div>

                            <div className="grid-2-equal" style={{ marginBottom: 16 }}>
                                <div className="card">
                                    <div className="card-header"><div className="card-title">Log Outage</div></div>
                                    <div style={{ padding: '16px 18px' }}>
                                        <div className="form-row">
                                            <div className="form-group">
                                                <label className="form-label">Outage Date</label>
                                                <input className="form-input" type="date" value={outageForm.outage_date} onChange={(e) => setOutageForm((form) => ({ ...form, outage_date: e.target.value }))} />
                                            </div>
                                            <div className="form-group">
                                                <label className="form-label">Type</label>
                                                <select className="form-input form-select" value={outageForm.type} onChange={(e) => setOutageForm((form) => ({ ...form, type: e.target.value }))}>
                                                    <option value="minor">Minor</option>
                                                    <option value="major">Major</option>
                                                    <option value="planned">Planned</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div className="form-row">
                                            <div className="form-group">
                                                <label className="form-label">Start Time</label>
                                                <input className="form-input" type="time" value={outageForm.start_time} onChange={(e) => setOutageForm((form) => ({ ...form, start_time: e.target.value }))} />
                                            </div>
                                            <div className="form-group">
                                                <label className="form-label">End Time</label>
                                                <input className="form-input" type="time" value={outageForm.end_time} onChange={(e) => setOutageForm((form) => ({ ...form, end_time: e.target.value }))} />
                                            </div>
                                        </div>
                                        <div className="form-row">
                                            <div className="form-group">
                                                <label className="form-label">Floors Affected</label>
                                                <select className="form-input form-select" value={outageForm.floors_affected} onChange={(e) => setOutageForm((form) => ({ ...form, floors_affected: e.target.value }))}>
                                                    {floorSelectOptions.map((label) => (
                                                        <option key={label} value={label}>{label}</option>
                                                    ))}
                                                </select>
                                            </div>
                                            <div className="form-group">
                                                <label className="form-label">Fuel Used</label>
                                                <input className="form-input" type="number" min="0" step="0.01" value={outageForm.fuel_used} onChange={(e) => setOutageForm((form) => ({ ...form, fuel_used: e.target.value }))} />
                                            </div>
                                        </div>
                                        <div className="form-group" style={{ marginBottom: 12 }}>
                                            <label className="form-label">Notes</label>
                                            <textarea className="form-input" rows={2} value={outageForm.notes} onChange={(e) => setOutageForm((form) => ({ ...form, notes: e.target.value }))} style={{ resize: 'vertical' }} />
                                        </div>
                                        <label style={{ display: 'flex', gap: 8, alignItems: 'center', marginBottom: 12 }}>
                                            <input type="checkbox" checked={outageForm.generator_activated} onChange={(e) => setOutageForm((form) => ({ ...form, generator_activated: e.target.checked }))} />
                                            <span style={{ fontSize: 13 }}>Generator activated</span>
                                        </label>
                                        <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
                                            <button className="btn-primary" onClick={() => post('/electricity/outages', outageForm)}>
                                                Save Outage
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div className="card">
                                    <div className="card-header"><div className="card-title">Month Snapshot</div></div>
                                    <div style={{ padding: '16px 18px' }}>
                                        <div className="bill-summary-row"><span>Outages logged</span><span>{outages.length}</span></div>
                                        <div className="bill-summary-row"><span>Generator activations</span><span>{outages.filter((outage) => outage.generator_activated).length}</span></div>
                                        <div className="bill-summary-row"><span>Fuel cost logged</span><span>{fmtTZS(fuelTotal)}</span></div>
                                    </div>
                                </div>
                            </div>

                            <div className="card">
                                <div className="card-header"><div className="card-title">Outage Log</div></div>
                                <table className="ledger-table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Start</th>
                                            <th>End</th>
                                            <th>Type</th>
                                            <th>Floors</th>
                                            <th className="num">Fuel Used</th>
                                            <th>Generator</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {outages.map((outage) => (
                                            <tr key={outage.id}>
                                                <td>{outage.outage_date}</td>
                                                <td>{outage.start_time}</td>
                                                <td>{outage.end_time ?? ' - '}</td>
                                                <td>{outage.type}</td>
                                                <td>{outage.floors_affected ?? 'All floors'}</td>
                                                <td className="num">{outage.fuel_used ? `${fmtNum(outage.fuel_used)} L` : ' - '}</td>
                                                <td>{outage.generator_activated ? 'Yes' : 'No'}</td>
                                            </tr>
                                        ))}
                                        {outages.length === 0 && <tr><td colSpan={7} style={{ textAlign: 'center', padding: 28, color: 'var(--text-muted)' }}>No outages recorded yet.</td></tr>}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}
                </main>
            </div>
        </AppLayout>
    );
}

function OverviewTab({
    currentMonth,
    directUnits,
    submeterUnits,
    directDraftCount,
    submeterDraftCount,
    directTotal,
    submeterTotal,
    fuelTotal,
    totalGenKwh,
    outages,
}) {
    return (
        <div>
            <div className="page-header">
                <div>
                    <div className="page-title">Electricity Overview</div>
                    <div className="page-sub">{fmtMonth(currentMonth)}</div>
                </div>
            </div>

            <div className="stats-grid" style={{ marginBottom: 16 }}>
                <div className="stat-card">
                    <div className="stat-value">{directUnits.length}</div>
                    <div className="stat-label">Direct Units</div>
                </div>
                <div className="stat-card">
                    <div className="stat-value">{submeterUnits.length}</div>
                    <div className="stat-label">Submeter Units</div>
                </div>
                <div className="stat-card">
                    <div className="stat-value">{fmtNum(totalGenKwh)}</div>
                    <div className="stat-label">Generator kWh This Month</div>
                </div>
                <div className="stat-card">
                    <div className="stat-value">{outages.length}</div>
                    <div className="stat-label">Outages Logged</div>
                </div>
            </div>

            <div className="grid-2-equal">
                <div className="card">
                    <div className="card-header"><div className="card-title">Draft Invoice Summary</div></div>
                    <div style={{ padding: '16px 18px' }}>
                        <div className="bill-summary-row"><span>Generator draft invoices</span><span>{directDraftCount}</span></div>
                        <div className="bill-summary-row"><span>Submeter draft invoices</span><span>{submeterDraftCount}</span></div>
                        <div className="bill-summary-row"><span>Generator value</span><span>{fmtTZS(directTotal)}</span></div>
                        <div className="bill-summary-row"><span>Submeter sales value</span><span>{fmtTZS(submeterTotal)}</span></div>
                    </div>
                </div>

                <div className="card">
                    <div className="card-header"><div className="card-title">Operations Summary</div></div>
                    <div style={{ padding: '16px 18px' }}>
                        <div className="bill-summary-row"><span>Fuel spend logged</span><span>{fmtTZS(fuelTotal)}</span></div>
                        <div className="bill-summary-row"><span>Direct units charged only for generator</span><span>Yes</span></div>
                        <div className="bill-summary-row"><span>Submeter units charged for sales only</span><span>Yes</span></div>
                    </div>
                </div>
            </div>
        </div>
    );
}
