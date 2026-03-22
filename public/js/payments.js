/* ════════════════════════════════════════════════════════════════════════════
   PAYMENTS PAGE JAVASCRIPT — Exact match to rental-dashboard.html
   ════════════════════════════════════════════════════════════════════════════ */

const FX_RATE = 2650;

// ─────── Data Structures ───────

const TENANTS = {
  'SR': {
    name: 'Sarah Rutto',
    email: 'sarah.rutto@email.com',
    phone: '+255 784 123456',
    initials: 'SR',
    color: 'rgba(59, 130, 246, 0.18)',
    tc: 'var(--accent)'
  },
  'BK': {
    name: 'Benjamin Kipchoge',
    email: 'benjamin.k@email.com',
    phone: '+255 789 234567',
    initials: 'BK',
    color: 'rgba(34, 197, 94, 0.18)',
    tc: 'var(--green)'
  },
  'AM': {
    name: 'Amina Musa',
    email: 'amina.musa@email.com',
    phone: '+255 712 345678',
    initials: 'AM',
    color: 'rgba(245, 158, 11, 0.18)',
    tc: 'var(--amber)'
  },
  'JO': {
    name: 'James Omondi',
    email: 'james.omondi@email.com',
    phone: '+255 756 456789',
    initials: 'JO',
    color: 'rgba(239, 68, 68, 0.18)',
    tc: 'var(--red)'
  },
  'FN': {
    name: 'Fatima Nassir',
    email: 'fatima.nassir@email.com',
    phone: '+255 767 567890',
    initials: 'FN',
    color: 'rgba(168, 85, 247, 0.18)',
    tc: 'var(--purple)'
  },
  'CK': {
    name: 'Charles Kiplagat',
    email: 'charles.k@email.com',
    phone: '+255 722 678901',
    initials: 'CK',
    color: 'rgba(96, 165, 250, 0.18)',
    tc: 'var(--cyan)'
  },
  'LW': {
    name: 'Lisa Wilson',
    email: 'lisa.wilson@email.com',
    phone: '+255 776 789012',
    initials: 'LW',
    color: 'rgba(167, 139, 250, 0.18)',
    tc: 'var(--indigo)'
  },
  'PO': {
    name: 'Peter Ochieng',
    email: 'peter.o@email.com',
    phone: '+255 788 890123',
    initials: 'PO',
    color: 'rgba(244, 63, 94, 0.18)',
    tc: 'var(--rose)'
  },
  'NM': {
    name: 'Naomi Mwamburi',
    email: 'naomi.mw@email.com',
    phone: '+255 754 901234',
    initials: 'NM',
    color: 'rgba(59, 130, 246, 0.18)',
    tc: 'var(--accent)'
  },
  'DK': {
    name: 'David Kamau',
    email: 'david.kamau@email.com',
    phone: '+255 777 012345',
    initials: 'DK',
    color: 'rgba(34, 197, 94, 0.18)',
    tc: 'var(--green)'
  },
  'RN': {
    name: 'Rachel Njoroge',
    email: 'rachel.n@email.com',
    phone: '+255 745 123456',
    initials: 'RN',
    color: 'rgba(245, 158, 11, 0.18)',
    tc: 'var(--amber)'
  },
  'MO': {
    name: 'Mohamed Omar',
    email: 'mohamed.o@email.com',
    phone: '+255 768 234567',
    initials: 'MO',
    color: 'rgba(239, 68, 68, 0.18)',
    tc: 'var(--red)'
  }
};

const PAY_STATUS = {
  paid: {
    label: 'Paid',
    bg: 'rgba(34, 197, 94, 0.1)',
    color: 'var(--green)'
  },
  overpaid: {
    label: 'Overpaid',
    bg: 'rgba(59, 130, 246, 0.1)',
    color: 'var(--accent)'
  },
  partially_paid: {
    label: 'Partial',
    bg: 'rgba(245, 158, 11, 0.1)',
    color: 'var(--amber)'
  },
  overdue: {
    label: 'Overdue',
    bg: 'rgba(239, 68, 68, 0.1)',
    color: 'var(--red)'
  },
  pending: {
    label: 'Pending',
    bg: 'rgba(107, 114, 128, 0.1)',
    color: 'var(--text-secondary)'
  }
};

let PAYMENTS_DATA = [
  {
    id: 'P001',
    tenant: 'SR',
    unit: 'A-101',
    month: 'Mar 2026',
    invoiceId: 'INV-1001',
    invoiceAmount: 1289,
    amountPaid: 1350,
    credit: 61,
    method: 'Bank Transfer',
    status: 'overpaid',
    date: 'Mar 1, 2026'
  },
  {
    id: 'P002',
    tenant: 'JO',
    unit: 'B-205',
    month: 'Mar 2026',
    invoiceId: 'INV-1002',
    invoiceAmount: 2100,
    amountPaid: 1500,
    credit: -600,
    method: 'Mpesa',
    status: 'partially_paid',
    date: 'Mar 5, 2026'
  },
  {
    id: 'P003',
    tenant: 'AM',
    unit: 'C-310',
    month: 'Mar 2026',
    invoiceId: 'INV-1003',
    invoiceAmount: 1850,
    amountPaid: 1850,
    credit: 0,
    method: 'Bank Transfer',
    status: 'paid',
    date: 'Mar 3, 2026'
  }
];

const TENANT_CREDITS = {
  'SR': 183000,
  'BK': 0,
  'AM': 0,
  'JO': 0,
  'FN': 0,
  'CK': 0,
  'LW': 0,
  'PO': 0,
  'NM': 0,
  'DK': 0,
  'RN': 0,
  'MO': 0
};

const CREDIT_HISTORY = [
  {
    tenant: 'SR',
    date: 'Mar 1, 2026',
    amount: 183000,
    desc: 'Overpayment on INV-1001',
    type: 'credit'
  },
  {
    tenant: 'SR',
    date: 'Feb 15, 2026',
    amount: -50000,
    desc: 'Applied to Feb 2026 invoice',
    type: 'applied'
  }
];

// ─────── Formatting ───────

function fmtTZS(usdAmount, opts = {}) {
  const tzs = Math.round(usdAmount * FX_RATE);
  if (opts.compact) {
    if (tzs >= 1_000_000) return 'TZS ' + (tzs / 1_000_000).toFixed(1) + 'M';
    if (tzs >= 1_000) return 'TZS ' + (tzs / 1_000).toFixed(0) + 'k';
  }
  return 'TZS ' + tzs.toLocaleString();
}

function fmtTZSc(usdAmount) {
  return fmtTZS(usdAmount, { compact: true });
}

// ─────── Page State ───────

let payFilter = 'all';
let paySearch = '';
let payMonth = new Date().toLocaleString('default', { month: 'short', year: 'numeric' });

// ─────── Event Handlers ───────

function switchPayTab(tabName) {
  // Hide all tabs
  document.querySelectorAll('.prof-tab-content').forEach(el => {
    el.classList.remove('active');
  });

  // Deactivate all tab buttons
  document.querySelectorAll('.prof-tab').forEach(el => {
    el.classList.remove('active');
  });

  // Show active tab
  const tabEl = document.getElementById(`pay-${tabName}-tab`);
  if (tabEl) {
    tabEl.classList.add('active');
  }

  // Activate tab button - find the clicked button
  const tabBtn = document.querySelector(`[data-tab="${tabName}"]`);
  if (tabBtn) {
    tabBtn.classList.add('active');
  }

  if (tabName === 'ledger') {
    renderPayments();
  } else if (tabName === 'credits') {
    renderCreditsTab();
  }
}

function setPayFilter(status) {
  payFilter = status;

  // Update active filter pill
  document.querySelectorAll('.filter-pill').forEach(el => {
    el.classList.remove('active');
  });
  
  // Find and activate the clicked pill
  const activePill = document.querySelector(`[data-status="${status}"]`);
  if (activePill) {
    activePill.classList.add('active');
  }

  renderPayments();
}

function setPayMonth(month) {
  payMonth = month;
  renderPayments();
}

function setPaySearch(query) {
  paySearch = query.toLowerCase();
  renderPayments();
}

function updatePayStats() {
  const filtered = getFilteredPayments();

  const collected = filtered
    .filter(p => p.status === 'paid' || p.status === 'overpaid')
    .reduce((sum, p) => sum + p.amountPaid, 0);

  const overdue = filtered
    .filter(p => p.status === 'overdue')
    .reduce((sum, p) => sum + p.invoiceAmount, 0);

  const shortfall = filtered
    .filter(p => p.status === 'partially_paid')
    .reduce((sum, p) => sum + Math.max(0, p.invoiceAmount - p.amountPaid), 0);

  const credits = filtered.reduce((sum, p) => sum + Math.max(0, p.credit), 0);

  document.querySelector('[data-stat="collected"]').textContent = fmtTZSc(collected);
  document.querySelector('[data-stat="overdue"]').textContent = fmtTZSc(overdue);
  document.querySelector('[data-stat="shortfall"]').textContent = fmtTZSc(shortfall);
  document.querySelector('[data-stat="credits"]').textContent = fmtTZSc(credits);
}

function getFilteredPayments() {
  return PAYMENTS_DATA.filter(p => {
    const tenantName = TENANTS[p.tenant]?.name.toLowerCase() || '';
    const matchesFilter = payFilter === 'all' || p.status === payFilter;
    const matchesSearch = !paySearch || tenantName.includes(paySearch);
    const matchesMonth =
      !payMonth || p.month.toLowerCase() === payMonth.toLowerCase();

    return matchesFilter && matchesSearch && matchesMonth;
  });
}

function renderPayments() {
  const filtered = getFilteredPayments();
  const tbody = document.querySelector('.payments-table tbody');

  if (!tbody) return;

  tbody.innerHTML = filtered
    .map(p => {
      const tenant = TENANTS[p.tenant];
      const status = PAY_STATUS[p.status];
      const variance = p.amountPaid - p.invoiceAmount;
      const varianceClass = variance > 0 ? 'text-green' : variance < 0 ? 'text-red' : '';
      const varianceText =
        variance > 0
          ? `+${fmtTZSc(variance)}`
          : variance < 0
            ? fmtTZSc(Math.abs(variance))
            : '—';

      let statusDisplay = status.label;
      if (p.status === 'partially_paid') {
        const short = p.invoiceAmount - p.amountPaid;
        statusDisplay = `${status.label} — ${fmtTZSc(short)} short`;
      }

      return `
        <tr>
          <td>
            <div class="tenant-cell">
              <div class="t-avatar" style="background:${tenant.color}; color:${tenant.tc};">
                ${tenant.initials}
              </div>
              <div>
                <div style="font-weight:500">${tenant.name}</div>
                <div style="font-size:11px; color:var(--text-muted)">${p.month}</div>
              </div>
            </div>
          </td>
          <td>${p.unit}</td>
          <td>${p.invoiceId}</td>
          <td>${fmtTZSc(p.invoiceAmount)}</td>
          <td>${fmtTZSc(p.amountPaid)}</td>
          <td class="${varianceClass}">${varianceText}</td>
          <td>${p.method}</td>
          <td>
            <div style="background:${status.bg}; color:${status.color}; padding:4px 8px; border-radius:4px; font-size:11px; font-weight:600; display:inline-block;">
              ${statusDisplay}
            </div>
          </td>
          <td style="color:var(--text-muted)">${p.date}</td>
        </tr>
      `;
    })
    .join('');

  updatePayStats();
}

function renderCreditsTab() {
  const creditsSection = document.getElementById('pay-credits-section');
  if (!creditsSection) return;

  const creditsHTML = Object.entries(TENANT_CREDITS)
    .filter(([_, amt]) => amt > 0)
    .map(([initials, amount]) => {
      const tenant = TENANTS[initials];
      return `
        <tr>
          <td>
            <div class="tenant-cell">
              <div class="t-avatar" style="background:${tenant.color}; color:${tenant.tc};">
                ${tenant.initials}
              </div>
              <div>${tenant.name}</div>
            </div>
          </td>
          <td style="text-align:right">${fmtTZSc(amount)}</td>
        </tr>
      `;
    })
    .join('');

  creditsSection.innerHTML = creditsHTML || '<tr><td colspan="2" style="text-align:center; color:var(--text-muted); padding:20px;">No active credits</td></tr>';
}

// ─────── Modal ───────

function openRecordPaymentModal() {
  const modal = document.getElementById('record-payment-modal');
  if (modal) modal.classList.add('open');
}

function closeRecordPaymentModal() {
  const modal = document.getElementById('record-payment-modal');
  if (modal) modal.classList.remove('open');
}

function submitRecordPayment(e) {
  e?.preventDefault();

  // Get form values
  const tenant = document.querySelector('select[name="tenant"]')?.value;
  const amount = parseFloat(document.querySelector('input[name="amount"]')?.value || 0);
  const method = document.querySelector('select[name="method"]')?.value;

  if (!tenant || !amount || !method) {
    alert('Please fill all fields');
    return;
  }

  // Add payment (connect to backend)
  console.log('Recording payment:', { tenant, amount, method });

  // Close modal
  closeRecordPaymentModal();

  // Re-render (would fetch from backend in real app)
  renderPayments();
}

// ─────── Initialization ───────

document.addEventListener('DOMContentLoaded', function () {
  // Render initial data
  renderPayments();
  
  // Tab click handlers
  document.querySelectorAll('.prof-tab').forEach(tab => {
    tab.addEventListener('click', function (e) {
      e.preventDefault();
      switchPayTab(this.dataset.tab);
    });
  });

  // Filter pill click handlers
  document.querySelectorAll('.filter-pill').forEach(pill => {
    pill.addEventListener('click', function (e) {
      e.preventDefault();
      setPayFilter(this.dataset.status);
    });
  });

  // Search input handler
  const searchInput = document.querySelector('input[placeholder="Search ..."]');
  if (searchInput) {
    searchInput.addEventListener('input', e => {
      setPaySearch(e.target.value);
    });
  }

  // Month select handler
  const monthSelect = document.querySelector('select[name="month"]');
  if (monthSelect) {
    monthSelect.addEventListener('change', e => {
      setPayMonth(e.target.value);
    });
  }

  // Modal handlers
  const modalOverlay = document.getElementById('record-payment-modal');
  if (modalOverlay) {
    modalOverlay.addEventListener('click', e => {
      if (e.target === modalOverlay) closeRecordPaymentModal();
    });
  }

  const closeBtn = document.querySelector('.modal-close');
  if (closeBtn) {
    closeBtn.addEventListener('click', e => {
      e.preventDefault();
      closeRecordPaymentModal();
    });
  }

  const submitBtn = document.querySelector('.modal-footer .btn-primary');
  if (submitBtn) {
    submitBtn.addEventListener('click', submitRecordPayment);
  }
});

