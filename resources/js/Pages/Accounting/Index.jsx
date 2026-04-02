import { Fragment, useEffect, useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

const toNum = (v) => Number(v ?? 0) || 0;

const currentYear = new Date().getFullYear();
const periodOptions = [-1, 0, 1].flatMap((offset) => {
    const y = currentYear + offset;
    return [
        { value: `Q1-${y}`, label: `Q1 ${y} (Jan–Mar)` },
        { value: `Q2-${y}`, label: `Q2 ${y} (Apr–Jun)` },
        { value: `Q3-${y}`, label: `Q3 ${y} (Jul–Sep)` },
        { value: `Q4-${y}`, label: `Q4 ${y} (Oct–Dec)` },
        { value: `FY-${y}`, label: `Full Year ${y}` },
    ];
});
const defaultPeriod = () => {
    const m = new Date().getMonth();
    const q = m < 3 ? 'Q1' : m < 6 ? 'Q2' : m < 9 ? 'Q3' : 'Q4';
    return `${q}-${new Date().getFullYear()}`;
};
const fmtMoney = (v) => `TZS ${toNum(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const titleCase = (s) => (s || '').charAt(0).toUpperCase() + (s || '').slice(1);

const NAV = [
    { id: 'coa', section: 'Books', label: 'Chart of Accounts' },
    { id: 'je', section: null, label: 'Journal Entries' },
    { id: 'gl', section: null, label: 'General Ledger' },
    { id: 'tb', section: 'Reports', label: 'Trial Balance' },
    { id: 'pl', section: null, label: 'Profit & Loss' },
    { id: 'bs', section: null, label: 'Balance Sheet' },
    { id: 'cf', section: null, label: 'Cash Flow' },
];

function normalizeAccounts(input) {
    if (!Array.isArray(input) || input.length === 0) return [];
    return input.map((a) => ({
        code: String(a.code ?? ''),
        name: a.name ?? '-',
        type: String(a.type ?? 'asset').toLowerCase(),
        cat: a.category ?? a.cat ?? titleCase(String(a.type ?? 'asset')),
        balance: toNum(a.balance),
        ytd: toNum(a.ytd_activity ?? a.ytd),
        description: a.description ?? '',
    })).sort((a, b) => a.code.localeCompare(b.code));
}

function normalizeEntries(input) {
    if (!Array.isArray(input) || input.length === 0) return [];
    return input.map((e, idx) => {
        const raw = e.entry_number ?? e.id ?? `JE-${String(idx + 1).padStart(3, '0')}`;
        const id = String(raw).startsWith('JE-') ? String(raw) : `JE-${String(raw).padStart(3, '0')}`;
        return {
            id,
            date: e.entry_date ?? e.date ?? new Date().toISOString().slice(0, 10),
            desc: e.description ?? e.desc ?? '-',
            ref: e.reference ?? e.ref ?? id,
            status: String(e.status ?? 'draft').toLowerCase(),
            lines: (e.lines ?? []).map((l) => ({
                acct: l.account_code ?? l.acct ?? '',
                name: l.account_name ?? l.name ?? '',
                dr: toNum(l.debit ?? l.dr),
                cr: toNum(l.credit ?? l.cr),
            })),
        };
    });
}

function groupByCategory(list) {
    const grouped = {};
    list.forEach((a) => {
        if (!grouped[a.cat]) grouped[a.cat] = [];
        grouped[a.cat].push(a);
    });
    return grouped;
}

function displayBalance(account) {
    const bal = toNum(account.balance);
    if (account.type === 'liability' || account.type === 'equity' || account.type === 'revenue') {
        return -bal;
    }
    return bal;
}

function statusClass(s) {
    if (s === 'posted') return 'posted';
    if (s === 'void') return 'void';
    return 'draft';
}

function parsePeriod(p) {
    if (!p) return { from: null, to: null };
    const [part, yearStr] = p.split('-');
    const y = parseInt(yearStr, 10);
    if (part === 'Q1') return { from: `${y}-01-01`, to: `${y}-03-31` };
    if (part === 'Q2') return { from: `${y}-04-01`, to: `${y}-06-30` };
    if (part === 'Q3') return { from: `${y}-07-01`, to: `${y}-09-30` };
    if (part === 'Q4') return { from: `${y}-10-01`, to: `${y}-12-31` };
    if (part === 'FY') return { from: `${y}-01-01`, to: `${y}-12-31` };
    return { from: null, to: null };
}

function periodLabel(p) {
    if (!p) return 'All Time';
    const [part, year] = p.split('-');
    const ends = { Q1: '31 March', Q2: '30 June', Q3: '30 September', Q4: '31 December' };
    if (ends[part]) return `For the period ending ${ends[part]} ${year}`;
    if (part === 'FY') return `Full Year ${year}`;
    return p;
}

function periodAsAt(p) {
    if (!p) return 'All Time';
    const [part, year] = p.split('-');
    const ends = { Q1: '31 March', Q2: '30 June', Q3: '30 September', Q4: '31 December' };
    if (ends[part]) return `As at ${ends[part]} ${year}`;
    if (part === 'FY') return `As at 31 December ${year}`;
    return p;
}

export default function Accounting({ accounts = [], entries = [] }) {
    const [active, setActive] = useState('coa');
    const [period, setPeriod] = useState(defaultPeriod);

    const [accountData, setAccountData] = useState(() => normalizeAccounts(accounts));
    const [entryData, setEntryData] = useState(() => normalizeEntries(entries));

    const [coaSearch, setCoaSearch] = useState('');
    const [jeSearch, setJeSearch] = useState('');
    const [jeFilter, setJeFilter] = useState('');
    const [glFilter, setGlFilter] = useState('');
    const [expanded, setExpanded] = useState({});

    const [showAccountModal, setShowAccountModal] = useState(false);
    const [showJEModal, setShowJEModal] = useState(false);

    const [accountForm, setAccountForm] = useState({ code: '', name: '', type: '', cat: '', opening: '', desc: '' });
    const [jeForm, setJeForm] = useState({ date: new Date().toISOString().slice(0, 10), ref: '', desc: '', lines: [{ acct: '', dr: 0, cr: 0 }, { acct: '', dr: 0, cr: 0 }] });

    const [toast, setToast] = useState('');

    useEffect(() => {
        setAccountData(normalizeAccounts(accounts));
    }, [accounts]);

    useEffect(() => {
        setEntryData(normalizeEntries(entries));
    }, [entries]);

    useEffect(() => {
        if (!toast) return undefined;
        const t = setTimeout(() => setToast(''), 2200);
        return () => clearTimeout(t);
    }, [toast]);

    const nextEntryId = (list) => {
        const max = list.reduce((m, e) => {
            const hit = String(e.id).match(/(\d+)/);
            return Math.max(m, hit ? Number(hit[1]) : 0);
        }, 0);
        return `JE-${String(max + 1).padStart(3, '0')}`;
    };

    const refreshAll = () => {
        router.reload({ only: ['accounts', 'entries'] });
        setToast('Data refreshed from server');
    };

    const filteredAccounts = useMemo(() => {
        const q = coaSearch.toLowerCase();
        return accountData.filter((a) => !q || a.code.toLowerCase().includes(q) || a.name.toLowerCase().includes(q) || String(a.cat).toLowerCase().includes(q));
    }, [accountData, coaSearch]);

    const groupedAccounts = useMemo(() => groupByCategory(filteredAccounts), [filteredAccounts]);

    const filteredEntries = useMemo(() => {
        const q = jeSearch.toLowerCase();
        return entryData.filter((je) => {
            const statusOk = !jeFilter || je.status === jeFilter;
            const qOk = !q || je.id.toLowerCase().includes(q) || je.desc.toLowerCase().includes(q) || String(je.ref).toLowerCase().includes(q);
            return statusOk && qOk;
        });
    }, [entryData, jeSearch, jeFilter]);

    const jeStats = useMemo(() => {
        const total = entryData.length;
        const posted = entryData.filter((e) => e.status === 'posted').length;
        return `${total} entries | ${posted} posted | ${total - posted} drafts/void`;
    }, [entryData]);

    const glAccounts = useMemo(() => {
        if (glFilter) return accountData.filter((a) => a.code === glFilter);
        const defaults = new Set(['1000', '1100', '4000', '5000', '5600']);
        return accountData.filter((a) => defaults.has(a.code));
    }, [accountData, glFilter]);

    const trialRows = useMemo(() => {
        return accountData.map((a) => {
            const debitNormal = a.type === 'asset' || a.type === 'expense' || (a.type === 'contra' && a.balance < 0);
            const dr = debitNormal && a.balance >= 0 ? a.balance : 0;
            const cr = (!debitNormal || a.balance < 0) ? Math.abs(a.balance) : 0;
            return { ...a, dr, cr };
        });
    }, [accountData]);

    const plData = useMemo(() => {
        const { from, to } = parsePeriod(period);

        const filtered = entryData.filter(
            (e) =>
                e.status === 'posted' &&
                (from === null || e.date >= from) &&
                (to === null || e.date <= to),
        );

        const lineAmounts = new Map();
        filtered.forEach((entry) => {
            entry.lines.forEach((line) => {
                const curr = lineAmounts.get(line.acct) ?? { dr: 0, cr: 0 };
                lineAmounts.set(line.acct, { dr: curr.dr + line.dr, cr: curr.cr + line.cr });
            });
        });

        const revenue = accountData
            .filter((a) => a.type === 'revenue')
            .map((a) => {
                const { dr = 0, cr = 0 } = lineAmounts.get(a.code) ?? {};
                return { ...a, ytd_display: cr - dr };
            })
            .filter((a) => a.ytd_display !== 0);

        const expense = accountData
            .filter((a) => a.type === 'expense')
            .map((a) => {
                const { dr = 0, cr = 0 } = lineAmounts.get(a.code) ?? {};
                return { ...a, ytd_display: dr - cr };
            })
            .filter((a) => a.ytd_display !== 0);

        const totalRevenue = revenue.reduce((s, a) => s + a.ytd_display, 0);
        const totalExpense = expense.reduce((s, a) => s + a.ytd_display, 0);
        const operating = expense
            .filter((a) => a.cat === 'Operating Expenses')
            .reduce((s, a) => s + a.ytd_display, 0);
        return { revenue, expense, totalRevenue, totalExpense, gross: totalRevenue - operating, net: totalRevenue - totalExpense };
    }, [accountData, entryData, period]);

    const bsData = useMemo(() => {
        const assets = accountData.filter((a) => a.type === 'asset' || a.type === 'contra').map((a) => ({ ...a, balance: displayBalance(a) }));
        const liabilities = accountData.filter((a) => a.type === 'liability').map((a) => ({ ...a, balance: displayBalance(a) }));
        const equityBase = accountData.filter((a) => a.type === 'equity').map((a) => ({ ...a, balance: displayBalance(a) }));

        // Current Period Earnings = net income derived from ALL posted GL lines (same scope
        // as account balances which are cumulative). Revenue credits minus debits, minus
        // expense debits minus credits — matches the accounting equation Assets = L + E.
        const allPosted = entryData.filter((e) => e.status === 'posted');
        const allLineAmounts = new Map();
        allPosted.forEach((entry) => {
            entry.lines.forEach((line) => {
                const curr = allLineAmounts.get(line.acct) ?? { dr: 0, cr: 0 };
                allLineAmounts.set(line.acct, { dr: curr.dr + line.dr, cr: curr.cr + line.cr });
            });
        });
        const totalRevenue = accountData
            .filter((a) => a.type === 'revenue')
            .reduce((s, a) => {
                const { dr = 0, cr = 0 } = allLineAmounts.get(a.code) ?? {};
                return s + (cr - dr);
            }, 0);
        const totalExpense = accountData
            .filter((a) => a.type === 'expense')
            .reduce((s, a) => {
                const { dr = 0, cr = 0 } = allLineAmounts.get(a.code) ?? {};
                return s + (dr - cr);
            }, 0);
        const currentEarnings = totalRevenue - totalExpense;

        const equity = [
            ...equityBase,
            {
                code: 'CURR-EARN',
                name: 'Current Period Earnings',
                type: 'equity',
                cat: 'Equity',
                balance: currentEarnings,
                ytd: currentEarnings,
            },
        ];

        const totalAssets = assets.reduce((s, a) => s + a.balance, 0);
        const totalLiabilities = liabilities.reduce((s, a) => s + a.balance, 0);
        const totalEquity = equity.reduce((s, a) => s + a.balance, 0);

        return {
            assets,
            liabilities,
            equity,
            totalAssets,
            totalLiabilities,
            totalEquity,
            difference: totalAssets - (totalLiabilities + totalEquity),
        };
    }, [accountData, entryData, plData]);

    const cfData = useMemo(() => {
        const accountByCode = new Map(accountData.map((a) => [a.code, a]));
        const cashCodes = new Set(
            accountData
                .filter((a) => a.type === 'asset' && /cash/i.test(a.name))
                .map((a) => a.code)
        );

        const rows = {
            operating: [],
            investing: [],
            financing: [],
        };

        const { from: cfFrom, to: cfTo } = parsePeriod(period);
        const postedEntries = entryData.filter(
            (e) =>
                e.status === 'posted' &&
                (cfFrom === null || e.date >= cfFrom) &&
                (cfTo === null || e.date <= cfTo),
        );

        postedEntries.forEach((entry) => {
            const cashDelta = entry.lines
                .filter((line) => cashCodes.has(line.acct))
                .reduce((sum, line) => sum + toNum(line.dr) - toNum(line.cr), 0);

            if (Math.abs(cashDelta) < 0.01) {
                return;
            }

            const counterpartAccounts = entry.lines
                .filter((line) => !cashCodes.has(line.acct))
                .map((line) => accountByCode.get(line.acct))
                .filter(Boolean);

            const hasFixedAsset = counterpartAccounts.some((a) => a.type === 'asset' && /fixed/i.test(String(a.cat || '')));
            const hasFinancing = counterpartAccounts.some(
                (a) => a.type === 'equity' || (a.type === 'liability' && /long-term|loan|mortgage|finance/i.test(String(a.cat || '') + ' ' + a.name))
            );

            const bucket = hasFixedAsset ? 'investing' : (hasFinancing ? 'financing' : 'operating');
            rows[bucket].push({ desc: entry.desc, amount: cashDelta });
        });

        const aggregate = (list) => Object.values(list.reduce((acc, row) => {
            if (!acc[row.desc]) {
                acc[row.desc] = { desc: row.desc, amount: 0 };
            }
            acc[row.desc].amount += row.amount;
            return acc;
        }, {}));

        const operating = aggregate(rows.operating);
        const investing = aggregate(rows.investing);
        const financing = aggregate(rows.financing);

        const tO = operating.reduce((s, i) => s + i.amount, 0);
        const tI = investing.reduce((s, i) => s + i.amount, 0);
        const tF = financing.reduce((s, i) => s + i.amount, 0);
        const net = tO + tI + tF;
        const close = accountData
            .filter((a) => cashCodes.has(a.code))
            .reduce((sum, a) => sum + toNum(a.balance), 0);
        const open = close - net;

        return { operating, investing, financing, tO, tI, tF, net, open, close };
    }, [accountData, entryData, period]);

    const jeTotals = useMemo(() => {
        const dr = jeForm.lines.reduce((s, l) => s + toNum(l.dr), 0);
        const cr = jeForm.lines.reduce((s, l) => s + toNum(l.cr), 0);
        return { dr, cr, balanced: Math.abs(dr - cr) < 0.01 && dr > 0 };
    }, [jeForm.lines]);

    const openJEModal = () => {
        setJeForm({ date: new Date().toISOString().slice(0, 10), ref: '', desc: '', lines: [{ acct: '', dr: 0, cr: 0 }, { acct: '', dr: 0, cr: 0 }] });
        setShowJEModal(true);
    };

    const addJELine = () => setJeForm((f) => ({ ...f, lines: [...f.lines, { acct: '', dr: 0, cr: 0 }] }));

    const updateJELine = (idx, patch) => {
        setJeForm((f) => ({
            ...f,
            lines: f.lines.map((l, i) => {
                if (i !== idx) return l;
                const next = { ...l, ...patch };
                if (patch.dr !== undefined) next.cr = 0;
                if (patch.cr !== undefined) next.dr = 0;
                return next;
            }),
        }));
    };

    const removeJELine = (idx) => setJeForm((f) => ({ ...f, lines: f.lines.filter((_, i) => i !== idx) }));

    const submitJE = (status) => {
        if (!jeForm.date || !jeForm.desc.trim()) return setToast('Please fill date and description');
        if (!jeTotals.balanced) return setToast('Entry is not balanced');
        if (jeForm.lines.some((l) => !l.acct)) return setToast('Select account on every line');

        const id = nextEntryId(entryData);
        const lines = jeForm.lines.map((l) => {
            const acc = accountData.find((a) => a.code === l.acct);
            return { acct: l.acct, name: acc?.name || l.acct, dr: toNum(l.dr), cr: toNum(l.cr) };
        });
        const payload = {
            id,
            date: jeForm.date,
            desc: jeForm.desc.trim(),
            ref: jeForm.ref.trim() || id,
            status,
            lines,
        };

        setEntryData((prev) => [payload, ...prev]);
        setShowJEModal(false);
        setToast(`Journal entry ${id} ${status === 'posted' ? 'posted' : 'saved as draft'}`);

        router.post('/accounting/journal-entries', {
            entry_date: payload.date,
            description: payload.desc,
            reference: payload.ref,
            status: payload.status,
            lines: payload.lines.map((l) => ({ account_code: l.acct, account_name: l.name, debit: l.dr, credit: l.cr })),
        }, { preserveState: true, preserveScroll: true });
    };

    const submitAccount = () => {
        const code = accountForm.code.trim();
        const name = accountForm.name.trim();
        if (!code || !name || !accountForm.type) return setToast('Required fields missing');
        if (accountData.some((a) => a.code === code)) return setToast('Account code already exists');

        const rec = {
            code,
            name,
            type: accountForm.type,
            cat: accountForm.cat.trim() || titleCase(accountForm.type),
            balance: toNum(accountForm.opening),
            ytd: 0,
            description: accountForm.desc.trim(),
        };

        setAccountData((prev) => [...prev, rec].sort((a, b) => a.code.localeCompare(b.code)));
        setAccountForm({ code: '', name: '', type: '', cat: '', opening: '', desc: '' });
        setShowAccountModal(false);
        setToast(`Account ${code} added`);

        router.post('/accounting/accounts', {
            code: rec.code,
            name: rec.name,
            type: rec.type,
            category: rec.cat,
            balance: rec.balance,
            description: rec.description,
        }, { preserveState: true, preserveScroll: true });
    };

    const printCurrent = () => {
        const section = document.getElementById(`acc-print-${active}`);
        if (!section) return;
        const title = NAV.find((n) => n.id === active)?.label || 'Accounting Report';
        const w = window.open('', '_blank', 'width=920,height=700');
        if (!w) return;
        w.document.write(`<!DOCTYPE html><html><head><title>${title}</title><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet"><style>body{font-family:'DM Sans',sans-serif;color:#111;padding:32px;font-size:13px}h1{font-size:20px;margin:0 0 4px}p{color:#666;margin:0 0 20px}table{width:100%;border-collapse:collapse}th{text-align:left;font-size:10px;text-transform:uppercase;color:#999;letter-spacing:.5px;padding:8px 12px;border-bottom:2px solid #e5e7eb}td{padding:8px 12px;border-bottom:1px solid #f3f4f6;font-size:12.5px}.num{text-align:right;font-variant-numeric:tabular-nums}.rpt-section{background:#f9fafb;padding:7px 12px;font-size:10px;font-weight:700;text-transform:uppercase;color:#999;border-bottom:1px solid #e5e7eb}.rpt-group{padding:7px 12px 7px 24px;font-size:12px;font-weight:600;color:#555;border-bottom:1px solid #f3f4f6}.rpt-line{display:flex;justify-content:space-between;padding:7px 12px 7px 36px;border-bottom:1px solid #f3f4f6}.rpt-total{display:flex;justify-content:space-between;padding:10px 12px;font-weight:700;border-top:2px solid #e5e7eb}.rpt-grand{display:flex;justify-content:space-between;padding:12px;font-weight:800;background:#eff6ff;color:#2563eb}</style></head><body><h1>Rucky Rentals - ${title}</h1><p>Generated: ${new Date().toLocaleDateString()} | ${period}</p>${section.innerHTML}<script>window.onload=function(){window.print()}<\/script></body></html>`);
        w.document.close();
    };

    const renderPageBarActions = ({ extra = null, showNewJE = false, showExport = false } = {}) => (
        <>
            {extra}
            {showNewJE && <button className="btn-primary" onClick={openJEModal}>+ New Journal Entry</button>}
            {showExport && <button className="btn-ghost" onClick={printCurrent}>Export PDF</button>}
        </>
    );

    const renderPageControls = () => (
        <>
            <div className="period-select">
                <select value={period} onChange={(e) => { setPeriod(e.target.value); refreshAll(); }}>
                    {periodOptions.map((opt) => (
                        <option key={opt.value} value={opt.value}>{opt.label}</option>
                    ))}
                </select>
            </div>
            <button className="icon-btn" onClick={refreshAll} title="Refresh from dashboard" style={{ color: 'var(--green)' }}>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
            </button>
        </>
    );

    return (
        <AppLayout title="Accounting">
            <div className="acc-layout">
                <aside className="acc-sidebar">
                    {NAV.map((n) => (
                        <div key={n.id}>
                            {n.section && <div className="acc-nav-section">{n.section}</div>}
                            <div className={`acc-nav-item ${active === n.id ? 'active' : ''}`} onClick={() => setActive(n.id)}>{n.label}</div>
                        </div>
                    ))}
                </aside>

                <main className="acc-content">
                    <div className={`acc-page ${active === 'coa' ? 'active' : ''}`} id="acc-print-coa">
                        <SectionHeader title="Chart of Accounts" subtitle="All account codes used in the general ledger">
                            {renderPageBarActions({
                                extra: (
                                    <>
                                        <div className="search-box"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg><input type="text" placeholder="Search accounts..." value={coaSearch} onChange={(e) => setCoaSearch(e.target.value)} /></div>
                                        {renderPageControls()}
                                        <button className="btn-primary" onClick={() => setShowAccountModal(true)}>+ Add Account</button>
                                    </>
                                ),
                            })}
                        </SectionHeader>

                        <div className="card">
                            <table className="ledger-table">
                                <thead><tr><th>Code</th><th>Account Name</th><th>Type</th><th>Category</th><th className="num">Balance</th><th className="num">YTD Activity</th><th></th></tr></thead>
                                <tbody>
                                    {Object.keys(groupedAccounts).length === 0 && <tr><td colSpan={7} style={{ textAlign: 'center', padding: 32, color: 'var(--text-muted)' }}>No accounts found.</td></tr>}
                                    {Object.entries(groupedAccounts).map(([cat, list]) => (
                                        <Fragment key={cat}>
                                            <tr style={{ background: 'var(--bg-elevated)' }}><td colSpan={7} style={{ fontSize: 11, fontWeight: 700, letterSpacing: '.5px', textTransform: 'uppercase', color: 'var(--text-muted)', padding: '7px 16px' }}>{cat}</td></tr>
                                            {list.map((a) => (
                                                <tr key={a.code}>
                                                    <td style={{ fontWeight: 700, color: 'var(--accent)', fontFamily: 'monospace', paddingLeft: 16 }}>{a.code}</td>
                                                    <td style={{ fontWeight: 600 }}>{a.name}</td>
                                                    <td><span className={`acc-type ${a.type}`}>{titleCase(a.type)}</span></td>
                                                    <td style={{ fontSize: 12.5, color: 'var(--text-secondary)' }}>{a.cat}</td>
                                                    <td className="num" style={{ color: displayBalance(a) < 0 ? 'var(--red)' : undefined }}>{fmtMoney(displayBalance(a))}</td>
                                                    <td className="num" style={{ color: a.ytd > 0 ? 'var(--green)' : (a.ytd < 0 ? 'var(--red)' : 'var(--text-muted)') }}>{`${a.ytd >= 0 ? '+' : '-'}${fmtMoney(Math.abs(a.ytd))}`}</td>
                                                    <td><button className="btn-ghost" style={{ padding: '4px 10px', fontSize: 12 }} onClick={() => { setGlFilter(a.code); setActive('gl'); }}>-&gt; GL</button></td>
                                                </tr>
                                            ))}
                                        </Fragment>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div className={`acc-page ${active === 'je' ? 'active' : ''}`} id="acc-print-je">
                        <SectionHeader title="Journal Entries" subtitle={jeStats}>
                            {renderPageBarActions({
                                showNewJE: true,
                                extra: (
                                    <>
                                        <div className="search-box"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg><input type="text" placeholder="Search entries..." value={jeSearch} onChange={(e) => setJeSearch(e.target.value)} /></div>
                                        <select className="form-input form-select" value={jeFilter} onChange={(e) => setJeFilter(e.target.value)} style={{ width: 110, padding: '6px 28px 6px 10px', fontSize: 12.5 }}><option value="">All</option><option value="posted">Posted</option><option value="draft">Draft</option><option value="void">Void</option></select>
                                        {renderPageControls()}
                                    </>
                                ),
                            })}
                        </SectionHeader>

                        <div className="card">
                            <table className="ledger-table">
                                <thead><tr><th style={{ paddingLeft: 16 }}>Entry #</th><th>Date</th><th>Description</th><th>Reference</th><th>Lines</th><th className="num" style={{ color: 'var(--accent)' }}>Debit (TZS)</th><th className="num" style={{ color: 'var(--green)' }}>Credit (TZS)</th><th>Status</th><th></th></tr></thead>
                                <tbody>
                                    {filteredEntries.length === 0 && <tr><td colSpan={9} style={{ textAlign: 'center', padding: 32, color: 'var(--text-muted)' }}>No entries found.</td></tr>}
                                    {filteredEntries.map((je) => {
                                        const dr = je.lines.reduce((s, l) => s + toNum(l.dr), 0);
                                        const cr = je.lines.reduce((s, l) => s + toNum(l.cr), 0);
                                        const open = !!expanded[je.id];
                                        return (
                                            <Fragment key={je.id}>
                                                <tr onClick={() => setExpanded((p) => ({ ...p, [je.id]: !p[je.id] }))}>
                                                    <td style={{ paddingLeft: 16, fontWeight: 700, color: 'var(--accent)' }}>{je.id}</td>
                                                    <td style={{ color: 'var(--text-secondary)', fontSize: 12.5 }}>{je.date}</td>
                                                    <td style={{ fontWeight: 500 }}>{je.desc}</td>
                                                    <td style={{ fontSize: 12, color: 'var(--text-muted)' }}>{je.ref}</td>
                                                    <td style={{ fontSize: 12.5, color: 'var(--text-muted)' }}>{je.lines.length} lines</td>
                                                    <td className="num" style={{ color: 'var(--accent)' }}>{fmtMoney(dr)}</td>
                                                    <td className="num" style={{ color: 'var(--green)' }}>{fmtMoney(cr)}</td>
                                                    <td><span className={`je-status ${statusClass(je.status)}`}>{titleCase(je.status)}</span></td>
                                                    <td style={{ paddingRight: 16, fontSize: 11, color: 'var(--text-muted)' }}>{open ? '^' : 'v'}</td>
                                                </tr>
                                                {open && (
                                                    <tr style={{ background: 'var(--bg-elevated)' }}>
                                                        <td colSpan={9} style={{ padding: '8px 16px 12px 40px' }}>
                                                            <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                                                                <thead><tr><th style={{ textAlign: 'left', fontSize: 10, fontWeight: 700, letterSpacing: '.5px', textTransform: 'uppercase', color: 'var(--text-muted)', padding: '5px 8px', borderBottom: '1px solid var(--border-subtle)' }}>Account</th><th style={{ textAlign: 'right', fontSize: 10, fontWeight: 700, textTransform: 'uppercase', color: 'var(--accent)', padding: '5px 8px', borderBottom: '1px solid var(--border-subtle)' }}>Debit</th><th style={{ textAlign: 'right', fontSize: 10, fontWeight: 700, textTransform: 'uppercase', color: 'var(--green)', padding: '5px 8px', borderBottom: '1px solid var(--border-subtle)' }}>Credit</th></tr></thead>
                                                                <tbody>
                                                                    {je.lines.map((l, i) => (
                                                                        <tr key={`${je.id}-${i}`}>
                                                                            <td style={{ padding: '5px 8px', fontSize: 13 }}>{l.acct} - {l.name}</td>
                                                                            <td style={{ textAlign: 'right', padding: '5px 8px', fontSize: 13, color: 'var(--accent)', fontVariantNumeric: 'tabular-nums' }}>{l.dr ? fmtMoney(l.dr) : '-'}</td>
                                                                            <td style={{ textAlign: 'right', padding: '5px 8px', fontSize: 13, color: 'var(--green)', fontVariantNumeric: 'tabular-nums' }}>{l.cr ? fmtMoney(l.cr) : '-'}</td>
                                                                        </tr>
                                                                    ))}
                                                                </tbody>
                                                            </table>
                                                        </td>
                                                    </tr>
                                                )}
                                            </Fragment>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div className={`acc-page ${active === 'gl' ? 'active' : ''}`} id="acc-print-gl">
                        <SectionHeader title="General Ledger" subtitle="Transactions by account with running balances">
                            {renderPageBarActions({
                                extra: (
                                    <>
                                        <select className="form-input form-select" value={glFilter} onChange={(e) => setGlFilter(e.target.value)} style={{ width: 240, padding: '6px 28px 6px 10px', fontSize: 12.5 }}>
                                            <option value="">All Key Accounts</option>
                                            {accountData.map((a) => <option key={a.code} value={a.code}>{a.code} - {a.name}</option>)}
                                        </select>
                                        {renderPageControls()}
                                    </>
                                ),
                            })}
                        </SectionHeader>

                        {glAccounts.map((acct) => {
                            const lines = [];
                            entryData.filter((je) => je.status !== 'void').forEach((je) => {
                                je.lines.filter((l) => l.acct === acct.code).forEach((l) => lines.push({ dr: l.dr, cr: l.cr, date: je.date, desc: je.desc, ref: je.id }));
                            });
                            lines.sort((a, b) => String(a.date).localeCompare(String(b.date)));
                            let running = 0;
                            const debitNormal = acct.type === 'asset' || acct.type === 'expense';
                            return (
                                <div className="card" key={acct.code} style={{ marginBottom: 16 }}>
                                    <div className="gl-account-header">
                                        <div>
                                            <div className="gl-account-code">{acct.code}</div>
                                            <div className="gl-account-name">{acct.name}</div>
                                            <div className="gl-account-type">{acct.cat}</div>
                                        </div>
                                        <div className="gl-running-balance">
                                            <div className="gl-balance-label">Closing Balance</div>
                                            <div className="gl-balance-value" style={{ color: running < 0 ? 'var(--red)' : 'var(--green)' }}>{fmtMoney(lines.length ? running : acct.balance)}</div>
                                        </div>
                                    </div>
                                    <table className="ledger-table">
                                        <thead><tr><th>Date</th><th>Description</th><th>Reference</th><th className="num" style={{ color: 'var(--accent)' }}>Debit</th><th className="num" style={{ color: 'var(--green)' }}>Credit</th><th className="num">Balance</th></tr></thead>
                                        <tbody>
                                            {lines.length === 0 && <tr><td colSpan={6} style={{ textAlign: 'center', padding: 20, color: 'var(--text-muted)' }}>No transactions in period</td></tr>}
                                            {lines.map((l, i) => {
                                                running += debitNormal ? (l.dr - l.cr) : (l.cr - l.dr);
                                                return (
                                                    <tr key={`${acct.code}-${i}`}>
                                                        <td style={{ fontSize: 12.5, color: 'var(--text-muted)' }}>{l.date}</td>
                                                        <td>{l.desc}</td>
                                                        <td style={{ fontSize: 12, color: 'var(--text-muted)' }}>{l.ref}</td>
                                                        <td className="num" style={{ color: 'var(--accent)' }}>{l.dr ? fmtMoney(l.dr) : '-'}</td>
                                                        <td className="num" style={{ color: 'var(--green)' }}>{l.cr ? fmtMoney(l.cr) : '-'}</td>
                                                        <td className="num" style={{ color: running < 0 ? 'var(--red)' : undefined, fontWeight: 600 }}>{fmtMoney(running)}</td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>
                            );
                        })}
                        {!glFilter && <div style={{ textAlign: 'center', padding: 16, color: 'var(--text-muted)', fontSize: 13 }}>Showing key accounts. Select above for full detail.</div>}
                    </div>

                    <div className={`acc-page ${active === 'tb' ? 'active' : ''}`} id="acc-print-tb">
                        <SectionHeader title="Trial Balance" subtitle={periodAsAt(period)}>{renderPageBarActions({ showExport: true, extra: renderPageControls() })}</SectionHeader>
                        <div className="card">
                            <table className="ledger-table">
                                <thead><tr><th style={{ paddingLeft: 16 }}>Code</th><th>Account Name</th><th>Type</th><th className="num" style={{ color: 'var(--accent)', textAlign: 'right' }}>Debit (TZS)</th><th className="num" style={{ color: 'var(--green)', textAlign: 'right' }}>Credit (TZS)</th></tr></thead>
                                <tbody>
                                    {trialRows.map((r) => (
                                        <tr key={r.code}>
                                            <td style={{ paddingLeft: 16, fontWeight: 700, color: 'var(--accent)', fontFamily: 'monospace' }}>{r.code}</td>
                                            <td style={{ fontWeight: 500 }}>{r.name}</td>
                                            <td><span className={`acc-type ${r.type}`}>{titleCase(r.type)}</span></td>
                                            <td className="num" style={{ color: 'var(--accent)' }}>{r.dr ? fmtMoney(r.dr) : '-'}</td>
                                            <td className="num" style={{ color: 'var(--green)' }}>{r.cr ? fmtMoney(r.cr) : '-'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                                <tfoot>
                                    <tr style={{ borderTop: '2px solid var(--border)', fontWeight: 700, fontSize: 14 }}><td colSpan={3} style={{ padding: '12px 16px' }}>TOTALS</td><td className="num" style={{ padding: '12px 16px', color: 'var(--accent)' }}>{fmtMoney(trialRows.reduce((s, r) => s + r.dr, 0))}</td><td className="num" style={{ padding: '12px 16px', color: 'var(--green)' }}>{fmtMoney(trialRows.reduce((s, r) => s + r.cr, 0))}</td></tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <div className={`acc-page ${active === 'pl' ? 'active' : ''}`} id="acc-print-pl">
                        <SectionHeader title="Profit & Loss Statement" subtitle={periodLabel(period)}>{renderPageBarActions({ showExport: true, extra: renderPageControls() })}</SectionHeader>
                        <div className="card">
                            <ReportSection title="Revenue" rows={plData.revenue} field="ytd_display" />
                            <ReportSection title="Expenses" rows={plData.expense} field="ytd_display" />
                            <div className="rpt-total" style={{ fontSize: 15 }}><span>Gross Profit</span><span className="rpt-num">{fmtMoney(plData.gross)}</span></div>
                            <div className="rpt-grand"><span>Net Income / (Loss)</span><span className="rpt-num" style={{ color: plData.net >= 0 ? 'var(--green)' : 'var(--red)' }}>{`${plData.net >= 0 ? '+' : '-'}${fmtMoney(Math.abs(plData.net))}`}</span></div>
                        </div>
                    </div>

                    <div className={`acc-page ${active === 'bs' ? 'active' : ''}`} id="acc-print-bs">
                        <SectionHeader title="Balance Sheet" subtitle={periodAsAt(period)}>{renderPageBarActions({ showExport: true, extra: renderPageControls() })}</SectionHeader>
                        <div className="card">
                            <ReportSection title="Assets" rows={bsData.assets} field="balance" />
                            <ReportSection title="Liabilities" rows={bsData.liabilities} field="balance" />
                            <ReportSection title="Equity" rows={bsData.equity} field="balance" />
                            <div className="rpt-total" style={{ fontSize: 13 }}><span>Balance Check (Assets - Liabilities - Equity)</span><span className="rpt-num" style={{ color: Math.abs(bsData.difference) < 0.01 ? 'var(--green)' : 'var(--red)' }}>{fmtMoney(bsData.difference)}</span></div>
                            <div className="rpt-grand"><span>Total Liabilities + Equity</span><span className="rpt-num">{fmtMoney(bsData.totalLiabilities + bsData.totalEquity)}</span></div>
                        </div>
                    </div>

                    <div className={`acc-page ${active === 'cf' ? 'active' : ''}`} id="acc-print-cf">
                        <SectionHeader title="Cash Flow Statement" subtitle={periodLabel(period)}>{renderPageBarActions({ showExport: true, extra: renderPageControls() })}</SectionHeader>
                        <div className="card">
                            <CashSection title="Operating Activities" rows={cfData.operating} total={cfData.tO} />
                            <CashSection title="Investing Activities" rows={cfData.investing} total={cfData.tI} />
                            <CashSection title="Financing Activities" rows={cfData.financing} total={cfData.tF} />
                            <div className="rpt-total" style={{ fontSize: 15 }}><span>Net Change in Cash</span><span className={`rpt-num ${cfData.net >= 0 ? 'rpt-positive' : 'rpt-negative'}`}>{`${cfData.net >= 0 ? '+' : '-'}${fmtMoney(Math.abs(cfData.net))}`}</span></div>
                            <div className="rpt-line"><span>Opening Cash Balance (1 Jan 2026)</span><span className="rpt-num">{fmtMoney(cfData.open)}</span></div>
                            <div className="rpt-grand"><span>Closing Cash Balance (31 Mar 2026)</span><span className="rpt-num" style={{ color: 'var(--green)' }}>{fmtMoney(cfData.close)}</span></div>
                        </div>
                    </div>
                </main>
            </div>

            {showJEModal && (
                <div className="modal-overlay open" onClick={(e) => e.target === e.currentTarget && setShowJEModal(false)}>
                    <div className="modal">
                        <div className="modal-header"><div className="modal-title">New Journal Entry</div><button className="modal-close" onClick={() => setShowJEModal(false)}>x</button></div>
                        <div className="modal-body">
                            <div className="form-row">
                                <div className="form-group"><label className="form-label">Date *</label><input className="form-input" type="date" value={jeForm.date} onChange={(e) => setJeForm((f) => ({ ...f, date: e.target.value }))} /></div>
                                <div className="form-group"><label className="form-label">Reference</label><input className="form-input" type="text" value={jeForm.ref} onChange={(e) => setJeForm((f) => ({ ...f, ref: e.target.value }))} placeholder="e.g. INV-1001" /></div>
                            </div>
                            <div className="form-row"><div className="form-group"><label className="form-label">Description *</label><input className="form-input" type="text" value={jeForm.desc} onChange={(e) => setJeForm((f) => ({ ...f, desc: e.target.value }))} placeholder="Brief description" /></div></div>

                            <div style={{ marginBottom: 14 }}>
                                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 8 }}>
                                    <label className="form-label" style={{ margin: 0 }}>Journal Lines *</label>
                                    <button className="btn-ghost" style={{ padding: '4px 10px', fontSize: 12 }} onClick={addJELine}>+ Add Line</button>
                                </div>
                                <div style={{ background: 'var(--bg-elevated)', borderRadius: 10, overflow: 'hidden', border: '1px solid var(--border)' }}>
                                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 110px 110px 32px', gap: 0, padding: '6px 12px', fontSize: 10.5, fontWeight: 700, letterSpacing: '.5px', textTransform: 'uppercase', color: 'var(--text-muted)', borderBottom: '1px solid var(--border)' }}>
                                        <span>Account</span><span style={{ textAlign: 'right' }}>Debit (TZS)</span><span style={{ textAlign: 'right' }}>Credit (TZS)</span><span></span>
                                    </div>
                                    {jeForm.lines.map((l, i) => (
                                        <div className="je-entry-row" key={i}>
                                            <select className="form-input form-select" style={{ fontSize: 12.5 }} value={l.acct} onChange={(e) => updateJELine(i, { acct: e.target.value })}>
                                                <option value="">Select account...</option>
                                                {accountData.map((a) => <option key={a.code} value={a.code}>{a.code} - {a.name}</option>)}
                                            </select>
                                            <input className="form-input" type="number" placeholder="Debit" style={{ textAlign: 'right' }} value={l.dr || ''} onChange={(e) => updateJELine(i, { dr: Number(e.target.value) || 0 })} />
                                            <input className="form-input" type="number" placeholder="Credit" style={{ textAlign: 'right' }} value={l.cr || ''} onChange={(e) => updateJELine(i, { cr: Number(e.target.value) || 0 })} />
                                            {jeForm.lines.length > 2 ? <button className="modal-close" style={{ width: 22, height: 22, fontSize: 12 }} onClick={() => removeJELine(i)}>x</button> : <div></div>}
                                        </div>
                                    ))}
                                </div>
                                <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 24, padding: '8px 12px', fontSize: 13 }}>
                                    <span>Debit: <strong style={{ color: 'var(--accent)' }}>{fmtMoney(jeTotals.dr)}</strong></span>
                                    <span>Credit: <strong style={{ color: 'var(--green)' }}>{fmtMoney(jeTotals.cr)}</strong></span>
                                    <span style={{ fontWeight: 600, color: jeTotals.balanced ? 'var(--green)' : 'var(--amber)' }}>{jeTotals.balanced ? 'Balanced' : `Difference: ${fmtMoney(Math.abs(jeTotals.dr - jeTotals.cr))}`}</span>
                                </div>
                            </div>
                        </div>
                        <div className="modal-footer">
                            <button className="btn-ghost" onClick={() => setShowJEModal(false)}>Cancel</button>
                            <button className="btn-ghost" onClick={() => submitJE('draft')}>Save Draft</button>
                            <button className="btn-primary" onClick={() => submitJE('posted')}>Post Entry</button>
                        </div>
                    </div>
                </div>
            )}

            {showAccountModal && (
                <div className="modal-overlay open" onClick={(e) => e.target === e.currentTarget && setShowAccountModal(false)}>
                    <div className="modal" style={{ width: 480 }}>
                        <div className="modal-header"><div className="modal-title">Add Account</div><button className="modal-close" onClick={() => setShowAccountModal(false)}>x</button></div>
                        <div className="modal-body">
                            <div className="form-row">
                                <div className="form-group" style={{ flex: '0 0 130px' }}><label className="form-label">Account Code *</label><input className="form-input" type="text" value={accountForm.code} onChange={(e) => setAccountForm((f) => ({ ...f, code: e.target.value }))} placeholder="e.g. 1010" /></div>
                                <div className="form-group"><label className="form-label">Account Name *</label><input className="form-input" type="text" value={accountForm.name} onChange={(e) => setAccountForm((f) => ({ ...f, name: e.target.value }))} placeholder="e.g. Cash at Bank" /></div>
                            </div>
                            <div className="form-row">
                                <div className="form-group"><label className="form-label">Type *</label>
                                    <select className="form-input form-select" value={accountForm.type} onChange={(e) => setAccountForm((f) => ({ ...f, type: e.target.value }))}>
                                        <option value="">Select...</option><option value="asset">Asset</option><option value="liability">Liability</option><option value="equity">Equity</option><option value="revenue">Revenue</option><option value="expense">Expense</option><option value="contra">Contra</option>
                                    </select>
                                </div>
                                <div className="form-group"><label className="form-label">Category</label><input className="form-input" type="text" value={accountForm.cat} onChange={(e) => setAccountForm((f) => ({ ...f, cat: e.target.value }))} placeholder="e.g. Current Assets" /></div>
                            </div>
                            <div className="form-row">
                                <div className="form-group"><label className="form-label">Opening Balance (TZS)</label><input className="form-input" type="number" value={accountForm.opening} onChange={(e) => setAccountForm((f) => ({ ...f, opening: e.target.value }))} placeholder="0.00" /></div>
                                <div className="form-group"><label className="form-label">Description</label><input className="form-input" type="text" value={accountForm.desc} onChange={(e) => setAccountForm((f) => ({ ...f, desc: e.target.value }))} placeholder="Optional" /></div>
                            </div>
                        </div>
                        <div className="modal-footer">
                            <button className="btn-ghost" onClick={() => setShowAccountModal(false)}>Cancel</button>
                            <button className="btn-primary" onClick={submitAccount}>Add Account</button>
                        </div>
                    </div>
                </div>
            )}

            <div className={`acc-toast ${toast ? 'show' : ''}`}>{toast}</div>
        </AppLayout>
    );
}

function SectionHeader({ title, subtitle, children }) {
    return (
        <div className="acc-page-header">
            <div><div className="acc-page-title">{title}</div><div className="acc-page-sub">{subtitle}</div></div>
            <div className="acc-actions">{children}</div>
        </div>
    );
}

function ReportSection({ title, rows, field }) {
    const grouped = groupByCategory(rows);
    const total = rows.reduce((s, r) => s + toNum(r[field]), 0);
    return (
        <>
            <div className="rpt-section">{title}</div>
            {Object.entries(grouped).map(([cat, list]) => (
                <Fragment key={`${title}-${cat}`}>
                    <div className="rpt-group">{cat}</div>
                    {list.map((a) => <div className="rpt-line" key={`${title}-${a.code}`}><span>{a.code} - {a.name}</span><span className={`rpt-num ${toNum(a[field]) < 0 ? 'rpt-negative' : ''}`}>{fmtMoney(a[field])}</span></div>)}
                </Fragment>
            ))}
            <div className="rpt-total"><span>Total {title}</span><span className="rpt-num">{fmtMoney(total)}</span></div>
        </>
    );
}

function CashSection({ title, rows, total }) {
    return (
        <>
            <div style={{ padding: '10px 16px', fontSize: 13, fontWeight: 600, color: 'var(--text-secondary)', background: 'var(--bg-elevated)', borderBottom: '1px solid var(--border-subtle)' }}>{title}</div>
            {rows.map((r) => <div className="rpt-line" key={`${title}-${r.desc}`}><span>{r.desc}</span><span className={`rpt-num ${r.amount < 0 ? 'rpt-negative' : (r.amount > 0 ? 'rpt-positive' : '')}`}>{`${r.amount >= 0 ? '+' : '-'}${fmtMoney(Math.abs(r.amount))}`}</span></div>)}
            <div className="rpt-total"><span>Net Cash from {title}</span><span className={`rpt-num ${total < 0 ? 'rpt-negative' : 'rpt-positive'}`}>{`${total >= 0 ? '+' : '-'}${fmtMoney(Math.abs(total))}`}</span></div>
        </>
    );
}
