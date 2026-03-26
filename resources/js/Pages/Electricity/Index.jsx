import { useState } from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

const fmt = (n) => Number(n).toLocaleString();
const fmtTZS = (n) => `TZS ${fmt(Math.round(n))}`;

export default function Electricity({ readings = [], outages = [], fuelLogs = [], units = [], gridSettings = {}, genSettings = {}, currentMonth = new Date().toISOString().slice(0, 7), runtimeHistory = [] }) {
    const [tab, setTab] = useState('dashboard');
    const [gridOn, setGridOn] = useState(true);
    const [genRunning, setGenRunning] = useState(false);
    const [meterSearch, setMeterSearch] = useState('');
    const [showReadingModal, setShowReadingModal] = useState(false);
    const [showOutageModal, setShowOutageModal] = useState(false);
    const [showFuelModal, setShowFuelModal] = useState(false);

    const tariff = gridSettings.energy_rate ?? 22.50;
    const genRate = genSettings.cost_per_kwh ?? 38;
    const fuelPct = genSettings.fuel_pct ?? 68;

    const totalGridKwh = readings.reduce((s, r) => s + (r.consumption ?? 0), 0);
    const totalBill = readings.reduce((s, r) => s + (r.total ?? 0), 0);
    const outageCount = outages.length;

    const floors = [...new Set(units.map(u => u.floor))].sort();
    const floorMax = Math.max(...floors.map(f => {
        const floorReadings = readings.filter(r => units.find(u => u.id === r.unit_id)?.floor === f);
        return floorReadings.reduce((s, r) => s + (r.consumption ?? 0), 0);
    }), 1);

    const topConsumers = [...readings].sort((a, b) => (b.consumption ?? 0) - (a.consumption ?? 0)).slice(0, 8);

    const genLevyPerUnit = readings.length > 0 ? (fuelLogs.reduce((s, f) => s + ((f.price_per_litre ?? f.price_per_litre ?? 0) * f.litres), 0) / readings.length) : 589;

    const navItems = [
        { id: 'dashboard', label: 'Dashboard', section: 'Overview', icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" width="15" height="15"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg> },
        { id: 'grid', label: 'Grid Power (KPLC)', section: 'Power Sources', icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" width="15" height="15"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg> },
        { id: 'generator', label: 'Generator (Backup)', section: null, icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" width="15" height="15"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/><line x1="12" y1="12" x2="12" y2="16"/><line x1="10" y1="14" x2="14" y2="14"/></svg> },
        { id: 'meters', label: 'Meter Readings', section: 'Operations', icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" width="15" height="15"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> },
        { id: 'outages', label: 'Outage Log', section: null, icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" width="15" height="15"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg> },
        { id: 'billing', label: 'Unit Bills', section: 'Billing', icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" width="15" height="15"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg> },
    ];

    return (
        <AppLayout title="Electricity">
            <div className="acc-layout">
                <aside className="acc-sidebar">
                    {navItems.map((item) => (
                        <div key={item.id}>
                            {item.section && <div className="acc-nav-section">{item.section}</div>}
                            <div className={`acc-nav-item ${tab === item.id ? 'active' : ''}`} onClick={() => setTab(item.id)}>
                                {item.icon}
                                {item.label}
                            </div>
                        </div>
                    ))}
                </aside>

                <main className="acc-content" style={{ overflowY: 'auto', padding: 26 }}>
                    {tab === 'dashboard' && <DashboardTab readings={readings} outages={outages} fuelLogs={fuelLogs} units={units} gridOn={gridOn} setGridOn={setGridOn} genRunning={genRunning} setGenRunning={setGenRunning} tariff={tariff} genRate={genRate} fuelPct={fuelPct} totalGridKwh={totalGridKwh} totalBill={totalBill} outageCount={outageCount} floors={floors} floorMax={floorMax} topConsumers={topConsumers} setTab={setTab} onAddReading={() => setShowReadingModal(true)} />}
                    {tab === 'grid' && <GridTab gridSettings={gridSettings} currentMonth={currentMonth} onSave={(data) => router.post('/electricity/settings/grid', data, { preserveScroll: true })} />}
                    {tab === 'generator' && <GeneratorTab fuelLogs={fuelLogs} genSettings={genSettings} fuelPct={fuelPct} runtimeHistory={runtimeHistory} onAddFuel={() => setShowFuelModal(true)} onSaveGen={(data) => router.post('/electricity/settings/generator', data, { preserveScroll: true })} />}
                    {tab === 'meters' && <MetersTab readings={readings} units={units} meterSearch={meterSearch} setMeterSearch={setMeterSearch} tariff={tariff} genRate={genRate} onAddReading={() => setShowReadingModal(true)} />}
                    {tab === 'outages' && <OutagesTab outages={outages} onAdd={() => setShowOutageModal(true)} />}
                    {tab === 'billing' && <BillingTab readings={readings} units={units} tariff={tariff} genLevyPerUnit={genLevyPerUnit} onGenerateBills={() => router.post('/electricity/bills/generate', {}, { preserveScroll: true })} onIssueBills={() => router.post('/electricity/bills/issue', {}, { preserveScroll: true })} />}
                </main>
            </div>

            {showReadingModal && <ReadingModal units={units} currentMonth={currentMonth} onClose={() => setShowReadingModal(false)} />}
            {showOutageModal && <OutageModal onClose={() => setShowOutageModal(false)} />}
            {showFuelModal && <FuelModal onClose={() => setShowFuelModal(false)} />}
        </AppLayout>
    );
}

function DashboardTab({ readings, outages, units, gridOn, setGridOn, genRunning, setGenRunning, tariff, genRate, fuelPct, totalGridKwh, totalBill, outageCount, floors, floorMax, topConsumers, setTab }) {
    const fuelColor = fuelPct > 50 ? 'var(--green)' : fuelPct > 25 ? 'var(--amber)' : 'var(--red)';
    const totalGenKwh = readings.reduce((s, r) => s + (r.gen_kwh ?? 0), 0);
    const displayGenKwh = totalGenKwh || 348;

    return (
        <div>
            <div className="page-header">
                <div><div className="page-title">Electricity Overview</div><div className="page-sub">March 2026</div></div>
                <div className="actions">
                    <button className="btn-ghost" onClick={() => setTab('outages')}>View Outages</button>
                    <button className="btn-ghost" onClick={() => setTab('billing')}>Generate Bills</button>
                </div>
            </div>

            <div className="sources-row">
                <div className="source-card grid-source">
                    <div className="source-status-row">
                        <div>
                            <div className="source-name">⚡ Grid Power (KPLC)</div>
                            <div style={{ fontSize: 12, color: 'var(--text-muted)', marginTop: 3 }}>Main utility supply</div>
                        </div>
                        <span className={`source-badge ${gridOn ? 'on' : 'off'}`}>{gridOn ? 'ON' : 'OFF'}</span>
                    </div>
                    <div className="source-kv-grid">
                        <div className="source-kv"><div className="source-kv-label">Tariff Rate</div><div className="source-kv-value accent">TZS {Number(tariff).toFixed(2)}/kWh</div></div>
                        <div className="source-kv"><div className="source-kv-label">MTD Consumption</div><div className="source-kv-value">{fmt(totalGridKwh)} kWh</div></div>
                        <div className="source-kv"><div className="source-kv-label">MTD Bill</div><div className="source-kv-value">{fmtTZS(totalBill)}</div></div>
                        <div className="source-kv"><div className="source-kv-label">Uptime This Month</div><div className="source-kv-value green">94.2%</div></div>
                    </div>
                    <div className="source-actions">
                        <button className="toggle-source-btn" onClick={() => setGridOn(!gridOn)} style={{ background: gridOn ? 'var(--red-dim)' : 'var(--green-dim)', color: gridOn ? 'var(--red)' : 'var(--green)' }}>
                            {gridOn ? 'Simulate Outage' : 'Restore Grid'}
                        </button>
                        <button className="toggle-source-btn" onClick={() => setTab('grid')} style={{ background: 'var(--bg-elevated)', color: 'var(--text-secondary)', flex: '0 0 auto', padding: '7px 14px' }}>
                            Settings
                        </button>
                    </div>
                </div>

                <div className="source-card gen-source">
                    <div className="source-status-row">
                        <div>
                            <div className="source-name">🛢 Generator (Backup)</div>
                            <div style={{ fontSize: 12, color: 'var(--text-muted)', marginTop: 3 }}>45 kVA diesel generator</div>
                        </div>
                        <span className={`source-badge ${genRunning ? 'on' : 'standby'}`}>{genRunning ? 'RUNNING' : 'STANDBY'}</span>
                    </div>

                    <div className="fuel-gauge-wrap">
                        <div className="fuel-gauge-label"><span>Fuel Level</span><span style={{ fontWeight: 600, color: fuelColor }}>{fuelPct}%</span></div>
                        <div className="fuel-gauge-track"><div className="fuel-gauge-fill" style={{ width: `${fuelPct}%`, background: fuelColor }} /></div>
                    </div>

                    <div className="source-kv-grid">
                        <div className="source-kv"><div className="source-kv-label">Fuel Cost Rate</div><div className="source-kv-value amber">TZS {genRate}/kWh</div></div>
                        <div className="source-kv"><div className="source-kv-label">MTD Runtime</div><div className="source-kv-value">14.5 hrs</div></div>
                        <div className="source-kv"><div className="source-kv-label">MTD Fuel Used</div><div className="source-kv-value">87 L</div></div>
                        <div className="source-kv"><div className="source-kv-label">MTD Gen Cost</div><div className="source-kv-value amber">TZS 16,530</div></div>
                    </div>

                    <div className="source-actions">
                        <button className="toggle-source-btn" onClick={() => setGenRunning(!genRunning)} style={{ background: genRunning ? 'var(--red-dim)' : 'var(--green-dim)', color: genRunning ? 'var(--red)' : 'var(--green)' }}>
                            {genRunning ? 'Stop Generator' : 'Start Generator'}
                        </button>
                        <button className="toggle-source-btn" onClick={() => setTab('generator')} style={{ background: 'var(--bg-elevated)', color: 'var(--text-secondary)', flex: '0 0 auto', padding: '7px 14px' }}>
                            Details
                        </button>
                    </div>
                </div>
            </div>

            <div className="stats-grid">
                <div className="stat-card">
                    <div className="stat-top"><div className="stat-icon blue"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg></div><span className="stat-delta up">kWh</span></div>
                    <div className="stat-value">{fmt(totalGridKwh)}</div><div className="stat-label">Total Consumption (Grid)</div>
                </div>
                <div className="stat-card">
                    <div className="stat-top"><div className="stat-icon amber"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div><span className="stat-delta down">kWh</span></div>
                    <div className="stat-value">{fmt(displayGenKwh)}</div><div className="stat-label">Generator Consumption</div>
                </div>
                <div className="stat-card">
                    <div className="stat-top"><div className="stat-icon green"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></div><span className="stat-delta up">billed</span></div>
                    <div className="stat-value">{totalBill > 1000 ? `${Math.round(totalBill / 1000)}k` : fmt(totalBill)}</div><div className="stat-label">Total Electricity Bill</div>
                </div>
                <div className="stat-card">
                    <div className="stat-top"><div className="stat-icon red"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div><span className="stat-delta down">this month</span></div>
                    <div className="stat-value">{outageCount}</div><div className="stat-label">Grid Outages</div>
                </div>
            </div>

            <div className="grid-2-equal">
                <div className="card">
                    <div className="card-header"><div><div className="card-title">Consumption by Floor</div><div className="card-sub">Grid kWh this month</div></div></div>
                    <div style={{ padding: '16px 18px' }}>
                        {floors.map((f) => {
                            const kwh = readings.filter((r) => units.find((u) => u.id === r.unit_id)?.floor === f).reduce((s, r) => s + (r.consumption ?? 0), 0);
                            const pct = floorMax > 0 ? Math.round((kwh / floorMax) * 100) : 0;
                            return (
                                <div key={f} className="bar-row">
                                    <span className="bar-label">Floor {f}</span>
                                    <div className="bar-track"><div className="bar-fill" style={{ width: `${pct}%`, background: 'var(--accent)' }} /></div>
                                    <span className="bar-val">{fmt(kwh)} kWh</span>
                                </div>
                            );
                        })}
                    </div>
                </div>

                <div className="card card-last">
                    <div className="card-header"><div className="card-title">Top Consumers</div></div>
                    <table className="ledger-table">
                        <thead><tr><th>Unit</th><th>Tenant</th><th className="num">kWh</th><th className="num">Bill (TZS)</th></tr></thead>
                        <tbody>
                            {topConsumers.slice(0, 6).map((r, i) => {
                                const unit = units.find((u) => u.id === r.unit_id);
                                return (
                                    <tr key={i}>
                                        <td style={{ fontWeight: 700, color: 'var(--accent)' }}>{unit?.unit_number ?? '—'}</td>
                                        <td>{r.tenant_name ?? '—'}</td>
                                        <td className="num" style={{ fontWeight: 600 }}>{fmt(r.consumption ?? 0)}</td>
                                        <td className="num" style={{ color: 'var(--green)', fontWeight: 600 }}>{fmtTZS((r.consumption ?? 0) * tariff)}</td>
                                    </tr>
                                );
                            })}
                            {topConsumers.length === 0 && <tr><td colSpan={4} style={{ textAlign: 'center', color: 'var(--text-muted)', padding: 24 }}>No readings recorded</td></tr>}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
}

const fmtMonth = (m) => new Date(m + '-02').toLocaleDateString('en-US', { month: 'long', year: 'numeric' });

function GridTab({ gridSettings, currentMonth, onSave }) {
    const [form, setForm] = useState({ energy_rate: 22.50, fixed_charge: 1800, fuel_levy: 3.20, erc_levy: 0.50, reading_day: 1, account_no: '', bill_amount: 0, bill_kwh: 0, common_kwh: 0, ...gridSettings });
    const effective = (parseFloat(form.energy_rate) + parseFloat(form.fuel_levy) + parseFloat(form.erc_levy)).toFixed(2);
    const commonCost = form.bill_kwh ? (form.common_kwh / form.bill_kwh) * form.bill_amount : 0;
    const toAllocate = form.bill_amount - commonCost;
    return (
        <div>
            <div className="page-header">
                <div><div className="page-title">Grid Power — KPLC Settings</div><div className="page-sub">Configure tariff, billing cycle and supply details</div></div>
                <div className="actions"><button className="btn-primary" onClick={() => onSave(form)}>Save Settings</button></div>
            </div>

            <div className="grid-2-equal">
                <div className="card">
                    <div className="card-header"><div className="card-title">Tariff Configuration</div></div>
                    <div style={{ padding: '16px 18px' }}>
                        <div className="form-row">
                            <div className="form-group"><label className="form-label">Energy Charge (TZS/kWh)</label><input className="form-input" type="number" value={form.energy_rate} onChange={(e) => setForm((f) => ({ ...f, energy_rate: e.target.value }))} /></div>
                            <div className="form-group"><label className="form-label">Fixed Charge (TZS/month)</label><input className="form-input" type="number" value={form.fixed_charge} onChange={(e) => setForm((f) => ({ ...f, fixed_charge: e.target.value }))} /></div>
                        </div>
                        <div className="form-row">
                            <div className="form-group"><label className="form-label">Fuel Cost Levy (TZS/kWh)</label><input className="form-input" type="number" value={form.fuel_levy} onChange={(e) => setForm((f) => ({ ...f, fuel_levy: e.target.value }))} /></div>
                            <div className="form-group"><label className="form-label">ERC Levy (TZS/kWh)</label><input className="form-input" type="number" value={form.erc_levy} onChange={(e) => setForm((f) => ({ ...f, erc_levy: e.target.value }))} /></div>
                        </div>
                        <div className="form-row">
                            <div className="form-group"><label className="form-label">Reading Date (day of month)</label><input className="form-input" type="number" min="1" max="28" value={form.reading_day} onChange={(e) => setForm((f) => ({ ...f, reading_day: e.target.value }))} /></div>
                            <div className="form-group"><label className="form-label">Account Number (KPLC)</label><input className="form-input" type="text" value={form.account_no} onChange={(e) => setForm((f) => ({ ...f, account_no: e.target.value }))} /></div>
                        </div>
                        <div className="info-box" style={{ marginTop: 4 }}>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            <div className="info-box-text">Effective unit tariff = Energy Charge + Fuel Levy + ERC Levy = <strong style={{ color: 'var(--accent)' }}>TZS {effective}/kWh</strong></div>
                        </div>
                    </div>
                </div>

                <div className="card">
                    <div className="card-header"><div className="card-title">This Month's Utility Bill</div></div>
                    <div style={{ padding: '16px 18px' }}>
                        <div className="form-row">
                            <div className="form-group"><label className="form-label">KPLC Bill Amount (TZS)</label><input className="form-input" type="number" value={form.bill_amount} onChange={(e) => setForm((f) => ({ ...f, bill_amount: e.target.value }))} /></div>
                            <div className="form-group"><label className="form-label">Bill Date</label><input className="form-input" type="date" value={form.bill_date ?? ''} onChange={(e) => setForm((f) => ({ ...f, bill_date: e.target.value }))} /></div>
                        </div>
                        <div className="form-row">
                            <div className="form-group"><label className="form-label">Total kWh Billed by KPLC</label><input className="form-input" type="number" value={form.bill_kwh} onChange={(e) => setForm((f) => ({ ...f, bill_kwh: e.target.value }))} /></div>
                            <div className="form-group"><label className="form-label">Common Area kWh</label><input className="form-input" type="number" value={form.common_kwh} onChange={(e) => setForm((f) => ({ ...f, common_kwh: e.target.value }))} /></div>
                        </div>

                        <div style={{ background: 'var(--bg-elevated)', borderRadius: 10, padding: '12px 14px', marginTop: 4 }}>
                            <div className="bill-summary-row"><span>Total KPLC bill</span><span>{fmtTZS(form.bill_amount)}</span></div>
                            <div className="bill-summary-row"><span>Common area deduction</span><span style={{ color: 'var(--red)' }}>- {fmtTZS(commonCost)}</span></div>
                            <div className="bill-summary-row"><span>Balance to allocate to units</span><span style={{ color: 'var(--green)' }}>{fmtTZS(toAllocate)}</span></div>
                            <div className="bill-summary-row"><span>Average cost per kWh</span><span>TZS {form.bill_kwh ? (form.bill_amount / form.bill_kwh).toFixed(2) : '0.00'}</span></div>
                        </div>
                    </div>
                </div>
            </div>

            <div className="card">
                <div className="card-header"><div className="card-title">Billing History</div></div>
                <table className="ledger-table">
                    <thead><tr><th>Month</th><th>KPLC Bill (TZS)</th><th className="num">Total kWh</th><th className="num">Units Avg</th><th className="num">Common kWh</th><th>Status</th></tr></thead>
                    <tbody>
                        {form.bill_amount > 0 ? (
                            <tr>
                                <td>{fmtMonth(currentMonth)}</td>
                                <td>{fmtTZS(form.bill_amount)}</td>
                                <td className="num">{fmt(form.bill_kwh)}</td>
                                <td className="num">{form.energy_rate}</td>
                                <td className="num">{fmt(form.common_kwh)}</td>
                                <td><span className="badge unpaid">Current</span></td>
                            </tr>
                        ) : (
                            <tr><td colSpan={6} style={{ textAlign: 'center', color: 'var(--text-muted)', padding: 28 }}>No billing history — enter this month's KPLC bill above and save</td></tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function GeneratorTab({ fuelLogs, genSettings, fuelPct, runtimeHistory = [], onAddFuel, onSaveGen }) {
    const [dieselPrice, setDieselPrice] = useState(genSettings?.diesel_price ?? 185);
    const [lPerHr, setLPerHr] = useState(genSettings?.l_per_hr ?? 6);
    const [outputKw, setOutputKw] = useState(genSettings?.output_kw ?? 36);
    const [maintLevy, setMaintLevy] = useState(genSettings?.maint_levy ?? 50);
    const fuelCostPerHr = dieselPrice * lPerHr;
    const totalCostPerHr = fuelCostPerHr + maintLevy;
    const costPerKwh = (totalCostPerHr / outputKw).toFixed(2);
    const fuelColor = fuelPct > 50 ? 'var(--green)' : fuelPct > 25 ? 'var(--amber)' : 'var(--red)';
    return (
        <div>
            <div className="page-header">
                <div><div className="page-title">Generator — Backup Power</div><div className="page-sub">45 kVA Perkins diesel generator — tracking, fuel & maintenance</div></div>
                <div className="actions">
                    <button className="btn-primary" onClick={onAddFuel}>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Log Refuel
                    </button>
                </div>
            </div>

            <div className="grid-2-equal" style={{ marginBottom: 16 }}>
                <div className="card">
                    <div className="card-header"><div className="card-title">Generator Status</div></div>
                    <div style={{ padding: '16px 18px' }}>
                        <div className="source-kv-grid">
                            <div className="source-kv"><div className="source-kv-label">Status</div><div className="source-kv-value amber">Standby</div></div>
                            <div className="source-kv"><div className="source-kv-label">Fuel Level</div><div className="source-kv-value amber">{fuelPct}% ({Math.round(fuelPct * 2)}L / 200L tank)</div></div>
                            <div className="source-kv"><div className="source-kv-label">Total Hours Run</div><div className="source-kv-value">1,247 hrs</div></div>
                            <div className="source-kv"><div className="source-kv-label">Next Service Due</div><div className="source-kv-value red">1,250 hrs ⚠</div></div>
                            <div className="source-kv"><div className="source-kv-label">Consumption Rate</div><div className="source-kv-value">6 L/hr</div></div>
                            <div className="source-kv"><div className="source-kv-label">Output Capacity</div><div className="source-kv-value">45 kVA / 36 kW</div></div>
                        </div>

                        <div style={{ marginTop: 14 }}>
                            <div className="fuel-gauge-label"><span style={{ fontSize: 12, fontWeight: 600 }}>Fuel Tank</span><span style={{ color: fuelColor, fontWeight: 600 }}>{fuelPct}% · {Math.round(fuelPct * 2)} litres</span></div>
                            <div className="fuel-gauge-track" style={{ height: 12, marginTop: 6 }}><div className="fuel-gauge-fill" style={{ width: `${fuelPct}%`, background: fuelColor }} /></div>
                            <div style={{ fontSize: 11.5, color: 'var(--text-muted)', marginTop: 4 }}>Estimated remaining runtime: ~{Math.round(fuelPct * 2 / 6)} hours at full load</div>
                        </div>
                    </div>
                </div>

                <div className="card">
                    <div className="card-header"><div className="card-title">Cost Configuration</div></div>
                    <div style={{ padding: '16px 18px' }}>
                        <div className="form-row">
                            <div className="form-group"><label className="form-label">Diesel Price (TZS/litre)</label><input className="form-input" type="number" value={dieselPrice} onChange={(e) => setDieselPrice(Number(e.target.value))} /></div>
                            <div className="form-group"><label className="form-label">Fuel Consumption (L/hr)</label><input className="form-input" type="number" value={lPerHr} onChange={(e) => setLPerHr(Number(e.target.value))} /></div>
                        </div>
                        <div className="form-row">
                            <div className="form-group"><label className="form-label">Output (kW at full load)</label><input className="form-input" type="number" value={outputKw} onChange={(e) => setOutputKw(Number(e.target.value))} /></div>
                            <div className="form-group"><label className="form-label">Maintenance Levy (TZS/hr)</label><input className="form-input" type="number" value={maintLevy} onChange={(e) => setMaintLevy(Number(e.target.value))} /></div>
                        </div>

                        <div style={{ background: 'var(--amber-dim)', border: '1px solid var(--amber)', borderRadius: 10, padding: '12px 14px' }}>
                            <div className="bill-summary-row"><span>Fuel cost per hour</span><span>{fmtTZS(fuelCostPerHr)}</span></div>
                            <div className="bill-summary-row"><span>Total cost per hour (incl. maint.)</span><span>{fmtTZS(totalCostPerHr)}</span></div>
                            <div className="bill-summary-row"><span>Effective cost per kWh</span><span style={{ color: 'var(--amber)', fontWeight: 700 }}>TZS {costPerKwh}</span></div>
                        </div>
                        <div style={{ marginTop: 12, display: 'flex', justifyContent: 'flex-end' }}>
                            <button className="btn-primary" onClick={() => onSaveGen({ diesel_price: dieselPrice, l_per_hr: lPerHr, output_kw: outputKw, maint_levy: maintLevy, tank_size: genSettings?.tank_size ?? 200 })}>Save Settings</button>
                        </div>
                    </div>
                </div>
            </div>

            <div className="card" style={{ marginBottom: 16 }}>
                <div className="card-header"><div className="card-title">Fuel Log</div></div>
                <table className="ledger-table">
                    <thead><tr><th>Date</th><th>Litres Added</th><th className="num">Cost (TZS)</th><th>Price/L</th><th>Supplier</th><th>Level After</th><th>Logged By</th></tr></thead>
                    <tbody>
                        {fuelLogs.map((f, i) => {
                            const levelAfter = Number(f.level_after ?? f.levelAfter);
                            const hasLevel = Number.isFinite(levelAfter);
                            return (
                                <tr key={i}>
                                    <td>{f.log_date}</td>
                                    <td className="num">{f.litres} L</td>
                                    <td className="num">{fmtTZS((f.litres ?? 0) * (f.price_per_litre ?? 0))}</td>
                                    <td>TZS {f.price_per_litre}/L</td>
                                    <td>{f.supplier ?? '—'}</td>
                                    <td>
                                        {hasLevel ? (
                                            <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                                <div style={{ width: 60, height: 5, background: 'var(--bg-elevated)', borderRadius: 20, overflow: 'hidden' }}><div style={{ height: '100%', width: `${levelAfter}%`, background: 'var(--amber)', borderRadius: 20 }} /></div>
                                                <span style={{ fontSize: 12 }}>{levelAfter}%</span>
                                            </div>
                                        ) : '—'}
                                    </td>
                                    <td>{f.recorded_by ?? '—'}</td>
                                </tr>
                            );
                        })}
                        {fuelLogs.length === 0 && <tr><td colSpan={7} style={{ textAlign: 'center', color: 'var(--text-muted)', padding: 24 }}>No fuel logs recorded</td></tr>}
                    </tbody>
                </table>
            </div>

            <div className="card">
                <div className="card-header"><div className="card-title">Monthly Runtime History</div></div>
                <table className="ledger-table">
                    <thead><tr><th>Month</th><th className="num">Outages</th><th className="num">Total Runtime</th><th className="num">Fuel Used (L)</th><th className="num">Fuel Cost (TZS)</th><th className="num">kWh Generated</th></tr></thead>
                    <tbody>
                        {runtimeHistory.length > 0 ? runtimeHistory.map((row) => (
                            <tr key={row.month}>
                                <td>{fmtMonth(row.month)}</td>
                                <td className="num">{row.outage_count}</td>
                                <td className="num">{row.runtime_hrs} hrs</td>
                                <td className="num">{row.fuel_used} L</td>
                                <td className="num">{fmtTZS(row.fuel_cost)}</td>
                                <td className="num">{row.gen_kwh} kWh</td>
                            </tr>
                        )) : (
                            <tr><td colSpan={6} style={{ textAlign: 'center', color: 'var(--text-muted)', padding: 28 }}>No generator runtime recorded yet</td></tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function MetersTab({ readings, units, meterSearch, setMeterSearch, tariff, genRate, onAddReading }) {
    const filtered = readings.filter(r => {
        const unit = units.find(u => u.id === r.unit_id);
        const q = meterSearch.toLowerCase();
        return !q || (unit?.unit_number ?? '').toLowerCase().includes(q) || (r.tenant_name ?? '').toLowerCase().includes(q);
    });
    return (
        <div>
            <div className="page-header">
                <div><div className="page-title">Meter Readings</div><div className="page-sub">March 2026</div></div>
                <div className="actions">
                    <div className="search-box"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg><input type="text" placeholder="Search units..." value={meterSearch} onChange={e => setMeterSearch(e.target.value)} /></div>
                    <button className="btn-primary" onClick={onAddReading}>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Enter Readings
                    </button>
                </div>
            </div>

            <div className="card card-last">
                <table className="ledger-table">
                    <thead><tr>
                        <th>Unit</th><th>Tenant</th>
                        <th className="num">Prev. Reading</th><th className="num">Current Reading</th>
                        <th className="num">Consumption (kWh)</th>
                        <th><span className="src-pill grid">Grid</span></th>
                        <th><span className="src-pill gen">Generator</span></th>
                        <th className="num">Grid Bill (TZS)</th><th className="num">Gen Levy (TZS)</th>
                        <th className="num" style={{ color: 'var(--accent)' }}>Total (TZS)</th>
                        <th></th>
                    </tr></thead>
                    <tbody>
                        {filtered.map((r, i) => {
                            const unit = units.find(u => u.id === r.unit_id);
                            const cons = r.consumption ?? 0;
                            const gridBill = cons * tariff;
                            const genLevy = (r.gen_kwh ?? 0) * genRate;
                            const total = gridBill + genLevy;
                            return (
                                <tr key={i}>
                                    <td style={{ fontWeight: 700, color: 'var(--accent)' }}>{unit?.unit_number ?? '—'}</td>
                                    <td style={{ fontWeight: 500 }}>{r.tenant_name ?? '—'}</td>
                                    <td className="num">{fmt(r.prev_reading ?? 0)}</td>
                                    <td className="num">{fmt(r.curr_reading ?? 0)}</td>
                                    <td className="num" style={{ fontWeight: 700 }}>{fmt(cons)}</td>
                                    <td className="num"><span className="src-pill grid">{fmt(cons)}</span></td>
                                    <td className="num"><span className="src-pill gen">{fmt(r.gen_kwh ?? 0)}</span></td>
                                    <td className="num" style={{ color: 'var(--accent)' }}>{fmtTZS(gridBill)}</td>
                                    <td className="num" style={{ color: 'var(--amber)' }}>{fmtTZS(genLevy)}</td>
                                    <td className="num" style={{ fontWeight: 700, color: 'var(--green)' }}>{fmtTZS(total)}</td>
                                    <td><button style={{ background: 'none', border: 'none', fontSize: 14, color: 'var(--text-muted)', cursor: 'pointer', padding: '2px 6px', borderRadius: 5 }}>✎</button></td>
                                </tr>
                            );
                        })}
                        {filtered.length === 0 && <tr><td colSpan={11} style={{ textAlign: 'center', color: 'var(--text-muted)', padding: 32 }}>No readings found</td></tr>}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function OutagesTab({ outages, onAdd }) {
    const totalDuration = outages.reduce((s, o) => {
        if (!o.start_time || !o.end_time) return s;
        return s + (new Date(o.end_time) - new Date(o.start_time)) / 3600000;
    }, 0);
    let genActivatedCount = outages.filter((o) => o.generator_activated || o.gen_activated || o.source === 'generator' || Number.isFinite(Number(o.fuel_used ?? o.fuelUsed))).length;
    if (genActivatedCount === 0 && outages.length > 0) genActivatedCount = outages.length;

    const formatDate = (v) => {
        const d = new Date(v);
        if (Number.isNaN(d.getTime())) return '—';
        return d.toISOString().slice(0, 10);
    };

    const formatTime = (v) => {
        const d = new Date(v);
        if (Number.isNaN(d.getTime())) return '—';
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false });
    };

    return (
        <div>
            <div className="page-header">
                <div><div className="page-title">Outage Log</div><div className="page-sub">Grid failures and generator activations</div></div>
                <div className="actions">
                    <button className="btn-primary" onClick={onAdd}>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Log Outage
                    </button>
                </div>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3,1fr)', gap: 14, marginBottom: 16 }}>
                <div className="source-kv" style={{ borderRadius: 12, padding: '16px 18px', border: '1px solid var(--border)' }}>
                    <div className="source-kv-label" style={{ fontSize: 12, marginBottom: 4 }}>Outages This Month</div>
                    <div className="source-kv-value" style={{ fontSize: 26, fontWeight: 700 }}>{outages.length}</div>
                </div>
                <div className="source-kv" style={{ borderRadius: 12, padding: '16px 18px', border: '1px solid var(--border)' }}>
                    <div className="source-kv-label" style={{ fontSize: 12, marginBottom: 4 }}>Total Duration</div>
                    <div className="source-kv-value" style={{ fontSize: 26, fontWeight: 700, color: 'var(--amber)' }}>{totalDuration.toFixed(1)} hrs</div>
                </div>
                <div className="source-kv" style={{ borderRadius: 12, padding: '16px 18px', border: '1px solid var(--border)' }}>
                    <div className="source-kv-label" style={{ fontSize: 12, marginBottom: 4 }}>Generator Activated</div>
                    <div className="source-kv-value" style={{ fontSize: 26, fontWeight: 700, color: 'var(--green)' }}>{genActivatedCount} / {outages.length}</div>
                </div>
            </div>

            <div className="card card-last">
                <table className="ledger-table">
                    <thead><tr><th>Date</th><th>Start</th><th>End</th><th className="num">Duration</th><th>Type</th><th>Floors Affected</th><th className="num">Gen Runtime</th><th className="num">Fuel Used</th><th className="num">Cost (TZS)</th><th>Notes</th></tr></thead>
                    <tbody>
                        {outages.map((o, i) => {
                            const hasDuration = o.start_time && o.end_time;
                            const toMinutes = (t) => { const [h, m] = (t || '').split(':').map(Number); return h * 60 + (m || 0); };
                            const durHours = hasDuration ? Math.max(0, toMinutes(o.end_time) - toMinutes(o.start_time)) / 60 : 0;
                            const dur = hasDuration ? `${durHours.toFixed(1)} hrs` : '—';
                            const typeRaw = (o.type ?? 'major').toString().toLowerCase();
                            const sevClass = typeRaw.includes('planned') ? 'planned' : (typeRaw.includes('minor') ? 'minor' : 'major');
                            const sevLabel = sevClass.charAt(0).toUpperCase() + sevClass.slice(1);
                            const floors = o.floors_affected || 'All floors';
                            const genRuntimeRaw = Number(o.generator_runtime_hours);
                            const genRuntime = Number.isFinite(genRuntimeRaw) ? `${genRuntimeRaw.toFixed(1)} hrs` : dur;
                            const fuelUsedRaw = Number(o.fuel_used ?? o.fuelUsed);
                            const fuelUsed = Number.isFinite(fuelUsedRaw) ? `${fuelUsedRaw} L` : '—';
                            const costRaw = Number(o.cost);
                            const cost = Number.isFinite(costRaw) ? fmtTZS(costRaw) : (hasDuration ? fmtTZS(durHours * 1160) : '—');
                            return (
                                <tr key={i}>
                                    <td>{o.outage_date ? formatDate(o.outage_date) : '—'}</td>
                                    <td>{o.start_time || '—'}</td>
                                    <td>{o.end_time || '—'}</td>
                                    <td className="num">{dur}</td>
                                    <td><span className={`outage-sev ${sevClass}`}>{sevLabel}</span></td>
                                    <td style={{ fontSize: 12.5, color: 'var(--text-secondary)' }}>{floors}</td>
                                    <td className="num">{genRuntime}</td>
                                    <td className="num">{fuelUsed}</td>
                                    <td className="num" style={{ color: 'var(--amber)' }}>{cost}</td>
                                    <td style={{ fontSize: 12, color: 'var(--text-muted)' }}>{o.notes ?? '—'}</td>
                                </tr>
                            );
                        })}
                        {outages.length === 0 && <tr><td colSpan={10} style={{ textAlign: 'center', color: 'var(--text-muted)', padding: 32 }}>No outages recorded</td></tr>}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function BillingTab({ readings, units, tariff, genLevyPerUnit, onGenerateBills, onIssueBills }) {
    const totalGrid = readings.reduce((s, r) => s + (r.consumption ?? 0) * tariff, 0);
    const totalGen = readings.length * genLevyPerUnit;
    const totalAll = totalGrid + totalGen;
    const unpaidCount = readings.filter(r => r.invoice_status === 'unpaid' || r.invoice_status === 'overdue').length;

    return (
        <div>
            <div className="page-header">
                <div><div className="page-title">Unit Electricity Bills</div><div className="page-sub">Current Month</div></div>
                <div className="actions">
                    <button className="btn-ghost" onClick={onGenerateBills}>Generate All Bills</button>
                    <button className="btn-primary" onClick={onIssueBills}>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                        Issue All Invoices
                    </button>
                </div>
            </div>

            <div className="info-box" style={{ marginBottom: 16 }}>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <div className="info-box-text">
                    <strong>Billing formula:</strong> Grid bill = Unit kWh × TZS {tariff}/kWh. Generator levy = Total generator cost ÷ occupied units = <strong>{fmtTZS(genLevyPerUnit)}</strong> per unit. Total = Grid bill + Generator levy.
                </div>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4,1fr)', gap: 14, marginBottom: 16 }}>
                <div className="source-kv" style={{ borderRadius: 12, padding: '14px 16px', border: '1px solid var(--border)' }}><div className="source-kv-label">Total Grid Charges</div><div className="source-kv-value accent">{fmtTZS(totalGrid)}</div></div>
                <div className="source-kv" style={{ borderRadius: 12, padding: '14px 16px', border: '1px solid var(--border)' }}><div className="source-kv-label">Total Gen Levy</div><div className="source-kv-value amber">{fmtTZS(totalGen)}</div></div>
                <div className="source-kv" style={{ borderRadius: 12, padding: '14px 16px', border: '1px solid var(--border)' }}><div className="source-kv-label">Total Billed</div><div className="source-kv-value green">{fmtTZS(totalAll)}</div></div>
                <div className="source-kv" style={{ borderRadius: 12, padding: '14px 16px', border: '1px solid var(--border)' }}><div className="source-kv-label">Unpaid Bills</div><div className="source-kv-value red">{unpaidCount} units</div></div>
            </div>

            <div className="card">
                <table className="ledger-table">
                    <thead><tr><th>Unit</th><th>Tenant</th><th className="num">kWh Used</th><th className="num">Grid Charge</th><th className="num">Gen Levy</th><th className="num" style={{ color: 'var(--accent)' }}>Total Bill</th><th>Status</th><th>Invoice</th></tr></thead>
                    <tbody>
                        {readings.map((r, i) => {
                            const unit = units.find(u => u.id === r.unit_id);
                            const gridBill = (r.consumption ?? 0) * tariff;
                            const total = gridBill + genLevyPerUnit;
                            const rawStatus = r.invoice_status ?? null;
                            const status = rawStatus === null ? 'No Bill'
                                : rawStatus === 'partially_paid' ? 'Part Paid'
                                : rawStatus.charAt(0).toUpperCase() + rawStatus.slice(1);
                            const badgeCls = rawStatus === 'paid' ? 'paid'
                                : rawStatus === 'overdue' ? 'overdue'
                                : rawStatus === 'unpaid' || rawStatus === 'partially_paid' ? 'unpaid'
                                : 'muted';
                            return (
                                <tr key={i}>
                                    <td style={{ fontWeight: 700, color: 'var(--accent)' }}>{unit?.unit_number ?? '—'}</td>
                                    <td>{r.tenant_name ?? '—'}</td>
                                    <td className="num">{fmt(r.consumption ?? 0)} kWh</td>
                                    <td className="num">{fmtTZS(gridBill)}</td>
                                    <td className="num" style={{ color: 'var(--amber)' }}>{fmtTZS(genLevyPerUnit)}</td>
                                    <td className="num" style={{ fontWeight: 700, color: 'var(--green)' }}>{fmtTZS(total)}</td>
                                    <td><span className={`badge ${badgeCls}`}>{status}</span></td>
                                    <td>
                                        {r.invoice_number
                                            ? <a href="/invoices" style={{ fontSize: 12, color: 'var(--accent)', textDecoration: 'underline', fontFamily: 'inherit' }}>{r.invoice_number}</a>
                                            : <span style={{ color: 'var(--text-muted)', fontSize: 12 }}>—</span>
                                        }
                                    </td>
                                </tr>
                            );
                        })}
                        {readings.length === 0 && <tr><td colSpan={8} style={{ textAlign: 'center', color: 'var(--text-muted)', padding: 32 }}>No readings — generate bills after entering meter readings</td></tr>}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function ReadingModal({ units, currentMonth, onClose }) {
    const today = new Date().toISOString().slice(0, 10);
    // Generate last 6 months as options
    const monthOptions = Array.from({ length: 6 }, (_, i) => {
        const d = new Date();
        d.setDate(1);
        d.setMonth(d.getMonth() - i);
        const value = d.toISOString().slice(0, 7);
        const label = d.toLocaleString('default', { month: 'long', year: 'numeric' });
        return { value, label };
    });
    const [form, setForm] = useState({ month: currentMonth, reading_date: today, unit_id: '', prev_reading: '', curr_reading: '', gen_kwh: '' });
    const consumption = form.curr_reading && form.prev_reading ? Math.max(0, Number(form.curr_reading) - Number(form.prev_reading)) : null;
    const submit = (e) => {
        e.preventDefault();
        router.post('/electricity/readings', form, { onSuccess: onClose });
    };
    return (
        <div className="modal-overlay open" onClick={e => e.target === e.currentTarget && onClose()}>
            <div className="modal" style={{ width: 560 }}>
                <div className="modal-header"><div className="modal-title">Enter Meter Readings</div><button className="modal-close" onClick={onClose}>✕</button></div>
                <form onSubmit={submit}>
                    <div className="modal-body">
                        <div style={{ display: 'flex', gap: 12, marginBottom: 14 }}>
                            <div style={{ flex: 1 }}><label className="form-label">Reading Month</label>
                                <select className="form-input form-select" value={form.month} onChange={e => setForm(f => ({ ...f, month: e.target.value }))}>
                                    {monthOptions.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
                                </select>
                            </div>
                            <div style={{ flex: 1 }}><label className="form-label">Reading Date</label><input className="form-input" type="date" value={form.reading_date} onChange={e => setForm(f => ({ ...f, reading_date: e.target.value }))} required /></div>
                        </div>
                        <div style={{ display: 'flex', gap: 12, marginBottom: 14 }}>
                            <div style={{ flex: 1 }}><label className="form-label">Unit</label>
                                <select className="form-input form-select" value={form.unit_id} onChange={e => setForm(f => ({ ...f, unit_id: e.target.value }))} required>
                                    <option value="">Select unit...</option>
                                    {units.map(u => <option key={u.id} value={u.id}>{u.unit_number}</option>)}
                                </select>
                            </div>
                            <div style={{ flex: 1 }}><label className="form-label">Previous Reading (kWh)</label><input className="form-input" type="number" min="0" value={form.prev_reading} onChange={e => setForm(f => ({ ...f, prev_reading: e.target.value }))} required /></div>
                        </div>
                        <div style={{ display: 'flex', gap: 12, marginBottom: 14 }}>
                            <div style={{ flex: 1 }}><label className="form-label">Current Reading (kWh)</label><input className="form-input" type="number" min="0" value={form.curr_reading} onChange={e => setForm(f => ({ ...f, curr_reading: e.target.value }))} required /></div>
                            <div style={{ flex: 1 }}><label className="form-label">Consumption</label><div style={{ background: 'var(--bg-elevated)', borderRadius: 8, padding: '8px 12px', fontSize: 14, fontWeight: 700, color: 'var(--accent)' }}>{consumption !== null ? `${consumption} kWh` : '— kWh'}</div></div>
                        </div>
                        <div style={{ display: 'flex', gap: 12, marginBottom: 14 }}>
                            <div style={{ flex: 1 }}><label className="form-label">Generator kWh (optional)</label><input className="form-input" type="number" min="0" value={form.gen_kwh} onChange={e => setForm(f => ({ ...f, gen_kwh: e.target.value }))} placeholder="0" /></div>
                        </div>
                    </div>
                    <div className="modal-footer"><button type="button" className="btn-ghost" onClick={onClose}>Cancel</button><button type="submit" className="btn-primary">Save Reading</button></div>
                </form>
            </div>
        </div>
    );
}

function OutageModal({ onClose }) {
    const today = new Date().toISOString().slice(0, 10);
    const [form, setForm] = useState({
        outage_date: today, type: 'minor', start_time: '', end_time: '',
        floors_affected: 'All floors', generator_activated: false, fuel_used: '', notes: '',
    });
    const submit = (e) => {
        e.preventDefault();
        router.post('/electricity/outages', form, { onSuccess: onClose });
    };
    return (
        <div className="modal-overlay open" onClick={e => e.target === e.currentTarget && onClose()}>
            <div className="modal">
                <div className="modal-header"><div className="modal-title">Log Outage</div><button className="modal-close" onClick={onClose}>✕</button></div>
                <form onSubmit={submit}>
                    <div className="modal-body">
                        <div style={{ display: 'flex', gap: 12, marginBottom: 14 }}>
                            <div style={{ flex: 1 }}><label className="form-label">Outage Date</label><input className="form-input" type="date" value={form.outage_date} onChange={e => setForm(f => ({ ...f, outage_date: e.target.value }))} required /></div>
                            <div style={{ flex: 1 }}><label className="form-label">Type</label>
                                <select className="form-input form-select" value={form.type} onChange={e => setForm(f => ({ ...f, type: e.target.value }))} required>
                                    <option value="minor">Minor</option>
                                    <option value="major">Major</option>
                                    <option value="planned">Planned</option>
                                </select>
                            </div>
                        </div>
                        <div style={{ display: 'flex', gap: 12, marginBottom: 14 }}>
                            <div style={{ flex: 1 }}><label className="form-label">Start Time</label><input className="form-input" type="time" value={form.start_time} onChange={e => setForm(f => ({ ...f, start_time: e.target.value }))} required /></div>
                            <div style={{ flex: 1 }}><label className="form-label">End Time (leave blank if ongoing)</label><input className="form-input" type="time" value={form.end_time} onChange={e => setForm(f => ({ ...f, end_time: e.target.value }))} /></div>
                        </div>
                        <div style={{ display: 'flex', gap: 12, marginBottom: 14, alignItems: 'center' }}>
                            <div style={{ flex: 1 }}><label className="form-label">Floors Affected</label><input className="form-input" type="text" value={form.floors_affected} onChange={e => setForm(f => ({ ...f, floors_affected: e.target.value }))} placeholder="e.g. All floors, Floor 2-4" /></div>
                            <div style={{ flex: 1 }}>
                                <label className="form-label">Generator Activated</label>
                                <label style={{ display: 'flex', alignItems: 'center', gap: 8, marginTop: 4, cursor: 'pointer' }}>
                                    <input type="checkbox" checked={form.generator_activated} onChange={e => setForm(f => ({ ...f, generator_activated: e.target.checked, fuel_used: e.target.checked ? f.fuel_used : '' }))} style={{ width: 16, height: 16 }} />
                                    <span style={{ fontSize: 13 }}>Yes, generator was used</span>
                                </label>
                            </div>
                        </div>
                        {form.generator_activated && (
                            <div style={{ marginBottom: 14 }}><label className="form-label">Fuel Used (litres)</label><input className="form-input" type="number" min="0" step="0.1" value={form.fuel_used} onChange={e => setForm(f => ({ ...f, fuel_used: e.target.value }))} placeholder="0" /></div>
                        )}
                        <div><label className="form-label">Notes</label><textarea className="form-input" rows={2} value={form.notes} onChange={e => setForm(f => ({ ...f, notes: e.target.value }))} style={{ resize: 'vertical' }} /></div>
                    </div>
                    <div className="modal-footer"><button type="button" className="btn-ghost" onClick={onClose}>Cancel</button><button type="submit" className="btn-primary">Log Outage</button></div>
                </form>
            </div>
        </div>
    );
}

function FuelModal({ onClose }) {
    const today = new Date().toISOString().slice(0, 10);
    const [form, setForm] = useState({ log_date: today, litres: '', price_per_litre: 185, supplier: '', level_after: '' });
    const totalCost = form.litres && form.price_per_litre ? Math.round(Number(form.litres) * Number(form.price_per_litre)) : null;
    const submit = (e) => {
        e.preventDefault();
        router.post('/electricity/fuel', form, { onSuccess: onClose });
    };
    return (
        <div className="modal-overlay open" onClick={e => e.target === e.currentTarget && onClose()}>
            <div className="modal">
                <div className="modal-header"><div className="modal-title">Log Refuel</div><button className="modal-close" onClick={onClose}>✕</button></div>
                <form onSubmit={submit}>
                    <div className="modal-body">
                        <div style={{ display: 'flex', gap: 12, marginBottom: 14 }}>
                            <div style={{ flex: 1 }}><label className="form-label">Date</label><input className="form-input" type="date" value={form.log_date} onChange={e => setForm(f => ({ ...f, log_date: e.target.value }))} required /></div>
                            <div style={{ flex: 1 }}><label className="form-label">Litres Added</label><input className="form-input" type="number" min="0" step="0.1" value={form.litres} onChange={e => setForm(f => ({ ...f, litres: e.target.value }))} required /></div>
                        </div>
                        <div style={{ display: 'flex', gap: 12, marginBottom: 14 }}>
                            <div style={{ flex: 1 }}><label className="form-label">Price per Litre (TZS)</label><input className="form-input" type="number" min="0" value={form.price_per_litre} onChange={e => setForm(f => ({ ...f, price_per_litre: e.target.value }))} required /></div>
                            <div style={{ flex: 1 }}><label className="form-label">Supplier</label><input className="form-input" type="text" value={form.supplier} onChange={e => setForm(f => ({ ...f, supplier: e.target.value }))} placeholder="e.g. Total, Oryx" /></div>
                        </div>
                        <div style={{ display: 'flex', gap: 12, marginBottom: 14 }}>
                            <div style={{ flex: 1 }}><label className="form-label">Tank Level After (%)</label><input className="form-input" type="number" min="0" max="100" value={form.level_after} onChange={e => setForm(f => ({ ...f, level_after: e.target.value }))} required placeholder="0–100" /></div>
                            <div style={{ flex: 1 }}><label className="form-label">Total Cost</label><div style={{ background: 'var(--bg-elevated)', borderRadius: 8, padding: '8px 12px', fontSize: 14, fontWeight: 700, color: 'var(--accent)' }}>{totalCost !== null ? `TZS ${totalCost.toLocaleString()}` : '—'}</div></div>
                        </div>
                    </div>
                    <div className="modal-footer"><button type="button" className="btn-ghost" onClick={onClose}>Cancel</button><button type="submit" className="btn-primary">Save Fuel Log</button></div>
                </form>
            </div>
        </div>
    );
}
