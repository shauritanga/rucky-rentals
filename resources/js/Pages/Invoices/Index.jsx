import { useEffect, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm, router, usePage } from '@inertiajs/react';

const fmt = (n) => Number(n).toLocaleString();
const VAT_RATE = 0.18;
const SERVICE_CHARGE_RATE = 0.05;

const normalizeCurrency = (value) => (String(value || '').toUpperCase() === 'TZS' ? 'TZS' : 'USD');

const resolveInvoiceCurrency = (invoice, leases = []) => {
  const direct = normalizeCurrency(invoice?.currency);
  if (invoice?.currency) return direct;

  const lease = leases.find((l) => Number(l.id) === Number(invoice?.lease_id));
  return normalizeCurrency(lease?.currency || lease?.unit?.currency);
};

const formatMoney = (amount, currency = 'USD') => {
  const numeric = Number(amount || 0);
  if (Number.isNaN(numeric)) return '—';

  const money = normalizeCurrency(currency);
  const decimals = money === 'USD' ? 2 : 0;
  return `${money} ${numeric.toLocaleString('en-US', {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  })}`;
};

const formatMoneyCompact = (amount, currency = 'USD') => {
  const numeric = Number(amount || 0);
  if (Number.isNaN(numeric)) return '—';

  const money = normalizeCurrency(currency);
  const abs = Math.abs(numeric);

  if (abs >= 1_000_000) return `${money} ${(abs / 1_000_000).toFixed(1)}M`;
  if (abs >= 1_000) return `${money} ${(abs / 1_000).toFixed(0)}k`;

  const decimals = money === 'USD' ? 2 : 0;
  return `${money} ${numeric.toLocaleString('en-US', {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  })}`;
};

const invoiceStatusLabel = (status = '') => {
  const labels = {
    draft: 'Draft',
    proforma: 'Proforma',
    unpaid: 'Unpaid',
    partially_paid: 'Partially Paid',
    paid: 'Paid',
    overdue: 'Overdue',
  };

  return labels[status] || String(status || '').replace('_', ' ');
};

function InvoiceDoc({ inv, currency = 'USD', lease = null }) {
  const fitoutBadge = (description = '') => {
    const text = String(description || '').toLowerCase();
    if (!text.includes('fit-out')) return null;
    if (text.includes('vat')) return 'FIT-OUT VAT';
    return 'FIT-OUT';
  };
  const lineItems    = (inv.items || []).filter(i => i.item_type !== 'electricity_vat');
  const vatItems     = (inv.items || []).filter(i => i.item_type === 'electricity_vat');
  const subtotal     = lineItems.reduce((s, i) => s + Number(i.total || 0), 0);
  const vatFromItems = vatItems.reduce((s, i) => s + Number(i.total || 0), 0);
  const vatRate      = Number(lease?.vat_rate ?? 18);
  const vatAmount    = vatFromItems > 0 ? vatFromItems : Math.round(subtotal * vatRate / 100);
  const vatLabel     = `VAT (${vatRate}%)`;
  const grandTotal   = subtotal + vatAmount;
  const isPaid = inv.status === 'paid';
  const isOver = inv.status === 'overdue';
  return (
    <div style={{background:'var(--bg-surface)',border:'1px solid var(--border)',borderRadius:12,padding:'36px 40px',position:'relative'}}>
      {isPaid && <div style={{position:'absolute',top:36,right:36,fontSize:28,fontWeight:900,letterSpacing:2,textTransform:'uppercase',opacity:.12,transform:'rotate(-18deg)',border:'4px solid var(--green)',padding:'4px 10px',borderRadius:4,color:'var(--green)'}}>PAID</div>}
      {isOver && <div style={{position:'absolute',top:36,right:36,fontSize:28,fontWeight:900,letterSpacing:2,textTransform:'uppercase',opacity:.12,transform:'rotate(-18deg)',border:'4px solid var(--red)',padding:'4px 10px',borderRadius:4,color:'var(--red)'}}>OVERDUE</div>}
      <div style={{display:'flex',alignItems:'flex-start',justifyContent:'space-between',marginBottom:32}}>
        <div style={{display:'flex',alignItems:'center',gap:10}}>
          <div style={{width:36,height:36,borderRadius:9,background:'var(--accent)',display:'flex',alignItems:'center',justifyContent:'center',fontFamily:"'Instrument Serif',serif",fontStyle:'italic',fontSize:18,color:'#fff',flexShrink:0}}>R</div>
          <div><div style={{fontSize:17,fontWeight:700}}>Ruky Rentals</div><div style={{fontSize:'11.5px',color:'var(--text-muted)'}}>Nairobi, Kenya · info@ruky.ke</div></div>
        </div>
        <div style={{textAlign:'right'}}>
          <div style={{fontSize:22,fontWeight:800,letterSpacing:'-.5px',color:inv.type==='proforma'?'var(--amber)':'var(--accent)',textTransform:'uppercase'}}>{inv.type==='proforma'?'PROFORMA INVOICE':'TAX INVOICE'}</div>
          <div style={{fontSize:13,color:'var(--text-muted)',fontWeight:500}}>{inv.invoice_number}</div>
        </div>
      </div>
      <div style={{height:1,background:'var(--border-subtle)',margin:'20px 0'}}></div>
      <div style={{display:'grid',gridTemplateColumns:'1fr 1fr 1fr',gap:16,marginBottom:28}}>
        <div><div style={{fontSize:10,fontWeight:700,letterSpacing:'.8px',textTransform:'uppercase',color:'var(--text-muted)',marginBottom:6}}>Billed To</div><div style={{fontSize:14,fontWeight:600,marginBottom:3}}>{inv.tenant_name}</div><div style={{fontSize:'12.5px',color:'var(--text-secondary)',lineHeight:1.6}}>{inv.tenant_email||'—'}<br/>Unit {inv.unit_ref}</div></div>
        <div><div style={{fontSize:10,fontWeight:700,letterSpacing:'.8px',textTransform:'uppercase',color:'var(--text-muted)',marginBottom:6}}>From</div><div style={{fontSize:14,fontWeight:600,marginBottom:3}}>Ruky Rentals Ltd</div><div style={{fontSize:'12.5px',color:'var(--text-secondary)',lineHeight:1.6}}>P.O. Box 10042-00100<br/>Nairobi, Kenya</div></div>
        <div><div style={{fontSize:10,fontWeight:700,letterSpacing:'.8px',textTransform:'uppercase',color:'var(--text-muted)',marginBottom:6}}>Invoice Details</div><div style={{fontSize:'12.5px',color:'var(--text-secondary)',lineHeight:1.8}}><strong>Issue Date:</strong> {inv.issued_date}<br/><strong>Due Date:</strong> {inv.due_date||'—'}<br/><strong>Period:</strong> {inv.period||'—'}</div></div>
      </div>
      <div style={{height:1,background:'var(--border-subtle)',margin:'20px 0'}}></div>
      <table style={{width:'100%',borderCollapse:'collapse',marginBottom:20}}>
        <thead><tr>{['Description','Qty','Unit Price','Amount'].map(h=><th key={h} style={{textAlign:h==='Amount'||h==='Unit Price'||h==='Qty'?'right':'left',fontSize:'10.5px',fontWeight:700,letterSpacing:'.5px',textTransform:'uppercase',color:'var(--text-muted)',padding:'8px 10px',borderBottom:'2px solid var(--border)'}}>{h}</th>)}</tr></thead>
        <tbody>
          {(inv.items||[]).filter(item => item.item_type !== 'electricity_vat').map((item,i) => {
            const badge = fitoutBadge(item.description);
            return (
              <tr key={i}>
                <td style={{padding:'10px 10px',borderBottom:'1px solid var(--border-subtle)',fontSize:13}}>
                  <div style={{fontWeight:500,display:'flex',alignItems:'center',gap:8}}>
                    <span>{item.description}</span>
                    {badge && (
                      <span style={{fontSize:10,fontWeight:700,letterSpacing:'.4px',textTransform:'uppercase',color:'var(--amber)',background:'var(--amber-dim)',border:'1px solid rgba(245,158,11,.35)',borderRadius:12,padding:'1px 7px'}}>
                        {badge}
                      </span>
                    )}
                  </div>
                  {item.sub_description&&<div style={{fontSize:'11.5px',color:'var(--text-muted)',marginTop:2}}>{item.sub_description}</div>}
                </td>
                <td style={{textAlign:'center',padding:'10px 10px',borderBottom:'1px solid var(--border-subtle)',fontSize:13}}>{item.quantity}</td>
                <td style={{textAlign:'right',padding:'10px 10px',borderBottom:'1px solid var(--border-subtle)',fontSize:13}}>{formatMoney(item.unit_price, currency)}</td>
                <td style={{textAlign:'right',padding:'10px 10px',borderBottom:'1px solid var(--border-subtle)',fontSize:13,fontWeight:600}}>{formatMoney(item.total, currency)}</td>
              </tr>
            );
          })}
        </tbody>
      </table>
      <div style={{marginLeft:'auto',width:320}}>
        <div style={{display:'flex',justifyContent:'space-between',padding:'5px 0',fontSize:13,color:'var(--text-secondary)',borderTop:'1px solid var(--border-subtle)'}}><span>Subtotal</span><span>{formatMoney(subtotal, currency)}</span></div>
        <div style={{display:'flex',justifyContent:'space-between',padding:'5px 0',fontSize:13,color:'var(--text-secondary)',borderTop:'1px solid var(--border-subtle)'}}><span>{vatLabel}</span><span>{formatMoney(vatAmount, currency)}</span></div>
        <div style={{display:'flex',justifyContent:'space-between',padding:'10px 0',fontSize:16,fontWeight:700,borderTop:'2px solid var(--border)',marginTop:4}}><span>Total Due</span><span style={{color:'var(--accent)'}}>{formatMoney(grandTotal, currency)}</span></div>
      </div>
    </div>
  );
}

export default function InvoicesIndex({ invoices, leases, tenants, flash = {} }) {
  const { props } = usePage();
  const flashData = props.flash ?? {};
  const [toast, setToast] = useState({ msg: '', type: '' });
  const [filter, setFilter] = useState('all');
  const [search, setSearch] = useState('');
  const [sort, setSort] = useState('date-desc');
  const [selected, setSelected] = useState(null);
  const [showModal, setShowModal] = useState(false);
  const [invType, setInvType] = useState('proforma');
  const [items, setItems] = useState([{description:'',sub_description:'',quantity:1,unit_price:0}]);

  const { data, setData, post, processing, reset, transform } = useForm({ type:'invoice', lease_id:'', tenant_name:'', tenant_email:'', unit_ref:'', issued_date:'2026-03-19', due_date:'', period:'', notes:'', items:[] });

  const total = (inv) => (inv.items||[]).reduce((s,i)=>s+Number(i.total),0);

  const selectedLease = leases.find((l) => String(l.id) === String(data.lease_id));
  const modalCurrency = normalizeCurrency(selectedLease?.currency || selectedLease?.unit?.currency);

  const filtered = invoices.filter(inv => {
    const matchFilter = filter === 'all' || inv.status === filter;
    const q = search.toLowerCase();
    const matchSearch = !q || inv.tenant_name?.toLowerCase().includes(q) || inv.unit_ref?.toLowerCase().includes(q) || inv.invoice_number?.toLowerCase().includes(q);
    return matchFilter && matchSearch;
  }).sort((a, b) => {
    if (sort === 'date-desc') return String(b.invoice_number || '').localeCompare(String(a.invoice_number || ''));
    if (sort === 'date-asc') return String(a.invoice_number || '').localeCompare(String(b.invoice_number || ''));
    if (sort === 'due') return String(a.due_date || '').localeCompare(String(b.due_date || ''));
    if (sort === 'amount-hi') return total(b) - total(a);
    return 0;
  });

  const counts = {
    all: invoices.length,
    proforma: invoices.filter(i=>i.status==='proforma').length,
    unpaid: invoices.filter(i=>i.status==='unpaid' || i.status==='draft').length,
    partially_paid: invoices.filter(i=>i.status==='partially_paid').length,
    paid: invoices.filter(i=>i.status==='paid').length,
    overdue: invoices.filter(i=>i.status==='overdue').length,
  };
  const collectedTotals = invoices
    .filter(i => i.status === 'paid')
    .reduce((acc, inv) => {
      const currency = resolveInvoiceCurrency(inv, leases);
      acc[currency] += Number(total(inv) || 0);
      return acc;
    }, { USD: 0, TZS: 0 });
  const collectedLabel = ['USD', 'TZS']
    .filter((currency) => collectedTotals[currency] > 0)
    .map((currency) => formatMoneyCompact(collectedTotals[currency], currency))
    .join(' + ') || '—';
  const modalSubtotal = items.reduce((s, i) => s + (Number(i.quantity) * Number(i.unit_price)), 0);
  const modalVat = modalSubtotal * VAT_RATE;
  const modalTotal = modalSubtotal + modalVat;


  const getLeaseUnitRef = (lease) => {
    if (!lease) return '';
    return lease.unit?.number || lease.unit?.unit_number || lease.unit_ref || '';
  };

  const getLeaseTenantName = (lease) => lease?.tenant?.name || lease?.tenant_name || '';
  const getLeaseTenantEmail = (lease) => lease?.tenant?.email || lease?.tenant_email || '';

  // Compute the billing period for the next invoice for a given lease.
  // Prefers DB installment records (same source as the Payment Schedule) and
  // falls back to arithmetic derivation for leases without installments yet.
  const getNextBillingPeriod = (lease) => {
    const installments = Array.isArray(lease.installments) ? lease.installments : [];

    if (installments.length > 0) {
      // The installment.invoice_id is null until an invoice is raised for it.
      // Pick the first unlinked installment in chronological order.
      const next = installments
        .slice()
        .sort((a, b) => (a.period_start > b.period_start ? 1 : -1))
        .find(inst => !inst.invoice_id);

      if (next) {
        return { start: next.period_start, end: next.period_end, text: `${next.period_start} - ${next.period_end}` };
      }
    }

    // Fallback: derive from rent_start_date + count of rent invoices already issued
    const rentStart = lease.rent_start_date || lease.start_date;
    if (!rentStart) return { start: '', end: '', text: '' };

    const cycleMonths = Number(lease.payment_cycle) || 3;
    const leaseEnd = lease.end_date;

    const invoicedCount = invoices.filter(inv =>
      String(inv.lease_id) === String(lease.id) &&
      (inv.items || []).some(item =>
        String(item.description || '').toLowerCase().includes('rental payment')
      )
    ).length;

    const startDate = new Date(`${rentStart}T00:00:00`);
    startDate.setMonth(startDate.getMonth() + invoicedCount * cycleMonths);

    const endDate = new Date(startDate);
    endDate.setMonth(endDate.getMonth() + cycleMonths);
    endDate.setDate(endDate.getDate() - 1);

    // Clamp to lease end date (mirrors what the PHP controller and payment schedule do)
    if (leaseEnd) {
      const leaseEndDate = new Date(`${leaseEnd}T00:00:00`);
      if (endDate > leaseEndDate) {
        endDate.setTime(leaseEndDate.getTime());
      }
    }

    const start = startDate.toISOString().slice(0, 10);
    const end = endDate.toISOString().slice(0, 10);
    return { start, end, text: `${start} - ${end}` };
  };

  const buildLeaseInvoiceItems = (lease) => {
    const unitRef = getLeaseUnitRef(lease);
    const monthlyRent = Number(lease.monthly_rent ?? lease.rent ?? 0) || 0;
    const paymentCycle = Number(lease.payment_cycle ?? 1) || 1;
    const unitServiceCharge = Number(lease.unit?.service_charge ?? 0);
    const vatRate = Number(lease.vat_rate ?? VAT_RATE * 100) || 0;
    const possessionDate = lease.possession_date || lease.start_date || '';
    const rentStartDate = lease.rent_start_date || lease.start_date || '';
    const billingPeriod = getNextBillingPeriod(lease);
    const period = billingPeriod.text;
    const fitoutDays = lease.fitout_enabled ? (Number(lease.fitout_days) || 0) : 0;
    const dailyServiceCharge = unitServiceCharge / 30;
    const fitoutExtraSC = fitoutDays > 0 ? Math.round(dailyServiceCharge * fitoutDays * 100) / 100 : 0;
    // Fit-out charges only appear if no prior invoice for this lease already contains them
    const fitoutAlreadyInvoiced = invoices.some(inv =>
      String(inv.lease_id) === String(lease.id) &&
      inv.items?.some(item => String(item.description ?? '').includes('Fit-Out Period'))
    );

    const nextItems = [{
      description: `Rental Payment - Unit ${unitRef || '-'}`,
      sub_description: period,
      quantity: paymentCycle,
      unit_price: monthlyRent,
    }];

    if (unitServiceCharge > 0) {
      nextItems.push({
        description: `Service Charge`,
        sub_description: period || 'Building/common services',
        quantity: paymentCycle,
        unit_price: unitServiceCharge,
      });
    }

    if (!fitoutAlreadyInvoiced && fitoutExtraSC > 0) {
      nextItems.push({
        description: `Service Charge - Fit-Out Period (${fitoutDays} days)`,
        sub_description: `${possessionDate} to ${rentStartDate} (rent-free period)`,
        quantity: 1,
        unit_price: fitoutExtraSC,
      });
    }


    return nextItems;
  };

  useEffect(() => {
    if (!flash?.created_invoice_id) return;

    const created = invoices.find((inv) => Number(inv.id) === Number(flash.created_invoice_id));
    if (created) {
      setSelected(created);
    }
  }, [flash?.created_invoice_id, invoices]);

  useEffect(() => {
    const show = (msg, type, ms) => {
      setToast({ msg, type });
      window.setTimeout(() => setToast({ msg: '', type: '' }), ms);
    };
    if (flashData.success) show(flashData.success, 'success', 3500);
    if (flashData.warning) show(flashData.warning, 'warning', 5000);
    if (flashData.error)   show(flashData.error,   'error',   4000);
  }, [flashData.success, flashData.warning, flashData.error]);

  const openInvoiceModal = () => {
    setInvType('proforma');
    reset();
    setData('issued_date', '2026-03-19');
    setItems([{ description: '', sub_description: '', quantity: 1, unit_price: 0 }]);
    setShowModal(true);
  };

  const onLeaseChange = (leaseId) => {
    setData('lease_id', leaseId);
    if (!leaseId) return;

    const l = leases.find((x) => String(x.id) === String(leaseId));
    if (!l) return;

    const tenantName = getLeaseTenantName(l);
    const tenantEmail = getLeaseTenantEmail(l);
    const unitRef = getLeaseUnitRef(l);
    const billingPeriod = getNextBillingPeriod(l);

    setData('tenant_name', tenantName);
    setData('tenant_email', tenantEmail);
    setData('unit_ref', unitRef);
    if (billingPeriod.text) setData('period', billingPeriod.text);

    // All lease invoices are proforma by default (server enforces this too)
    setInvType('proforma');

    setItems(buildLeaseInvoiceItems(l));
  };

  useEffect(() => {
    if (!data.lease_id) return;
    const selectedLease = leases.find((x) => String(x.id) === String(data.lease_id));
    if (!selectedLease) return;

    const billingPeriod = getNextBillingPeriod(selectedLease);
    setData(d => ({
      ...d,
      tenant_name: getLeaseTenantName(selectedLease),
      tenant_email: getLeaseTenantEmail(selectedLease),
      unit_ref: getLeaseUnitRef(selectedLease),
      period: billingPeriod.text || d.period,
    }));
    setItems(buildLeaseInvoiceItems(selectedLease));
  }, [data.lease_id, leases]);

  const markPaid = (inv) => router.patch(`/invoices/${inv.id}`, { status:'paid' }, { onSuccess: () => setSelected(s=>s?{...s,status:'paid'}:null) });

  const showError = (msg) => {
    setToast({ msg, type: 'error' });
    window.setTimeout(() => setToast({ msg: '', type: '' }), 5000);
  };

  const submit = (e, action = 'send') => {
    e.preventDefault();
    if (!data.tenant_name?.trim()) { showError('Tenant name is required.'); return; }
    if (!data.issued_date) { showError('Issue date is required.'); return; }
    if (!data.due_date && action !== 'draft') { showError('Due date is required.'); return; }
    const hasAmount = items.some((i) => Number(i.quantity) > 0 && Number(i.unit_price) > 0);
    if (!hasAmount) { showError('Add at least one line item with a quantity and price.'); return; }

    transform((form) => {
      const matchedTenant = tenants.find(
        (t) => String(t.name || '').toLowerCase() === String(form.tenant_name || '').trim().toLowerCase()
      );

      return {
      ...form,
      type: invType,
      unit_ref: form.unit_ref?.trim() || '—',
      tenant_email: form.tenant_email || matchedTenant?.email || null,
      items,
      notes: form.notes?.trim() || null,
      ...(action === 'draft' ? { status: 'draft' } : {}),
      };
    });

    post('/invoices', {
      preserveScroll: true,
      onSuccess: () => {
        reset();
        setItems([{description:'',sub_description:'',quantity:1,unit_price:0}]);
        setShowModal(false);
      },
      onError: (errs) => {
        const first = Object.values(errs)[0];
        setToast({ msg: first || 'Failed to create invoice. Please check the form.', type: 'error' });
        window.setTimeout(() => setToast({ msg: '', type: '' }), 5000);
      },
    });
  };

  const addItem = () => setItems([...items, {description:'',sub_description:'',quantity:1,unit_price:0}]);
  const updateItem = (i, field, val) => { const arr=[...items]; arr[i]={...arr[i],[field]:val}; setItems(arr); };

  return (
    <AppLayout title="Invoices" subtitle="All invoices">
      <Head title="Invoices" />

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

      <div className="tn-stats-row">
        <div className="tn-stat"><div className="tn-stat-value">{counts.all}</div><div className="tn-stat-label">Total Invoices</div></div>
        <div className="tn-stat-divider"></div>
        <div className="tn-stat"><div className="tn-stat-value" style={{color:'var(--amber)'}}>{counts.proforma}</div><div className="tn-stat-label">Proforma</div></div>
        <div className="tn-stat-divider"></div>
        <div className="tn-stat"><div className="tn-stat-value" style={{color:'var(--accent)'}}>{counts.unpaid}</div><div className="tn-stat-label">Unpaid</div></div>
        <div className="tn-stat-divider"></div>
        <div className="tn-stat"><div className="tn-stat-value" style={{color:'var(--amber)'}}>{counts.partially_paid}</div><div className="tn-stat-label">Partially Paid</div></div>
        <div className="tn-stat-divider"></div>
        <div className="tn-stat"><div className="tn-stat-value" style={{color:'var(--red)'}}>{counts.overdue}</div><div className="tn-stat-label">Overdue</div></div>
        <div className="tn-stat-divider"></div>
        <div className="tn-stat"><div className="tn-stat-value" style={{color:'var(--green)'}}>{collectedLabel}</div><div className="tn-stat-label">Collected</div></div>
      </div>

      <div className="toolbar">
        <div className="filters">
          {[['all','All'],['proforma','Proforma'],['unpaid','Unpaid'],['partially_paid','Partially Paid'],['paid','Paid'],['overdue','Overdue']].map(([f,l])=>(
            <button key={f} className={`filter-pill ${filter===f?'active':''}`} onClick={()=>setFilter(f)}>{l} <span className="pill-count">{counts[f]||0}</span></button>
          ))}
        </div>
        <div style={{display:'flex',gap:8,alignItems:'center'}}>
          <div className="search-box" style={{width:190}}>
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <input type="text" placeholder="Search invoices…" value={search} onChange={e=>setSearch(e.target.value)} />
          </div>
          <select className="form-input form-select" value={sort} onChange={e=>setSort(e.target.value)} style={{width:130,padding:'6px 28px 6px 10px',fontSize:'12.5px'}}>
            <option value="date-desc">Newest First</option>
            <option value="date-asc">Oldest First</option>
            <option value="due">Due Date</option>
            <option value="amount-hi">Amount ↓</option>
          </select>
          <button className="btn-primary" onClick={openInvoiceModal}>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            New Invoice
          </button>
        </div>
      </div>

      <div className="card">
        <table className="data-table">
          <thead><tr><th>Invoice #</th><th>Type</th><th>Tenant</th><th>Unit</th><th>Issue Date</th><th>Due Date</th><th>Amount</th><th>Status</th><th></th></tr></thead>
          <tbody>
            {filtered.map(inv => (
              <tr key={inv.id} onClick={()=>setSelected(inv)}>
                <td style={{fontWeight:700,color:'var(--accent)'}}>{inv.invoice_number}</td>
                <td><span style={{fontSize:'11.5px',fontWeight:600,padding:'3px 9px',borderRadius:20,background:inv.type==='proforma'?'var(--amber-dim)':'var(--accent-dim)',color:inv.type==='proforma'?'var(--amber)':'var(--accent)'}}>{inv.type==='proforma'?'Proforma':'Tax Invoice'}</span></td>
                <td style={{fontWeight:500}}>{inv.tenant_name}</td>
                <td style={{fontWeight:600,color:'var(--text-secondary)'}}>{inv.unit_ref}</td>
                <td style={{fontSize:'12.5px',color:'var(--text-muted)'}}>{inv.issued_date}</td>
                <td style={{fontSize:'12.5px',color:inv.status==='overdue'?'var(--red)':'var(--text-secondary)'}}>{inv.due_date||'—'}</td>
                <td style={{fontWeight:700}}>{formatMoney(total(inv), resolveInvoiceCurrency(inv, leases))}</td>
                <td><span className={`badge ${inv.status}`}>{invoiceStatusLabel(inv.status)}</span></td>
                <td><button className="action-dots" onClick={e=>{e.stopPropagation();setSelected(inv)}}>···</button></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Invoice Drawer */}
      <div className={`drawer-overlay ${selected?'open':''}`} onClick={e=>e.target===e.currentTarget&&setSelected(null)}>
        <div className="drawer" style={{width:560}}>
          {selected && <>
            <div style={{height:52,borderBottom:'1px solid var(--border-subtle)',display:'flex',alignItems:'center',justifyContent:'space-between',padding:'0 20px',flexShrink:0,background:'var(--bg-surface)'}}>
              <div style={{fontSize:'13.5px',fontWeight:600}}>{selected.invoice_number} — {selected.tenant_name}</div>
              <div style={{display:'flex',gap:6}}>
                <button className="icon-btn" title="Save PDF" onClick={()=>window.location.href=`/invoices/${selected.id}/pdf`}><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg></button>
                <button className="drawer-close" onClick={()=>setSelected(null)}>✕</button>
              </div>
            </div>
            <div style={{flex:1,overflowY:'auto',padding:24,scrollbarWidth:'thin',scrollbarColor:'var(--border) transparent'}}>
              <InvoiceDoc inv={selected} currency={resolveInvoiceCurrency(selected, leases)} lease={leases.find((l) => Number(l.id) === Number(selected?.lease_id))} />
            </div>
            <div style={{padding:'12px 20px',borderTop:'1px solid var(--border-subtle)',display:'flex',gap:8,flexShrink:0,background:'var(--bg-surface)'}}>
              {(selected.status==='unpaid'||selected.status==='overdue'||selected.status==='partially_paid') && <button className="btn-primary" style={{flex:1,justifyContent:'center'}} onClick={()=>markPaid(selected)}>✓ Mark as Paid</button>}
              {selected.status==='proforma' && <button className="btn-primary" style={{flex:1,justifyContent:'center'}} onClick={()=>router.patch(`/invoices/${selected.id}`,{status:'unpaid'},{onSuccess:()=>setSelected(s=>s?{...s,status:'unpaid'}:null)})}>Convert to Invoice</button>}
              {selected.status==='paid' && <button className="btn-secondary" style={{flex:1,justifyContent:'center'}} onClick={()=>window.location.href=`/invoices/${selected.id}/pdf`}>Save PDF</button>}
              {selected.type === 'proforma' && selected.tenant_email
                ? <button className="btn-secondary" onClick={()=>router.post(`/invoices/${selected.id}/send`,{},{preserveScroll:true,onSuccess:()=>setSelected(null)})}>Email Proforma</button>
                : <button className="btn-secondary" disabled title={selected.type==='invoice'?'Tax invoice — proforma was emailed at issue time':'No tenant email on file'} style={{opacity:.5,cursor:'not-allowed'}}>Email</button>
              }
            </div>
          </>}
        </div>
      </div>

      {/* New Invoice Modal */}
      <div className={`modal-overlay ${showModal?'open':''}`} onClick={e=>e.target===e.currentTarget&&setShowModal(false)}>
        <div className="modal" style={{width:520,maxHeight:'92vh',display:'flex',flexDirection:'column'}}>
          <div className="modal-header" style={{flexShrink:0}}><div className="modal-title">New Proforma Invoice</div><button className="modal-close" onClick={()=>setShowModal(false)}>✕</button></div>
          <form onSubmit={(e)=>submit(e, 'send')} style={{display:'flex',flexDirection:'column',flex:1,overflow:'hidden'}}>
            <div className="modal-body" style={{overflowY:'auto',flex:1}}>

              <div className="form-row">
                <div className="form-group">
                  <label className="form-label">Link to Lease (optional)</label>
                  <select className="form-input form-select" value={data.lease_id || ''} onChange={(e)=>onLeaseChange(e.target.value)}>
                    <option value="">- Manual entry -</option>
                    {leases.map((l) => (
                      <option key={l.id} value={l.id}>{`${getLeaseUnitRef(l) || 'Unit'} - ${getLeaseTenantName(l) || 'Tenant'} (${l.status || 'active'})`}</option>
                    ))}
                  </select>
                </div>
                <div className="form-group"><label className="form-label">Issue Date *</label><input className="form-input" type="date" value={data.issued_date} onChange={e=>setData('issued_date',e.target.value)} required /></div>
              </div>

              <div className="form-row">
                <div className="form-group"><label className="form-label">Tenant / Billed To *</label><input className="form-input" value={data.tenant_name} onChange={e=>setData('tenant_name',e.target.value)} placeholder="Full name" required /></div>
                <div className="form-group"><label className="form-label">Unit</label><input className="form-input" value={data.unit_ref} onChange={e=>setData('unit_ref',e.target.value)} placeholder="e.g. A-101" /></div>
              </div>
              <div className="form-row">
                <div className="form-group"><label className="form-label">Due Date *</label><input className="form-input" type="date" value={data.due_date} onChange={e=>setData('due_date',e.target.value)} /></div>
                <div className="form-group"><label className="form-label">Period Covered</label><input className="form-input" value={data.period} onChange={e=>setData('period',e.target.value)} placeholder="e.g. Apr - Jun 2026" /></div>
              </div>

              <div style={{marginBottom:14}}>
                <label className="form-label" style={{marginBottom:8,display:'block'}}>Line Items *</label>
                {items.map((item,i)=>(
                  <div key={i} style={{display:'flex',gap:6,alignItems:'flex-start',marginBottom:8}}>
                    <div style={{flex:2}}><input className="form-input" placeholder="Description" value={item.description} onChange={e=>updateItem(i,'description',e.target.value)} style={{marginBottom:4}} /><input className="form-input" placeholder="Sub-description" value={item.sub_description} onChange={e=>updateItem(i,'sub_description',e.target.value)} style={{fontSize:12}} /></div>
                    <div style={{flex:'0 0 60px'}}><input className="form-input" type="number" placeholder="Qty" value={item.quantity} onChange={e=>updateItem(i,'quantity',+e.target.value)} /></div>
                    <div style={{flex:'0 0 100px'}}><input className="form-input" type="number" placeholder="Amount" value={item.unit_price} onChange={e=>updateItem(i,'unit_price',+e.target.value)} /></div>
                    {items.length>1 && <button type="button" className="btn-ghost" style={{flex:'0 0 32px',height:38,padding:0,justifyContent:'center',display:'flex',alignItems:'center'}} onClick={()=>setItems(items.filter((_,j)=>j!==i))}>✕</button>}
                  </div>
                ))}
                <button type="button" className="btn-ghost" style={{width:'100%',marginTop:8,justifyContent:'center',display:'flex',alignItems:'center',gap:6}} onClick={addItem}>
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                  Add Line Item
                </button>
                {modalTotal > 0 && (
                  <div style={{background:'var(--bg-elevated)',borderRadius:9,padding:'12px 14px',fontSize:13,color:'var(--text-secondary)'}}>
                    <div style={{display:'flex',justifyContent:'space-between',padding:'3px 0'}}><span style={{color:'var(--text-muted)'}}>Subtotal</span><span>{formatMoney(modalSubtotal, modalCurrency)}</span></div>
                    <div style={{display:'flex',justifyContent:'space-between',padding:'3px 0'}}><span style={{color:'var(--text-muted)'}}>VAT (18%)</span><span>{formatMoney(modalVat, modalCurrency)}</span></div>
                    <div style={{display:'flex',justifyContent:'space-between',paddingTop:8,marginTop:6,borderTop:'1px solid var(--border)'}}><span style={{fontWeight:700}}>Total Due</span><span style={{color:'var(--accent)',fontWeight:700}}>{formatMoney(modalTotal, modalCurrency)}</span></div>
                  </div>
                )}
              </div>
              <div className="form-row"><div className="form-group"><label className="form-label">Notes / Payment Instructions</label><textarea className="form-input" value={data.notes} onChange={e=>setData('notes',e.target.value)} rows={2} style={{resize:'vertical'}} placeholder="Bank details, reference number, payment instructions..." /></div></div>
            </div>
            <div className="modal-footer" style={{flexShrink:0}}>
              <button type="button" className="btn-ghost" onClick={()=>setShowModal(false)}>Cancel</button>
              <button type="button" className="btn-secondary" onClick={(e)=>submit(e, 'draft')} disabled={processing}>Save as Draft</button>
              <button type="submit" className="btn-primary" disabled={processing}>Issue Invoice</button>
            </div>
          </form>
        </div>
      </div>
    </AppLayout>
  );
}
