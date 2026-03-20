import { useState } from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

const toNum = (v) => Number(v ?? 0) || 0;
const fmt = (n) => Number(n ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const fmtKES = (n) => `KES ${fmt(n)}`;

const TYPE_COLORS = {
    asset: { bg: 'var(--accent-dim)', color: 'var(--accent)' },
    liability: { bg: 'var(--red-dim)', color: 'var(--red)' },
    equity: { bg: 'rgba(139,92,246,.12)', color: '#a78bfa' },
    revenue: { bg: 'var(--green-dim)', color: 'var(--green)' },
    expense: { bg: 'var(--amber-dim)', color: 'var(--amber)' },
    contra: { bg: 'var(--bg-elevated)', color: 'var(--text-muted)' },
};

const TYPE_CLASS = {
    asset: 'asset',
    liability: 'liability',
    equity: 'equity',
    revenue: 'revenue',
    expense: 'expense',
    contra: 'contra',
};

const titleCase = (s) => (s || '').charAt(0).toUpperCase() + (s || '').slice(1);

export default function Accounting({ accounts = [], entries = [] }) {
    const [tab, setTab] = useState('coa');
    const [showAccountModal, setShowAccountModal] = useState(false);
    const [showJEModal, setShowJEModal] = useState(false);
    const [ledgerAccount, setLedgerAccount] = useState(accounts[0]?.id ?? null);
    const [search, setSearch] = useState('');

    const navItems = [
        { id: 'coa', label: 'Chart of Accounts', section: 'Accounts' },
        { id: 'je', label: 'Journal Entries', section: 'Transactions' },
        { id: 'ledger', label: 'General Ledger', section: null },
        { id: 'tb', label: 'Trial Balance', section: 'Reports' },
        { id: 'pl', label: 'P&L Statement', section: null },
        { id: 'bs', label: 'Balance Sheet', section: null },
        { id: 'cf', label: 'Cash Flow', section: null },
    ];

    return (
        <AppLayout title="Accounting">
            <div style={{ display: 'flex', flex: 1, overflow: 'hidden', height: '100%' }}>
                <aside style={{ width: 210, minWidth: 210, background: 'var(--bg-surface)', borderRight: '1px solid var(--border)', display: 'flex', flexDirection: 'column', overflowY: 'auto' }}>
                    {navItems.map(item => (
                        <div key={item.id}>
                            {item.section && <div style={{ padding: '12px 10px 4px', fontSize: 10, fontWeight: 600, letterSpacing: '.7px', textTransform: 'uppercase', color: 'var(--text-muted)' }}>{item.section}</div>}
                            <div onClick={() => setTab(item.id)} style={{ display: 'flex', alignItems: 'center', gap: 9, padding: '8px 12px', borderRadius: 8, margin: '0 4px 1px', cursor: 'pointer', color: tab === item.id ? 'var(--accent)' : 'var(--text-secondary)', background: tab === item.id ? 'var(--accent-dim)' : 'transparent', fontSize: 13, fontWeight: tab === item.id ? 500 : 450 }}>
                                {item.label}
                            </div>
                        </div>
                    ))}
                </aside>

                <main style={{ flex: 1, overflowY: 'auto', padding: 26 }}>
                    {tab === 'coa' && <CoaTab accounts={accounts} search={search} setSearch={setSearch} onAdd={() => setShowAccountModal(true)} />}
                    {tab === 'je' && <JETab entries={entries} accounts={accounts} onAdd={() => setShowJEModal(true)} />}
                    {tab === 'ledger' && <LedgerTab accounts={accounts} entries={entries} ledgerAccount={ledgerAccount} setLedgerAccount={setLedgerAccount} />}
                    {tab === 'tb' && <TrialBalanceTab accounts={accounts} entries={entries} />}
                    {tab === 'pl' && <PLTab accounts={accounts} entries={entries} />}
                    {tab === 'bs' && <BalanceSheetTab accounts={accounts} entries={entries} />}
                    {tab === 'cf' && <CashFlowTab accounts={accounts} entries={entries} />}
                </main>
            </div>

            {showAccountModal && <AccountModal onClose={() => setShowAccountModal(false)} />}
            {showJEModal && <JEModal accounts={accounts} onClose={() => setShowJEModal(false)} />}
        </AppLayout>
    );
}

function CoaTab({ accounts, search, setSearch, onAdd }) {
    const filtered = accounts.filter((a) => {
        const q = search.toLowerCase();
        return !q ||
            String(a.code ?? '').toLowerCase().includes(q) ||
            String(a.name ?? '').toLowerCase().includes(q) ||
            String(a.type ?? '').toLowerCase().includes(q);
    });

    return (
        <div>
            <div className="page-header">
                <div>
                    <div className="page-title">Chart of Accounts</div>
                    <div className="page-sub">Account master and current balances</div>
                </div>
                <div className="actions">
                    <div className="search-box">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                        <input type="text" placeholder="Search accounts..." value={search} onChange={(e) => setSearch(e.target.value)} />
                    </div>
                    <button className="btn-primary" onClick={onAdd}>+ New Account</button>
                </div>
            </div>

            <div className="card">
                <table className="ledger-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th className="num">Balance</th>
                            <th className="num">YTD Activity</th>
                        </tr>
                    </thead>
                    <tbody>
                        {filtered.map((a) => {
                            const type = String(a.type ?? '').toLowerCase();
                            const cls = TYPE_CLASS[type] || 'contra';
                            return (
                                <tr key={a.id ?? `${a.code}-${a.name}`}>
                                    <td style={{ fontWeight: 700, color: 'var(--accent)' }}>{a.code ?? '—'}</td>
                                    <td>{a.name ?? '—'}</td>
                                    <td><span className={`acc-type ${cls}`}>{titleCase(type || 'contra')}</span></td>
                                    <td className="num">{fmtKES(toNum(a.balance))}</td>
                                    <td className="num">{fmtKES(toNum(a.ytd_activity ?? a.ytd))}</td>
                                </tr>
                            );
                        })}
                        {filtered.length === 0 && <tr><td colSpan={5} style={{ textAlign: 'center', color: 'var(--text-muted)', padding: 28 }}>No accounts found</td></tr>}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function JETab({ entries, onAdd }) {
    const totals = entries.reduce((acc, e) => {
        const lines = Array.isArray(e.lines) ? e.lines : [];
        acc.debit += lines.reduce((s, l) => s + toNum(l.debit), 0);
        acc.credit += lines.reduce((s, l) => s + toNum(l.credit), 0);
        return acc;
    }, { debit: 0, credit: 0 });

    return (
        <div>
            <div className="page-header">
                <div>
                    <div className="page-title">Journal Entries</div>
                    <div className="page-sub">Post and review double-entry transactions</div>
                </div>
                <div className="actions">
                    <button className="btn-primary" onClick={onAdd}>+ New Journal Entry</button>
                </div>
            </div>

            <div className="stats-grid" style={{ marginBottom: 16 }}>
                <div className="stat-card"><div className="stat-value">{entries.length}</div><div className="stat-label">Entries</div></div>
                <div className="stat-card"><div className="stat-value">{fmtKES(totals.debit)}</div><div className="stat-label">Total Debits</div></div>
                <div className="stat-card"><div className="stat-value">{fmtKES(totals.credit)}</div><div className="stat-label">Total Credits</div></div>
                <div className="stat-card"><div className="stat-value">{fmtKES(totals.debit - totals.credit)}</div><div className="stat-label">Diff (should be 0)</div></div>
            </div>

            <div className="card">
                <table className="ledger-table">
                    <thead>
                        <tr>
                            <th>Entry #</th>
                            <th>Date</th>
                            <th>Description</th>
                            <th className="num">Debit</th>
                            <th className="num">Credit</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {entries.map((e) => {
                            const lines = Array.isArray(e.lines) ? e.lines : [];
                            const debit = lines.reduce((s, l) => s + toNum(l.debit), 0);
                            const credit = lines.reduce((s, l) => s + toNum(l.credit), 0);
                            const status = String(e.status ?? 'draft').toLowerCase();
                            return (
                                <tr key={e.id ?? e.entry_number}>
                                    <td style={{ fontWeight: 700, color: 'var(--accent)' }}>{e.entry_number ?? e.number ?? '—'}</td>
                                    <td>{e.entry_date ?? e.date ?? '—'}</td>
                                    <td>{e.description ?? '—'}</td>
                                    <td className="num">{fmtKES(debit)}</td>
                                    <td className="num">{fmtKES(credit)}</td>
                                    <td><span className={`badge ${status === 'posted' ? 'posted' : (status === 'void' ? 'void' : 'draft')}`}>{titleCase(status)}</span></td>
                                </tr>
                            );
                        })}
                        {entries.length === 0 && <tr><td colSpan={6} style={{ textAlign: 'center', color: 'var(--text-muted)', padding: 28 }}>No journal entries yet</td></tr>}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function LedgerTab({ accounts, entries, ledgerAccount, setLedgerAccount }) {
    const selected = accounts.find((a) => a.id === ledgerAccount) || accounts[0];
    const selectedCode = selected?.code;

    const rows = [];
    entries.forEach((e) => {
        const lines = Array.isArray(e.lines) ? e.lines : [];
        lines.forEach((l) => {
            if (l.account_code === selectedCode) {
                rows.push({
                    id: `${e.id}-${l.id}`,
                    date: e.entry_date ?? e.date,
                    ref: e.entry_number ?? e.number,
                    desc: e.description,
                    debit: toNum(l.debit),
                    credit: toNum(l.credit),
                });
            }
        });
    });

    rows.sort((a, b) => String(a.date).localeCompare(String(b.date)));
    let running = toNum(selected?.balance);

    return (
        <div>
            <div className="page-header">
                <div>
                    <div className="page-title">General Ledger</div>
                    <div className="page-sub">Account movement and running balance</div>
                </div>
                <div className="actions">
                    <select className="form-input form-select" style={{ minWidth: 280 }} value={selected?.id ?? ''} onChange={(e) => setLedgerAccount(Number(e.target.value))}>
                        {accounts.map((a) => <option key={a.id} value={a.id}>{a.code} - {a.name}</option>)}
                    </select>
                </div>
            </div>

            <div className="card">
                <table className="ledger-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Ref</th>
                            <th>Description</th>
                            <th className="num">Debit</th>
                            <th className="num">Credit</th>
                            <th className="num">Running Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>—</td>
                            <td>OPEN</td>
                            <td>Opening Balance</td>
                            <td className="num">—</td>
                            <td className="num">—</td>
                            <td className="num" style={{ fontWeight: 700 }}>{fmtKES(running)}</td>
                        </tr>
                        {rows.map((r) => {
                            running += r.debit - r.credit;
                            return (
                                <tr key={r.id}>
                                    <td>{r.date ?? '—'}</td>
                                    <td>{r.ref ?? '—'}</td>
                                    <td>{r.desc ?? '—'}</td>
                                    <td className="num">{fmtKES(r.debit)}</td>
                                    <td className="num">{fmtKES(r.credit)}</td>
                                    <td className="num" style={{ fontWeight: 700 }}>{fmtKES(running)}</td>
                                </tr>
                            );
                        })}
                        {rows.length === 0 && <tr><td colSpan={6} style={{ textAlign: 'center', color: 'var(--text-muted)', padding: 28 }}>No ledger activity for selected account</td></tr>}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function TrialBalanceTab({ accounts, entries }) {
    const map = new Map();
    accounts.forEach((a) => map.set(a.code, { code: a.code, name: a.name, debit: 0, credit: 0 }));
    entries.forEach((e) => {
        (e.lines || []).forEach((l) => {
            if (!map.has(l.account_code)) map.set(l.account_code, { code: l.account_code, name: l.account_name, debit: 0, credit: 0 });
            const r = map.get(l.account_code);
            r.debit += toNum(l.debit);
            r.credit += toNum(l.credit);
        });
    });
    const rows = [...map.values()].sort((a, b) => String(a.code).localeCompare(String(b.code)));
    const totalDebit = rows.reduce((s, r) => s + r.debit, 0);
    const totalCredit = rows.reduce((s, r) => s + r.credit, 0);

    return (
        <div>
            <div className="page-header">
                <div><div className="page-title">Trial Balance</div><div className="page-sub">Debit and credit totals by account</div></div>
            </div>
            <div className="card">
                <table className="ledger-table">
                    <thead><tr><th>Code</th><th>Account</th><th className="num">Debit</th><th className="num">Credit</th></tr></thead>
                    <tbody>
                        {rows.map((r) => <tr key={r.code}><td style={{ fontWeight: 700, color: 'var(--accent)' }}>{r.code}</td><td>{r.name}</td><td className="num">{fmtKES(r.debit)}</td><td className="num">{fmtKES(r.credit)}</td></tr>)}
                        <tr>
                            <td colSpan={2} style={{ fontWeight: 700 }}>Totals</td>
                            <td className="num" style={{ fontWeight: 700 }}>{fmtKES(totalDebit)}</td>
                            <td className="num" style={{ fontWeight: 700 }}>{fmtKES(totalCredit)}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function PLTab({ accounts, entries }) {
    const revenueCodes = new Set(accounts.filter((a) => a.type === 'revenue').map((a) => a.code));
    const expenseCodes = new Set(accounts.filter((a) => a.type === 'expense').map((a) => a.code));
    let revenue = 0;
    let expense = 0;

    entries.forEach((e) => {
        (e.lines || []).forEach((l) => {
            const net = toNum(l.credit) - toNum(l.debit);
            if (revenueCodes.has(l.account_code)) revenue += net;
            if (expenseCodes.has(l.account_code)) expense += -net;
        });
    });

    const netProfit = revenue - expense;
    return (
        <div>
            <div className="page-header">
                <div><div className="page-title">P&amp;L Statement</div><div className="page-sub">Income performance summary</div></div>
            </div>
            <div className="card" style={{ padding: 16 }}>
                <div className="source-kv-grid" style={{ marginBottom: 0 }}>
                    <div className="source-kv"><div className="source-kv-label">Total Revenue</div><div className="source-kv-value green">{fmtKES(revenue)}</div></div>
                    <div className="source-kv"><div className="source-kv-label">Total Expenses</div><div className="source-kv-value red">{fmtKES(expense)}</div></div>
                    <div className="source-kv"><div className="source-kv-label">Net Profit / (Loss)</div><div className={`source-kv-value ${netProfit >= 0 ? 'green' : 'red'}`}>{fmtKES(netProfit)}</div></div>
                    <div className="source-kv"><div className="source-kv-label">Margin</div><div className="source-kv-value accent">{revenue ? `${((netProfit / revenue) * 100).toFixed(1)}%` : '0.0%'}</div></div>
                </div>
            </div>
        </div>
    );
}

function BalanceSheetTab({ accounts }) {
    const totals = accounts.reduce((acc, a) => {
        const t = String(a.type || '').toLowerCase();
        const v = toNum(a.balance);
        if (t === 'asset') acc.assets += v;
        if (t === 'liability') acc.liabilities += v;
        if (t === 'equity') acc.equity += v;
        return acc;
    }, { assets: 0, liabilities: 0, equity: 0 });

    return (
        <div>
            <div className="page-header">
                <div><div className="page-title">Balance Sheet</div><div className="page-sub">Position snapshot</div></div>
            </div>
            <div className="card" style={{ padding: 16 }}>
                <div className="source-kv-grid" style={{ marginBottom: 0 }}>
                    <div className="source-kv"><div className="source-kv-label">Assets</div><div className="source-kv-value accent">{fmtKES(totals.assets)}</div></div>
                    <div className="source-kv"><div className="source-kv-label">Liabilities</div><div className="source-kv-value red">{fmtKES(totals.liabilities)}</div></div>
                    <div className="source-kv"><div className="source-kv-label">Equity</div><div className="source-kv-value amber">{fmtKES(totals.equity)}</div></div>
                    <div className="source-kv"><div className="source-kv-label">L + E</div><div className="source-kv-value green">{fmtKES(totals.liabilities + totals.equity)}</div></div>
                </div>
            </div>
        </div>
    );
}

function CashFlowTab({ entries }) {
    let operating = 0;
    entries.forEach((e) => {
        (e.lines || []).forEach((l) => {
            if (String(l.account_code).startsWith('1')) operating += toNum(l.debit) - toNum(l.credit);
        });
    });

    return (
        <div>
            <div className="page-header">
                <div><div className="page-title">Cash Flow</div><div className="page-sub">Simple movement summary</div></div>
            </div>
            <div className="card" style={{ padding: 16 }}>
                <div className="source-kv-grid" style={{ marginBottom: 0 }}>
                    <div className="source-kv"><div className="source-kv-label">Operating Cash Movement</div><div className={`source-kv-value ${operating >= 0 ? 'green' : 'red'}`}>{fmtKES(operating)}</div></div>
                    <div className="source-kv"><div className="source-kv-label">Entries Count</div><div className="source-kv-value accent">{entries.length}</div></div>
                </div>
            </div>
        </div>
    );
}

function AccountModal({ onClose }) {
    const [form, setForm] = useState({ code: '', name: '', type: 'asset', category: '', balance: 0, description: '' });
    const submit = (e) => {
        e.preventDefault();
        router.post('/accounting/accounts', form, { onSuccess: onClose });
    };

    return (
        <div className="modal-overlay open" onClick={(e) => e.target === e.currentTarget && onClose()}>
            <div className="modal" style={{ width: 560 }}>
                <div className="modal-header"><div className="modal-title">New Account</div><button className="modal-close" onClick={onClose}>✕</button></div>
                <form onSubmit={submit}>
                    <div className="modal-body">
                        <div className="form-row">
                            <div className="form-group"><label className="form-label">Code</label><input className="form-input" value={form.code} onChange={(e) => setForm((f) => ({ ...f, code: e.target.value }))} required /></div>
                            <div className="form-group"><label className="form-label">Type</label>
                                <select className="form-input form-select" value={form.type} onChange={(e) => setForm((f) => ({ ...f, type: e.target.value }))}>
                                    <option value="asset">Asset</option><option value="liability">Liability</option><option value="equity">Equity</option><option value="revenue">Revenue</option><option value="expense">Expense</option><option value="contra">Contra</option>
                                </select>
                            </div>
                        </div>
                        <div className="form-row">
                            <div className="form-group"><label className="form-label">Name</label><input className="form-input" value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} required /></div>
                            <div className="form-group"><label className="form-label">Category</label><input className="form-input" value={form.category} onChange={(e) => setForm((f) => ({ ...f, category: e.target.value }))} /></div>
                        </div>
                        <div className="form-row">
                            <div className="form-group"><label className="form-label">Opening Balance</label><input className="form-input" type="number" step="0.01" value={form.balance} onChange={(e) => setForm((f) => ({ ...f, balance: e.target.value }))} /></div>
                            <div className="form-group"><label className="form-label">Description</label><input className="form-input" value={form.description} onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))} /></div>
                        </div>
                    </div>
                    <div className="modal-footer"><button type="button" className="btn-ghost" onClick={onClose}>Cancel</button><button type="submit" className="btn-primary">Save Account</button></div>
                </form>
            </div>
        </div>
    );
}

function JEModal({ accounts, onClose }) {
    const [form, setForm] = useState({
        entry_date: new Date().toISOString().slice(0, 10),
        description: '',
        reference: '',
        status: 'draft',
        lines: [
            { account_code: accounts[0]?.code ?? '', account_name: accounts[0]?.name ?? '', debit: 0, credit: 0 },
            { account_code: accounts[1]?.code ?? '', account_name: accounts[1]?.name ?? '', debit: 0, credit: 0 },
        ],
    });

    const setLine = (idx, patch) => {
        setForm((f) => ({
            ...f,
            lines: f.lines.map((l, i) => (i === idx ? { ...l, ...patch } : l)),
        }));
    };

    const addLine = () => setForm((f) => ({ ...f, lines: [...f.lines, { account_code: '', account_name: '', debit: 0, credit: 0 }] }));
    const removeLine = (idx) => setForm((f) => ({ ...f, lines: f.lines.filter((_, i) => i !== idx) }));

    const debitTotal = form.lines.reduce((s, l) => s + toNum(l.debit), 0);
    const creditTotal = form.lines.reduce((s, l) => s + toNum(l.credit), 0);
    const balanced = Math.abs(debitTotal - creditTotal) < 0.005;

    const submit = (e) => {
        e.preventDefault();
        if (!balanced) return;
        router.post('/accounting/journal-entries', form, { onSuccess: onClose });
    };

    return (
        <div className="modal-overlay open" onClick={(e) => e.target === e.currentTarget && onClose()}>
            <div className="modal" style={{ width: 760, maxWidth: 'calc(100vw - 40px)' }}>
                <div className="modal-header"><div className="modal-title">New Journal Entry</div><button className="modal-close" onClick={onClose}>✕</button></div>
                <form onSubmit={submit}>
                    <div className="modal-body">
                        <div className="form-row">
                            <div className="form-group"><label className="form-label">Date</label><input className="form-input" type="date" value={form.entry_date} onChange={(e) => setForm((f) => ({ ...f, entry_date: e.target.value }))} required /></div>
                            <div className="form-group"><label className="form-label">Reference</label><input className="form-input" value={form.reference} onChange={(e) => setForm((f) => ({ ...f, reference: e.target.value }))} /></div>
                            <div className="form-group"><label className="form-label">Status</label><select className="form-input form-select" value={form.status} onChange={(e) => setForm((f) => ({ ...f, status: e.target.value }))}><option value="draft">Draft</option><option value="posted">Posted</option></select></div>
                        </div>
                        <div style={{ marginBottom: 14 }}><label className="form-label">Description</label><input className="form-input" value={form.description} onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))} required /></div>

                        <div className="card" style={{ marginBottom: 12 }}>
                            <table className="ledger-table">
                                <thead><tr><th>Account</th><th className="num">Debit</th><th className="num">Credit</th><th></th></tr></thead>
                                <tbody>
                                    {form.lines.map((l, i) => (
                                        <tr key={i}>
                                            <td>
                                                <select className="form-input form-select" value={l.account_code} onChange={(e) => {
                                                    const acc = accounts.find((a) => a.code === e.target.value);
                                                    setLine(i, { account_code: acc?.code ?? '', account_name: acc?.name ?? '' });
                                                }}>
                                                    <option value="">Select account...</option>
                                                    {accounts.map((a) => <option key={a.id} value={a.code}>{a.code} - {a.name}</option>)}
                                                </select>
                                            </td>
                                            <td className="num"><input className="form-input" type="number" step="0.01" value={l.debit} onChange={(e) => setLine(i, { debit: e.target.value })} /></td>
                                            <td className="num"><input className="form-input" type="number" step="0.01" value={l.credit} onChange={(e) => setLine(i, { credit: e.target.value })} /></td>
                                            <td><button type="button" className="btn-ghost" onClick={() => removeLine(i)} disabled={form.lines.length <= 2}>Remove</button></td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <div className="actions" style={{ justifyContent: 'space-between' }}>
                            <button type="button" className="btn-ghost" onClick={addLine}>+ Add Line</button>
                            <div style={{ fontSize: 13, color: balanced ? 'var(--green)' : 'var(--red)' }}>
                                Debits: {fmtKES(debitTotal)} | Credits: {fmtKES(creditTotal)}
                            </div>
                        </div>
                    </div>
                    <div className="modal-footer">
                        <button type="button" className="btn-ghost" onClick={onClose}>Cancel</button>
                        <button type="submit" className="btn-primary" disabled={!balanced}>Save Entry</button>
                    </div>
                </form>
            </div>
        </div>
    );
}
