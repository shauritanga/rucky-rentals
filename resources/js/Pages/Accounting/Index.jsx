import { Fragment, useEffect, useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

const toNum = (v) => Number(v ?? 0) || 0;
const fmtMoney = (v) => `TZS ${toNum(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const titleCase = (s) => (s || '').charAt(0).toUpperCase() + (s || '').slice(1);

const DEFAULT_ACCOUNTS = [
    { code: '1000', name: 'Cash at Bank', type: 'asset', cat: 'Current Assets', balance: 248600, ytd: 84200 },
    { code: '1010', name: 'Petty Cash', type: 'asset', cat: 'Current Assets', balance: 3500, ytd: 500 },
    { code: '1100', name: 'Rent Receivable', type: 'asset', cat: 'Current Assets', balance: 6400, ytd: 42000 },
    { code: '1110', name: 'Security Deposits', type: 'asset', cat: 'Current Assets', balance: 52800, ytd: 3200 },
    { code: '1200', name: 'Prepaid Expenses', type: 'asset', cat: 'Current Assets', balance: 4800, ytd: -1200 },
    { code: '1500', name: 'Building & Property', type: 'asset', cat: 'Fixed Assets', balance: 4800000, ytd: 0 },
    { code: '1600', name: 'Accum. Depreciation', type: 'contra', cat: 'Fixed Assets', balance: -185000, ytd: -18000 },
    { code: '2000', name: 'Accounts Payable', type: 'liability', cat: 'Current Liabilities', balance: 12400, ytd: 8200 },
    { code: '2100', name: 'Deposits Payable', type: 'liability', cat: 'Current Liabilities', balance: 52800, ytd: 3200 },
    { code: '2300', name: 'Accrued Expenses', type: 'liability', cat: 'Current Liabilities', balance: 8600, ytd: 2400 },
    { code: '2500', name: 'Mortgage Payable', type: 'liability', cat: 'Long-Term Liabilities', balance: 1200000, ytd: -48000 },
    { code: '3000', name: "Owner's Capital", type: 'equity', cat: 'Equity', balance: 3600000, ytd: 0 },
    { code: '3100', name: 'Retained Earnings', type: 'equity', cat: 'Equity', balance: 98420, ytd: 0 },
    { code: '3200', name: 'Current Year Earnings', type: 'equity', cat: 'Equity', balance: 47580, ytd: 47580 },
    { code: '4000', name: 'Rental Income', type: 'revenue', cat: 'Operating Revenue', balance: 124200, ytd: 124200 },
    { code: '4100', name: 'Late Payment Fees', type: 'revenue', cat: 'Operating Revenue', balance: 3600, ytd: 3600 },
    { code: '4200', name: 'Maintenance Charges', type: 'revenue', cat: 'Other Revenue', balance: 1200, ytd: 1200 },
    { code: '4300', name: 'Interest Income', type: 'revenue', cat: 'Other Revenue', balance: 840, ytd: 840 },
    { code: '5000', name: 'Maintenance Expense', type: 'expense', cat: 'Operating Expenses', balance: 8400, ytd: 8400 },
    { code: '5100', name: 'Property Management', type: 'expense', cat: 'Operating Expenses', balance: 6210, ytd: 6210 },
    { code: '5200', name: 'Cleaning & Sanitation', type: 'expense', cat: 'Operating Expenses', balance: 2360, ytd: 2360 },
    { code: '5300', name: 'Security Services', type: 'expense', cat: 'Operating Expenses', balance: 1680, ytd: 1680 },
    { code: '5400', name: 'Utilities - Common', type: 'expense', cat: 'Operating Expenses', balance: 1340, ytd: 1340 },
    { code: '5500', name: 'Insurance', type: 'expense', cat: 'Operating Expenses', balance: 4200, ytd: 4200 },
    { code: '5600', name: 'Mortgage Interest', type: 'expense', cat: 'Finance Costs', balance: 12000, ytd: 12000 },
    { code: '5700', name: 'Depreciation', type: 'expense', cat: 'Non-Cash', balance: 18000, ytd: 18000 },
    { code: '5800', name: 'Admin & Legal', type: 'expense', cat: 'Operating Expenses', balance: 840, ytd: 840 },
    { code: '5900', name: 'Bad Debt Expense', type: 'expense', cat: 'Operating Expenses', balance: 950, ytd: 950 },
];

const DEFAULT_ENTRIES = [
    { id: 'JE-001', date: '2026-01-01', desc: 'Opening balances - rental income Jan', ref: 'OPEN-JAN', status: 'posted', lines: [{ acct: '1000', name: 'Cash at Bank', dr: 42000, cr: 0 }, { acct: '4000', name: 'Rental Income', dr: 0, cr: 42000 }] },
    { id: 'JE-002', date: '2026-01-05', desc: 'Maintenance - plumbing repair', ref: 'TK-011', status: 'posted', lines: [{ acct: '5000', name: 'Maintenance Expense', dr: 110, cr: 0 }, { acct: '1000', name: 'Cash at Bank', dr: 0, cr: 110 }] },
    { id: 'JE-003', date: '2026-02-01', desc: 'Monthly rental income - February', ref: 'RENT-FEB', status: 'posted', lines: [{ acct: '1000', name: 'Cash at Bank', dr: 41500, cr: 0 }, { acct: '4000', name: 'Rental Income', dr: 0, cr: 41500 }] },
    { id: 'JE-004', date: '2026-03-01', desc: 'Monthly rental income - March', ref: 'RENT-MAR', status: 'posted', lines: [{ acct: '1000', name: 'Cash at Bank', dr: 38200, cr: 0 }, { acct: '1100', name: 'Rent Receivable', dr: 3800, cr: 0 }, { acct: '4000', name: 'Rental Income', dr: 0, cr: 42000 }] },
    { id: 'JE-005', date: '2026-03-10', desc: 'AC unit repair - C-303', ref: 'TK-004', status: 'posted', lines: [{ acct: '5000', name: 'Maintenance Expense', dr: 350, cr: 0 }, { acct: '2000', name: 'Accounts Payable', dr: 0, cr: 350 }] },
    { id: 'JE-006', date: '2026-03-19', desc: 'Bad debt provision - C-301', ref: 'BAD-C301', status: 'draft', lines: [{ acct: '5900', name: 'Bad Debt Expense', dr: 950, cr: 0 }, { acct: '1100', name: 'Rent Receivable', dr: 0, cr: 950 }] },
];

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
    if (!Array.isArray(input) || input.length === 0) return [...DEFAULT_ACCOUNTS].sort((a, b) => a.code.localeCompare(b.code));
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
    if (!Array.isArray(input) || input.length === 0) return [...DEFAULT_ENTRIES];
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

function statusClass(s) {
    if (s === 'posted') return 'posted';
    if (s === 'void') return 'void';
    return 'draft';
}

export default function Accounting({ accounts = [], entries = [] }) {
    const [active, setActive] = useState('coa');
    const [period, setPeriod] = useState('Q1-2026');
    const [syncStatus, setSyncStatus] = useState('Not synced');

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
    const [jeForm, setJeForm] = useState({ date: '2026-03-19', ref: '', desc: '', lines: [{ acct: '', dr: 0, cr: 0 }, { acct: '', dr: 0, cr: 0 }] });

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

    useEffect(() => {
        loadSharedAccounting();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const nextEntryId = (list) => {
        const max = list.reduce((m, e) => {
            const hit = String(e.id).match(/(\d+)/);
            return Math.max(m, hit ? Number(hit[1]) : 0);
        }, 0);
        return `JE-${String(max + 1).padStart(3, '0')}`;
    };

    const loadSharedAccounting = () => {
        try {
            const raw = localStorage.getItem('velour_shared');
            if (!raw) return;
            const shared = JSON.parse(raw);

            const nextAccounts = accountData.map((a) => ({ ...a }));
            const nextEntries = entryData.map((e) => ({ ...e, lines: e.lines.map((l) => ({ ...l })) }));
            const refs = new Set(nextEntries.map((e) => e.ref));

            const findAccount = (code) => nextAccounts.find((a) => a.code === code);

            if (Array.isArray(shared.payments)) {
                shared.payments.forEach((p) => {
                    if (p.status !== 'paid') return;
                    const ref = `RENT-${p.unit}-${String(p.month || '').replace(' ', '')}`;
                    if (refs.has(ref)) return;

                    const amount = toNum(p.amount);
                    nextEntries.push({
                        id: nextEntryId(nextEntries),
                        date: p.date || new Date().toISOString().slice(0, 10),
                        desc: `Rent received - ${p.tenant || 'Tenant'} (${p.unit || 'Unit'})`,
                        ref,
                        status: 'posted',
                        lines: [
                            { acct: '1000', name: 'Cash at Bank', dr: amount, cr: 0 },
                            { acct: '4000', name: 'Rental Income', dr: 0, cr: amount },
                        ],
                    });
                    refs.add(ref);

                    const cash = findAccount('1000');
                    const rent = findAccount('4000');
                    if (cash) { cash.balance += amount; cash.ytd += amount; }
                    if (rent) { rent.balance += amount; rent.ytd += amount; }
                });
            }

            if (Array.isArray(shared.maintenance)) {
                shared.maintenance.forEach((t) => {
                    const cost = toNum(t.cost);
                    if (!cost || t.status !== 'resolved') return;
                    const ref = `MAINT-${t.id}`;
                    if (refs.has(ref)) return;

                    nextEntries.push({
                        id: nextEntryId(nextEntries),
                        date: t.reported || new Date().toISOString().slice(0, 10),
                        desc: `Maintenance: ${t.title || 'Ticket'} (${t.unit || 'Unit'})`,
                        ref,
                        status: 'posted',
                        lines: [
                            { acct: '5000', name: 'Maintenance Expense', dr: cost, cr: 0 },
                            { acct: '2000', name: 'Accounts Payable', dr: 0, cr: cost },
                        ],
                    });
                    refs.add(ref);

                    const maint = findAccount('5000');
                    const ap = findAccount('2000');
                    if (maint) { maint.balance += cost; maint.ytd += cost; }
                    if (ap) { ap.balance += cost; ap.ytd += cost; }
                });
            }

            setAccountData(nextAccounts.sort((a, b) => a.code.localeCompare(b.code)));
            setEntryData(nextEntries);

            if (shared.syncedAt) {
                const d = new Date(shared.syncedAt);
                const ts = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                const units = Array.isArray(shared.units) ? shared.units.length : '?';
                const leases = Array.isArray(shared.leases) ? shared.leases.length : '?';
                setSyncStatus(`Synced ${ts} | ${units} units | ${leases} leases`);
            } else {
                setSyncStatus('Synced');
            }
        } catch (_e) {
            // ignore localStorage parse errors
        }
    };

    const refreshAll = () => {
        loadSharedAccounting();
        setToast('Data refreshed from dashboard');
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
        const revenue = accountData.filter((a) => a.type === 'revenue');
        const expense = accountData.filter((a) => a.type === 'expense');
        const totalRevenue = revenue.reduce((s, a) => s + a.ytd, 0);
        const totalExpense = expense.reduce((s, a) => s + a.ytd, 0);
        const operating = expense.filter((a) => a.cat === 'Operating Expenses').reduce((s, a) => s + a.ytd, 0);
        return { revenue, expense, totalRevenue, totalExpense, gross: totalRevenue - operating, net: totalRevenue - totalExpense };
    }, [accountData]);

    const bsData = useMemo(() => {
        const assets = accountData.filter((a) => a.type === 'asset' || a.type === 'contra');
        const liabilities = accountData.filter((a) => a.type === 'liability');
        const equity = accountData.filter((a) => a.type === 'equity');
        return {
            assets,
            liabilities,
            equity,
            totalAssets: assets.reduce((s, a) => s + a.balance, 0),
            totalLiabilities: liabilities.reduce((s, a) => s + a.balance, 0),
            totalEquity: equity.reduce((s, a) => s + a.balance, 0),
        };
    }, [accountData]);

    const cfData = useMemo(() => {
        const operating = [
            { desc: 'Net Income', amount: 47580 },
            { desc: 'Add: Depreciation', amount: 18000 },
            { desc: 'Increase in Rent Receivable', amount: -6400 },
            { desc: 'Increase in Prepaid Expenses', amount: -4800 },
            { desc: 'Increase in Accounts Payable', amount: 12400 },
            { desc: 'Increase in Accrued Expenses', amount: 8600 },
            { desc: 'Increase in Deferred Rent Income', amount: 18000 },
        ];
        const investing = [
            { desc: 'Purchase of Furniture & Fittings', amount: -6000 },
            { desc: 'Security Deposits Received', amount: 3200 },
        ];
        const financing = [
            { desc: 'Mortgage Repayments', amount: -48000 },
            { desc: 'Owner Drawings', amount: -24000 },
        ];
        const tO = operating.reduce((s, i) => s + i.amount, 0);
        const tI = investing.reduce((s, i) => s + i.amount, 0);
        const tF = financing.reduce((s, i) => s + i.amount, 0);
        const net = tO + tI + tF;
        const open = 155620;
        return { operating, investing, financing, tO, tI, tF, net, open, close: open + net };
    }, []);

    const jeTotals = useMemo(() => {
        const dr = jeForm.lines.reduce((s, l) => s + toNum(l.dr), 0);
        const cr = jeForm.lines.reduce((s, l) => s + toNum(l.cr), 0);
        return { dr, cr, balanced: Math.abs(dr - cr) < 0.01 && dr > 0 };
    }, [jeForm.lines]);

    const openJEModal = () => {
        setJeForm({ date: '2026-03-19', ref: '', desc: '', lines: [{ acct: '', dr: 0, cr: 0 }, { acct: '', dr: 0, cr: 0 }] });
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
        w.document.write(`<!DOCTYPE html><html><head><title>${title}</title><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet"><style>body{font-family:'DM Sans',sans-serif;color:#111;padding:32px;font-size:13px}h1{font-size:20px;margin:0 0 4px}p{color:#666;margin:0 0 20px}table{width:100%;border-collapse:collapse}th{text-align:left;font-size:10px;text-transform:uppercase;color:#999;letter-spacing:.5px;padding:8px 12px;border-bottom:2px solid #e5e7eb}td{padding:8px 12px;border-bottom:1px solid #f3f4f6;font-size:12.5px}.num{text-align:right;font-variant-numeric:tabular-nums}.rpt-section{background:#f9fafb;padding:7px 12px;font-size:10px;font-weight:700;text-transform:uppercase;color:#999;border-bottom:1px solid #e5e7eb}.rpt-group{padding:7px 12px 7px 24px;font-size:12px;font-weight:600;color:#555;border-bottom:1px solid #f3f4f6}.rpt-line{display:flex;justify-content:space-between;padding:7px 12px 7px 36px;border-bottom:1px solid #f3f4f6}.rpt-total{display:flex;justify-content:space-between;padding:10px 12px;font-weight:700;border-top:2px solid #e5e7eb}.rpt-grand{display:flex;justify-content:space-between;padding:12px;font-weight:800;background:#eff6ff;color:#2563eb}</style></head><body><h1>Velour Properties - ${title}</h1><p>Generated: ${new Date().toLocaleDateString()} | ${period}</p>${section.innerHTML}<script>window.onload=function(){window.print()}<\/script></body></html>`);
        w.document.close();
    };

    const renderPageBarActions = ({ extra = null, showNewJE = true, showExport = true } = {}) => (
        <>
            {extra}
            <div className="period-select">
                <select value={period} onChange={(e) => { setPeriod(e.target.value); refreshAll(); }}>
                    <option value="Q1-2026">Q1 2026 (Jan-Mar)</option>
                    <option value="Q4-2025">Q4 2025</option>
                    <option value="FY-2025">Full Year 2025</option>
                    <option value="FY-2026">Full Year 2026 (YTD)</option>
                </select>
            </div>
            <button className="icon-btn" onClick={refreshAll} title="Refresh from dashboard" style={{ color: 'var(--green)' }}>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
            </button>
            {showNewJE && <button className="btn-primary" onClick={openJEModal}>+ New Journal Entry</button>}
            {showExport && <button className="btn-ghost" onClick={printCurrent}>Export PDF</button>}
            <span style={{ fontSize: 12.5, color: syncStatus.startsWith('Synced') ? 'var(--green)' : 'var(--text-muted)', whiteSpace: 'nowrap' }}>{syncStatus}</span>
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
                                                    <td className="num" style={{ color: a.balance < 0 ? 'var(--red)' : undefined }}>{fmtMoney(a.balance)}</td>
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
                                extra: (
                                    <>
                                        <div className="search-box"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg><input type="text" placeholder="Search entries..." value={jeSearch} onChange={(e) => setJeSearch(e.target.value)} /></div>
                                        <select className="form-input form-select" value={jeFilter} onChange={(e) => setJeFilter(e.target.value)} style={{ width: 110, padding: '6px 28px 6px 10px', fontSize: 12.5 }}><option value="">All</option><option value="posted">Posted</option><option value="draft">Draft</option><option value="void">Void</option></select>
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
                                    <select className="form-input form-select" value={glFilter} onChange={(e) => setGlFilter(e.target.value)} style={{ width: 240, padding: '6px 28px 6px 10px', fontSize: 12.5 }}>
                                        <option value="">All Key Accounts</option>
                                        {accountData.map((a) => <option key={a.code} value={a.code}>{a.code} - {a.name}</option>)}
                                    </select>
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
                        <SectionHeader title="Trial Balance" subtitle="As at 31 March 2026">{renderPageBarActions()}</SectionHeader>
                        <div className="card">
                            <table className="ledger-table">
                                <thead><tr><th style={{ paddingLeft: 16 }}>Code</th><th>Account Name</th><th>Type</th><th className="num" style={{ color: 'var(--accent)' }}>Debit (TZS)</th><th className="num" style={{ color: 'var(--green)' }}>Credit (TZS)</th></tr></thead>
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
                        <SectionHeader title="Profit & Loss Statement" subtitle="For the period ending 31 March 2026">{renderPageBarActions()}</SectionHeader>
                        <div className="card">
                            <ReportSection title="Revenue" rows={plData.revenue} field="ytd" />
                            <ReportSection title="Expenses" rows={plData.expense} field="ytd" />
                            <div className="rpt-total" style={{ fontSize: 15 }}><span>Gross Profit</span><span className="rpt-num">{fmtMoney(plData.gross)}</span></div>
                            <div className="rpt-grand"><span>Net Income / (Loss)</span><span className="rpt-num" style={{ color: plData.net >= 0 ? 'var(--green)' : 'var(--red)' }}>{`${plData.net >= 0 ? '+' : '-'}${fmtMoney(Math.abs(plData.net))}`}</span></div>
                        </div>
                    </div>

                    <div className={`acc-page ${active === 'bs' ? 'active' : ''}`} id="acc-print-bs">
                        <SectionHeader title="Balance Sheet" subtitle="As at 31 March 2026">{renderPageBarActions()}</SectionHeader>
                        <div className="card">
                            <ReportSection title="Assets" rows={bsData.assets} field="balance" />
                            <ReportSection title="Liabilities" rows={bsData.liabilities} field="balance" />
                            <ReportSection title="Equity" rows={bsData.equity} field="balance" />
                            <div className="rpt-grand"><span>Total Liabilities + Equity</span><span className="rpt-num">{fmtMoney(bsData.totalLiabilities + bsData.totalEquity)}</span></div>
                        </div>
                    </div>

                    <div className={`acc-page ${active === 'cf' ? 'active' : ''}`} id="acc-print-cf">
                        <SectionHeader title="Cash Flow Statement" subtitle="For the quarter ended 31 March 2026">{renderPageBarActions()}</SectionHeader>
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
