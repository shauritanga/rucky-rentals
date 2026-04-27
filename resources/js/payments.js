/**
 * ════════════════════════════════════════════════════════════════════════════
 * PAYMENTS PAGE FUNCTIONALITY
 * ════════════════════════════════════════════════════════════════════════════
 */

// Payment data (mock data - replace with API calls)
let paymentsData = [
  {
    id: 'PAY-001',
    tenant: 'Sarah Rutto',
    unit: 'A-101',
    invoice: 'INV-1001',
    invoiceAmount: 9055050000,
    amountPaid: 9540000000,
    variance: 484950000,
    method: 'Bank Transfer',
    status: 'overpaid',
    date: '2026-03-01',
    reference: 'TZ-2026-03-01-001'
  },
  {
    id: 'PAY-002',
    tenant: 'James Emondi',
    unit: 'C-301',
    invoice: 'INV-1004',
    invoiceAmount: 7049000000,
    amountPaid: 5300000000,
    variance: -1749000000,
    method: 'Mobile Money',
    status: 'partially_paid',
    date: '2026-03-03',
    reference: 'MM-2026-03-03-001'
  },
  {
    id: 'PAY-003',
    tenant: 'Amina Musa',
    unit: 'B-201',
    invoice: 'INV-1003',
    invoiceAmount: 9055050000,
    amountPaid: 9055050000,
    variance: 0,
    method: 'Bank Transfer',
    status: 'paid',
    date: '2026-03-01',
    reference: 'TZ-2026-03-01-002'
  }
];

// Credits data (mock data)
let creditsData = [
  {
    tenantId: 1,
    tenant: 'Sarah Rutto',
    unit: 'A-101',
    amount: 484950000,
    createdDate: '2026-03-01',
    appliedTo: null
  }
];

// Invoices data (for modal)
let invoicesData = [
  { id: 'INV-1001', tenant: 'Sarah Rutto', unit: 'A-101', amount: 9055050000, status: 'overpaid', credit: 0 },
  { id: 'INV-1004', tenant: 'James Emondi', unit: 'C-301', amount: 7049000000, status: 'partially_paid', credit: 0 },
  { id: 'INV-1003', tenant: 'Amina Musa', unit: 'B-201', amount: 9055050000, status: 'paid', credit: 0 }
];

// State
let paymentPageState = {
  currentFilter: 'all',
  currentMonth: '',
  searchTerm: '',
  selectedInvoice: null
};

/**
 * Initialize Payments Page
 */
function initPaymentsPage() {
  loadPaymentsStats();
  renderPayments();
  renderCredits();
  populateInvoiceSelect();
  setPaymentDate();
}

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * PAYMENT LEDGER TAB
 * ═══════════════════════════════════════════════════════════════════════════
 */

/**
 * Load and display stats for payments page
 */
function loadPaymentsStats() {
  const stats = calculatePaymentStats();

  document.getElementById('pst-collected').textContent = formatCurrency(stats.collected);
  document.getElementById('pst-rate-label').textContent = stats.collectionRate + '%';
  
  document.getElementById('pst-overdue').textContent = formatCurrency(stats.overdue);
  document.getElementById('pst-overdue-label').textContent = stats.overdueCount + ' tenants';
  
  document.getElementById('pst-shortfall').textContent = formatCurrency(stats.shortfall);
  document.getElementById('pst-partial-label').textContent = stats.partiallyPaidCount + ' partial';
  
  document.getElementById('pst-credits').textContent = formatCurrency(stats.totalCredits);
  document.getElementById('pst-credit-label').textContent = stats.creditTenantCount + ' tenants';
}

/**
 * Calculate payment statistics
 */
function calculatePaymentStats() {
  const currentMonth = new Date().toLocaleDateString('default', { month: 'long', year: 'numeric' });
  
  const stats = {
    collected: 0,
    total: 0,
    collectionRate: 0,
    overdue: 0,
    overdueCount: 0,
    shortfall: 0,
    partiallyPaidCount: 0,
    totalCredits: 0,
    creditTenantCount: 0
  };

  // Calculate from payments
  paymentsData.forEach(payment => {
    stats.total += payment.invoiceAmount;
    
    if (payment.status === 'paid' || payment.status === 'overpaid') {
      stats.collected += Math.min(payment.invoiceAmount, payment.amountPaid);
    }
    
    if (payment.status === 'overdue') {
      stats.overdue += payment.invoiceAmount;
      stats.overdueCount++;
    }
    
    if (payment.status === 'partially_paid') {
      stats.shortfall += payment.variance;
      stats.partiallyPaidCount++;
    }
  });

  // Calculate credits
  creditsData.forEach(credit => {
    stats.totalCredits += credit.amount;
    stats.creditTenantCount++;
  });

  stats.collectionRate = stats.total > 0 ? Math.round((stats.collected / stats.total) * 100) : 0;

  return stats;
}

/**
 * Get initials from tenant name
 */
function getInitials(name) {
  return name
    .split(' ')
    .map(n => n[0])
    .join('')
    .toUpperCase()
    .slice(0, 2);
}

/**
 * Get avatar color based on initials
 */
function getAvatarColor(initials) {
  const colors = {
    'SR': '#6366F1',  // Indigo for Sarah Rutto
    'JE': '#EF4444',  // Red for James Emondi
    'AM': '#F59E0B',  // Amber for Amina Musa
    'BW': '#3B82F6',
    'EM': '#10B981',
    'JD': '#06B6D4',
    'AS': '#8B5CF6',
    'MJ': '#EC4899',
    'JO': '#EF4444'   // For John if needed
  };
  return colors[initials] || '#6366F1';
}

/**
 * Extract period from date (e.g., "P001 · Mar 2026")
 */
function extractPeriod(date) {
  const dateObj = new Date(date);
  const month = dateObj.toLocaleDateString('en-US', { month: 'short' });
  const year = dateObj.getFullYear();
  // You can customize the period prefix (P001, P002, etc.)
  const periodPrefix = 'P001'; // This should come from data
  return `${periodPrefix} · ${month} ${year}`;
}

/**
 * Format date as "dd/mm/yyyy"
 */
function formatDate(dateString) {
  const date = new Date(dateString);
  if (Number.isNaN(date.getTime())) return dateString;
  const day = String(date.getDate()).padStart(2, '0');
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const year = date.getFullYear();
  return `${day}/${month}/${year}`;
}

/**
 * Format variance with color styling
 */
function formatVarianceDisplay(variance, status) {
  if (status === 'overpaid' || variance > 0) {
    return `<span style="color: var(--green); font-weight: 600;">+${formatCurrencyShort(variance)}</span>`;
  } else if (status === 'partially_paid' || variance < 0) {
    return `<span style="color: #DC2626; font-weight: 600;">(${formatCurrencyShort(Math.abs(variance))})</span>`;
  } else {
    return '<span style="color: var(--text-muted);">—</span>';
  }
}

/**
 * Format status with styling
 */
function formatStatusDisplay(status, variance) {
  const statusStyles = {
    'paid': '<span style="background: rgba(34, 197, 94, 0.15); color: #22C55E; padding: 4px 10px; border-radius: 6px; font-size: 13px; font-weight: 600; display: inline-block; white-space: nowrap;">Paid</span>',
    'overpaid': '<span style="background: rgba(139, 92, 246, 0.15); color: #8B5CF6; padding: 4px 10px; border-radius: 6px; font-size: 13px; font-weight: 600; display: inline-block; white-space: nowrap;">Overpaid</span>',
    'partially_paid': `<span style="background: rgba(245, 158, 11, 0.15); color: #F59E0B; padding: 4px 10px; border-radius: 6px; font-size: 13px; font-weight: 600; display: inline-block; white-space: nowrap;">Partial — ${formatCurrencyShort(Math.abs(variance))} short</span>`,
    'overdue': '<span style="background: rgba(239, 68, 68, 0.15); color: #EF4444; padding: 4px 10px; border-radius: 6px; font-size: 13px; font-weight: 600; display: inline-block; white-space: nowrap;">Overdue</span>',
    'pending': '<span style="background: rgba(107, 114, 128, 0.15); color: #6B7280; padding: 4px 10px; border-radius: 6px; font-size: 13px; font-weight: 600; display: inline-block; white-space: nowrap;">Pending</span>'
  };
  return statusStyles[status] || '<span>Unknown</span>';
}

/**
 * Format currency in short form (e.g., "1,749.0M" for millions)
 */
function formatCurrencyShort(amount) {
  if (amount >= 1000000) {
    return `TZS ${(amount / 1000000).toFixed(1)}M`;
  } else if (amount >= 1000) {
    return `TZS ${(amount / 1000).toFixed(1)}K`;
  }
  return `TZS ${amount}`;
}

/**
 * Render payments table
 */
function renderPayments() {
  const tbody = document.getElementById('paymentsBody');
  
  let filteredPayments = filterPayments();
  
  if (filteredPayments.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="9" style="padding: 40px 16px; text-align: center;">
          <div style="color: var(--text-muted); font-size: 13px;">
            No payments found matching your filters
          </div>
        </td>
      </tr>
    `;
    updatePaymentFilterCounts();
    return;
  }

  tbody.innerHTML = filteredPayments.map(payment => {
    const initials = getInitials(payment.tenant);
    const avatarColor = getAvatarColor(initials);
    const period = extractPeriod(payment.date);
    const formattedDate = formatDate(payment.date);
    const varianceDisplay = formatVarianceDisplay(payment.variance, payment.status);
    const statusDisplay = formatStatusDisplay(payment.status, payment.variance);

    return `
      <tr onclick="viewPaymentDetail('${payment.id}')">
        <td>
          <div style="display: flex; align-items: center; gap: 10px;">
            <div style="width: 36px; height: 36px; border-radius: 8px; background: ${avatarColor}; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 12px; flex-shrink: 0;">
              ${initials}
            </div>
            <div>
              <div style="font-weight: 600; font-size: 14px;">${payment.tenant}</div>
              <div style="font-size: 12px; color: var(--text-muted);">${period}</div>
            </div>
          </div>
        </td>
        <td style="font-weight: 600;">${payment.unit}</td>
        <td><a href="#" onclick="event.preventDefault()" style="color: var(--accent); text-decoration: none; font-weight: 600;">${payment.invoice}</a></td>
        <td style="text-align: right; font-weight: 500;">${formatCurrency(payment.invoiceAmount)}</td>
        <td style="text-align: right; font-weight: 600;">${formatCurrency(payment.amountPaid)}</td>
        <td style="text-align: right;">
          ${varianceDisplay}
        </td>
        <td style="font-size: 13px;">${payment.method || '—'}</td>
        <td>
          ${statusDisplay}
        </td>
        <td style="color: var(--text-muted); font-size: 13px;">${formattedDate}</td>
      </tr>
    `;
  }).join('');

  updatePaymentFilterCounts();
}

/**
 * Filter payments based on current filters
 */
function filterPayments() {
  return paymentsData.filter(payment => {
    // Filter by status
    if (paymentPageState.currentFilter !== 'all' && payment.status !== paymentPageState.currentFilter) {
      return false;
    }

    // Filter by month
    if (paymentPageState.currentMonth && !payment.date.includes(paymentPageState.currentMonth)) {
      return false;
    }

    // Filter by search term
    if (paymentPageState.searchTerm) {
      const searchLower = paymentPageState.searchTerm.toLowerCase();
      return (
        payment.tenant.toLowerCase().includes(searchLower) ||
        payment.unit.toLowerCase().includes(searchLower) ||
        payment.invoice.toLowerCase().includes(searchLower) ||
        payment.reference?.toLowerCase().includes(searchLower)
      );
    }

    return true;
  });
}

/**
 * Update filter pill counts
 */
function updatePaymentFilterCounts() {
  const statuses = ['all', 'paid', 'overpaid', 'partially_paid', 'overdue', 'pending'];
  
  statuses.forEach(status => {
    let count = 0;
    if (status === 'all') {
      count = paymentsData.length;
    } else {
      count = paymentsData.filter(p => p.status === status).length;
    }
    
    const countElement = document.getElementById(`pfc-${status}`);
    if (countElement) {
      countElement.textContent = count;
    }
  });
}

/**
 * Set payment filter
 */
function setPayFilter(element, filter) {
  // Update active state
  document.querySelectorAll('.filter-pill').forEach(pill => pill.classList.remove('active'));
  element.classList.add('active');
  
  paymentPageState.currentFilter = filter;
  renderPayments();
}

/**
 * Handle payment search
 */
function onPaymentSearch(event) {
  paymentPageState.searchTerm = event.target.value;
  renderPayments();
}

/**
 * Handle month filter change
 */
function onPayMonthFilterChange(event) {
  paymentPageState.currentMonth = event.target.value;
  renderPayments();
}

/**
 * View payment detail (placeholder)
 */
function viewPaymentDetail(paymentId) {
  const payment = paymentsData.find(p => p.id === paymentId);
  if (payment) {
    console.log('View payment:', payment);
    // Could open a detail modal here
  }
}

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * RECORD PAYMENT MODAL
 * ═══════════════════════════════════════════════════════════════════════════
 */

/**
 * Open record payment modal
 */
function openRecordPaymentModal() {
  const modal = document.getElementById('recordPaymentModal');
  if (modal) {
    modal.classList.add('open');
  }
}

/**
 * Close record payment modal
 */
function closeRecordPaymentModal() {
  const modal = document.getElementById('recordPaymentModal');
  if (modal) {
    modal.classList.remove('open');
  }
  resetRecordPaymentForm();
}

/**
 * Reset record payment form
 */
function resetRecordPaymentForm() {
  document.getElementById('rp-invoice').value = '';
  document.getElementById('rp-method').value = 'Bank Transfer';
  document.getElementById('rp-amount').value = '';
  document.getElementById('rp-date').value = '';
  document.getElementById('rp-ref').value = '';
  document.getElementById('rp-notes').value = '';
  
  paymentPageState.selectedInvoice = null;
  
  document.getElementById('rp-invoice-card').style.display = 'none';
  document.getElementById('rp-reconcile').style.display = 'none';
  document.getElementById('rp-submit-btn').disabled = true;
}

/**
 * Populate invoice select in modal
 */
function populateInvoiceSelect() {
  const select = document.getElementById('rp-invoice');
  
  invoicesData.forEach(invoice => {
    const option = document.createElement('option');
    option.value = invoice.id;
    option.textContent = `${invoice.id} - ${invoice.tenant} (${invoice.unit})`;
    select.appendChild(option);
  });
}

/**
 * Handle invoice selection change
 */
function onRPInvoiceChange() {
  const invoiceId = document.getElementById('rp-invoice').value;
  
  if (!invoiceId) {
    document.getElementById('rp-invoice-card').style.display = 'none';
    paymentPageState.selectedInvoice = null;
    document.getElementById('rp-amount').value = '';
    updateReconciliation();
    return;
  }

  const invoice = invoicesData.find(inv => inv.id === invoiceId);
  if (!invoice) return;

  paymentPageState.selectedInvoice = invoice;

  // Show invoice card
  const card = document.getElementById('rp-invoice-card');
  card.style.display = 'block';

  document.getElementById('rp-inv-id').textContent = invoice.id;
  document.getElementById('rp-inv-detail').textContent = `${invoice.tenant} • ${invoice.unit}`;
  document.getElementById('rp-inv-amount').textContent = formatCurrency(invoice.amount);

  // Show/hide credit notice
  if (invoice.credit > 0) {
    const creditNotice = document.getElementById('rp-credit-notice');
    creditNotice.style.display = 'block';
    document.getElementById('rp-credit-amount').textContent = formatCurrency(invoice.credit);
  } else {
    document.getElementById('rp-credit-notice').style.display = 'none';
  }

  updateReconciliation();
}

/**
 * Handle amount input change
 */
function onRPAmountChange() {
  updateReconciliation();
}

/**
 * Update reconciliation preview
 */
function updateReconciliation() {
  if (!paymentPageState.selectedInvoice) {
    document.getElementById('rp-reconcile').style.display = 'none';
    return;
  }

  const invoice = paymentPageState.selectedInvoice;
  const amountReceived = parseFloat(document.getElementById('rp-amount').value) || 0;

  // Show reconciliation box
  const reconcileBox = document.getElementById('rp-reconcile');
  reconcileBox.style.display = 'block';

  // Calculate reconciliation
  const invoiceAmount = invoice.amount;
  const credit = invoice.credit || 0;
  const balanceDue = invoiceAmount - credit;
  const variance = amountReceived - balanceDue;

  // Update reconciliation lines
  document.getElementById('rp-rec-invoice').textContent = formatCurrency(invoiceAmount);
  
  if (credit > 0) {
    const creditRow = document.getElementById('rp-rec-credit-row');
    creditRow.style.display = 'flex';
    document.getElementById('rp-rec-credit').textContent = `-${formatCurrency(credit)}`;
  } else {
    document.getElementById('rp-rec-credit-row').style.display = 'none';
  }

  document.getElementById('rp-rec-balance').textContent = formatCurrency(balanceDue);
  document.getElementById('rp-rec-received').textContent = formatCurrency(amountReceived);

  // Update variance display
  const varianceLabel = document.getElementById('rp-rec-variance-label');
  const varianceValue = document.getElementById('rp-rec-variance');

  if (variance > 0) {
    varianceLabel.textContent = 'Overpayment';
    varianceValue.textContent = `+${formatCurrency(variance)}`;
    varianceValue.style.color = 'var(--green)';
  } else if (variance < 0) {
    varianceLabel.textContent = 'Shortfall';
    varianceValue.textContent = formatCurrency(variance);
    varianceValue.style.color = 'var(--red)';
  } else {
    varianceLabel.textContent = 'Variance';
    varianceValue.textContent = 'Balanced';
    varianceValue.style.color = 'var(--text-muted)';
  }

  // Update outcome
  const outcomeBox = document.getElementById('rp-outcome');
  if (amountReceived === 0) {
    outcomeBox.textContent = '';
  } else if (variance > 0) {
    outcomeBox.className = 'reconciliation-outcome success';
    outcomeBox.innerHTML = `✓ ${formatCurrency(variance)} will be credited to account`;
  } else if (variance < 0) {
    outcomeBox.className = 'reconciliation-outcome warning';
    outcomeBox.innerHTML = `⚠ Shortfall of ${formatCurrency(Math.abs(variance))} remains`;
  } else {
    outcomeBox.className = 'reconciliation-outcome success';
    outcomeBox.innerHTML = `✓ Invoice will be fully settled`;
  }

  // Enable/disable submit button
  updateSubmitButtonState();
}

/**
 * Update submit button state
 */
function updateSubmitButtonState() {
  const invoiceId = document.getElementById('rp-invoice').value;
  const amount = document.getElementById('rp-amount').value;
  const date = document.getElementById('rp-date').value;

  const isValid = invoiceId && amount && date && parseFloat(amount) > 0;
  document.getElementById('rp-submit-btn').disabled = !isValid;
}

/**
 * Format date input (set default to today)
 */
function setPaymentDate() {
  const today = new Date().toISOString().split('T')[0];
  document.getElementById('rp-date').value = today;
}

/**
 * Submit record payment form
 */
function submitRecordPayment() {
  const invoice = paymentPageState.selectedInvoice;
  const amount = parseFloat(document.getElementById('rp-amount').value);
  const method = document.getElementById('rp-method').value;
  const date = document.getElementById('rp-date').value;
  const reference = document.getElementById('rp-ref').value;
  const notes = document.getElementById('rp-notes').value;

  if (!invoice || !amount || !date) {
    alert('Please fill in all required fields');
    return;
  }

  // Create payment record
  const newPayment = {
    id: `PAY-${String(paymentsData.length + 1).padStart(3, '0')}`,
    tenant: invoice.tenant,
    unit: invoice.unit,
    invoice: invoice.id,
    invoiceAmount: invoice.amount,
    amountPaid: amount,
    variance: amount - (invoice.amount - (invoice.credit || 0)),
    method: method,
    status: calculatePaymentStatus(amount, invoice.amount, invoice.credit || 0),
    date: date,
    reference: reference || null,
    notes: notes || null
  };

  // Add to payments data
  paymentsData.unshift(newPayment);

  // Handle credit if overpayment
  if (newPayment.variance > 0) {
    const existingCredit = creditsData.find(c => c.tenant === invoice.tenant);
    if (existingCredit) {
      existingCredit.amount += newPayment.variance;
    } else {
      creditsData.push({
        tenantId: Math.random(),
        tenant: invoice.tenant,
        unit: invoice.unit,
        amount: newPayment.variance,
        createdDate: date,
        appliedTo: null
      });
    }
  }

  // Show success message
  showNotification('Payment recorded successfully', 'success');

  // Refresh the page
  closeRecordPaymentModal();
  loadPaymentsStats();
  renderPayments();
  renderCredits();
}

/**
 * Calculate payment status
 */
function calculatePaymentStatus(amountPaid, invoiceAmount, credit) {
  const balanceDue = invoiceAmount - credit;
  
  if (amountPaid >= balanceDue) {
    return amountPaid > balanceDue ? 'overpaid' : 'paid';
  } else if (amountPaid > 0) {
    return 'partially_paid';
  } else {
    return 'pending';
  }
}

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * TENANT CREDITS TAB
 * ═══════════════════════════════════════════════════════════════════════════
 */

/**
 * Render credit balances
 */
function renderCredits() {
  renderCreditBalances();
  renderCreditHistory();
}

/**
 * Render credit balance list
 */
function renderCreditBalances() {
  const container = document.getElementById('creditBalanceList');

  if (creditsData.length === 0) {
    container.innerHTML = `
      <div style="text-align: center; padding: 20px; color: var(--text-muted); font-size: 13px;">
        No tenant credits on account
      </div>
    `;
    return;
  }

  container.innerHTML = creditsData.map((credit, index) => `
    <div class="credit-item">
      <div class="credit-item-tenant">
        <div class="credit-item-name">${credit.tenant}</div>
        <div class="credit-item-unit">${credit.unit}</div>
      </div>
      <div class="credit-item-amount">${formatCurrency(credit.amount)}</div>
    </div>
  `).join('');
}

/**
 * Render credit history
 */
function renderCreditHistory() {
  const container = document.getElementById('creditHistoryList');

  const history = [];
  creditsData.forEach(credit => {
    history.push({
      tenant: credit.tenant,
      type: 'Created',
      amount: credit.amount,
      date: credit.createdDate,
      description: 'Overpayment credited'
    });
  });

  if (history.length === 0) {
    container.innerHTML = `
      <div style="text-align: center; padding: 20px; color: var(--text-muted); font-size: 13px;">
        No credit history
      </div>
    `;
    return;
  }

  container.innerHTML = history.map(item => `
    <div class="credit-item">
      <div class="credit-item-tenant">
        <div class="credit-item-name">${item.tenant}</div>
        <div class="credit-item-unit">${item.description} • ${item.date}</div>
      </div>
      <div class="credit-item-amount" style="color: var(--green);">+${formatCurrency(item.amount)}</div>
    </div>
  `).join('');
}

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * TAB SWITCHING
 * ═══════════════════════════════════════════════════════════════════════════
 */

/**
 * Switch payment tab
 */
function switchPayTab(element, tabId) {
  // Update active tab button
  document.querySelectorAll('.prof-tab').forEach(tab => tab.classList.remove('active'));
  element.classList.add('active');

  // Update active tab content
  document.querySelectorAll('.prof-tab-content').forEach(content => content.classList.remove('active'));
  const tabContent = document.getElementById(tabId);
  if (tabContent) {
    tabContent.classList.add('active');
  }
}

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * UTILITY FUNCTIONS
 * ═══════════════════════════════════════════════════════════════════════════
 */

/**
 * Format currency
 */
function formatCurrency(amount) {
  return new Intl.NumberFormat('en-TZ', {
    style: 'currency',
    currency: 'TZS',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(amount);
}

/**
 * Format status text
 */
function formatStatus(status) {
  const statusMap = {
    'paid': 'Paid',
    'overpaid': 'Overpaid',
    'partially_paid': 'Partial',
    'overdue': 'Overdue',
    'pending': 'Pending'
  };
  return statusMap[status] || status;
}

/**
 * Show notification
 */
function showNotification(message, type = 'info') {
  const notification = document.createElement('div');
  notification.style.cssText = `
    position: fixed;
    top: 20px;
    right: 20px;
    background: ${type === 'success' ? 'var(--green)' : type === 'error' ? 'var(--red)' : 'var(--accent)'};
    color: white;
    padding: 12px 16px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    z-index: 2000;
    animation: slideInRight 0.3s ease;
  `;
  notification.textContent = message;
  document.body.appendChild(notification);

  setTimeout(() => {
    notification.style.animation = 'slideOutRight 0.3s ease';
    setTimeout(() => notification.remove(), 300);
  }, 3000);
}

/**
 * Close modal when clicking outside it
 */
document.addEventListener('DOMContentLoaded', function() {
  const recordPaymentModal = document.getElementById('recordPaymentModal');
  if (recordPaymentModal) {
    recordPaymentModal.addEventListener('click', function(event) {
      if (event.target === this) {
        closeRecordPaymentModal();
      }
    });
  }

  // Add event listeners for search and filters
  const paySearchInput = document.getElementById('paySearchInput');
  if (paySearchInput) {
    paySearchInput.addEventListener('input', function() {
      paymentPageState.searchTerm = this.value;
      renderPayments();
    });
  }

  const payMonthFilter = document.getElementById('payMonthFilter');
  if (payMonthFilter) {
    payMonthFilter.addEventListener('change', function() {
      paymentPageState.currentMonth = this.value;
      renderPayments();
    });
  }

  // Add event listeners for amount and date inputs
  const rpAmount = document.getElementById('rp-amount');
  if (rpAmount) {
    rpAmount.addEventListener('input', updateReconciliation);
  }

  const rpDate = document.getElementById('rp-date');
  if (rpDate) {
    rpDate.addEventListener('change', updateSubmitButtonState);
  }

  const rpInvoice = document.getElementById('rp-invoice');
  if (rpInvoice) {
    rpInvoice.addEventListener('change', onRPInvoiceChange);
  }
});
