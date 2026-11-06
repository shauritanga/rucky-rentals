<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\PaymentChangeRequest;
use App\Models\Property;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\LeaseInstallment;
use App\Models\Tenant;
use App\Models\TenantCredit;
use App\Services\TenantCreditService;
use App\Models\Unit;
use App\Models\UnitClearance;
use App\Models\User;
use App\Notifications\PaymentChangeRequestNotification;
use App\Support\AfterResponse;
use App\Services\InvoiceNumberService;
use App\Services\PaymentReceiptService;
use App\Support\MockRentalData;
use App\Traits\LogsAudit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PaymentController extends Controller
{
    use LogsAudit;

    public function __construct(private InvoiceNumberService $invoiceNumberService) {}
    public function index(Request $request)
    {
        $user = $request->user();

        // ── Deposits & Clearance Adjustments ──────────────────────────────────
        $clearancesQuery = UnitClearance::with(['tenant', 'unit', 'lease', 'property'])
            ->orderByDesc('finalized_at')
            ->orderByDesc('created_at');
        $this->scopeByUserProperty($clearancesQuery, $request, 'property_id');
        $clearances = $clearancesQuery->get();

        $clearedLeaseIds = $clearances->pluck('lease_id')->filter()->all();

        $activeLeasesQuery = Lease::with(['tenant', 'unit', 'property'])
            ->where('deposit', '>', 0)
            ->whereNotIn('id', $clearedLeaseIds)
            // A deposit counts as held only once its proforma is paid.
            ->whereNotIn('id', Invoice::where('kind', 'deposit')->where('status', '!=', 'paid')->whereNotNull('lease_id')->select('lease_id'))
            ->orderByDesc('created_at');
        $this->scopeByUserProperty($activeLeasesQuery, $request, 'property_id');
        $activeLeases = $activeLeasesQuery->get();

        $deposits = collect();

        foreach ($clearances as $c) {
            $dep = (float) $c->deposit_amount;
            $ded = (float) ($c->total_deductions ?? 0);
            $ref = $c->refund_amount !== null ? (float) $c->refund_amount : max(0, $dep - $ded);
            $status = $c->status === 'completed' ? 'refund_due' : ($c->status === 'cancelled' ? 'cancelled' : 'in_clearance');

            $deposits->push([
                'id'             => 'clr-' . $c->id,
                'clearance_id'   => $c->id,
                'lease_id'       => $c->lease_id,
                'lease_number'   => $c->lease?->lease_number,
                'tenant'         => $c->tenant,
                'unit'           => $c->unit,
                'property'       => $c->property,
                'currency'       => 'TZS',
                'deposit_amount' => $dep,
                'clearance_ref'  => $c->clearance_number,
                'clearance_date' => $c->finalized_at?->toDateString() ?: $c->scheduled_date,
                'deductions'     => $ded,
                'final_amount'   => $ref,
                'status'         => $status,
                'notes'          => $c->manager_notes,
            ]);
        }

        foreach ($activeLeases as $l) {
            $deposits->push([
                'id'             => 'lease-' . $l->id,
                'clearance_id'   => null,
                'lease_id'       => $l->id,
                'lease_number'   => $l->lease_number,
                'tenant'         => $l->tenant,
                'unit'           => $l->unit,
                'property'       => $l->property,
                'currency'       => 'TZS',
                'deposit_amount' => $this->depositInTzs($l),
                'clearance_ref'  => null,
                'clearance_date' => null,
                'deductions'     => 0,
                'final_amount'   => $this->depositInTzs($l),
                'status'         => 'held',
                'notes'          => null,
            ]);
        }

        $depositsList = $deposits->values()->all();

        if (MockRentalData::shouldUse() && $user?->role !== 'manager') {
            return Inertia::render('Payments/Index', [
                'payments'       => MockRentalData::payments(),
                'tenants'        => MockRentalData::tenants(),
                'units'          => MockRentalData::units(),
                'invoices'       => MockRentalData::invoices(),
                'deposits'       => empty($depositsList) ? MockRentalData::deposits() : $depositsList,
                'depositRefunds' => empty($depositsList) ? MockRentalData::deposits() : $depositsList,
            ]);
        }

        $paymentsQuery = Payment::with(['tenant', 'unit', 'receipt:id,receipt_number'])->orderByDesc('created_at');
        $invoicesQuery = Invoice::with(['items', 'lease:id,vat_rate,wht_rate'])->orderByDesc('created_at');
        $tenantsQuery  = Tenant::query()->orderBy('name');
        $unitsQuery    = Unit::query()->approved()->orderBy('unit_number');

        $this->scopeByUserProperty($paymentsQuery, $request, 'property_id');
        $this->scopeByUserProperty($invoicesQuery, $request, 'property_id');
        $this->scopeByUserProperty($tenantsQuery, $request, 'property_id');
        $this->scopeByUserProperty($unitsQuery, $request, 'property_id');

        $payments = $paymentsQuery->get();

        // A manager's edit or deletion waiting for a superuser: the drawer shows a note and holds further changes.
        $waiting = PaymentChangeRequest::pending()->whereIn('payment_id', $payments->pluck('id'))->with('requester:id,name')->get()->keyBy('payment_id');
        $payments->each(fn ($p) => $p->setAttribute('pending_change', isset($waiting[$p->id])
            ? ['type' => $waiting[$p->id]->type, 'by' => $waiting[$p->id]->requester?->name]
            : null));

        // Requests still waiting, plus recent rejections with the superuser's reason.
        $requestsQuery = PaymentChangeRequest::with('requester:id,name')
            ->where(fn ($q) => $q->where('status', 'pending')
                ->orWhere(fn ($q) => $q->where('status', 'rejected')->where('decided_at', '>=', now()->subDays(30))))
            ->orderByDesc('created_at');
        $this->scopeByUserProperty($requestsQuery, $request, 'property_id');
        $paymentRequests = $requestsQuery->get()->map(function (PaymentChangeRequest $r) {
            $snap = $r->payload['snapshot'] ?? [];

            return [
                'id'            => $r->id,
                'type'          => $r->type,
                'status'        => $r->status,
                'tenant'        => $snap['tenant'] ?? '—',
                'unit'          => $snap['unit'] ?? '—',
                'invoice'       => $snap['invoice'] ?? '—',
                'amount'        => (float) ($r->type === 'edit' ? ($r->payload['amount'] ?? $snap['amount'] ?? 0) : ($snap['amount'] ?? 0)),
                'method'        => $r->payload['method'] ?? ($snap['method'] ?? null),
                'paid_date'     => $r->payload['paid_date'] ?? ($snap['paid_date'] ?? null),
                'decision_note' => $r->decision_note,
                'requested_by'  => $r->requester?->name,
                'created_at'    => $r->created_at?->toDateTimeString(),
            ];
        })->values();

        // Tenant credit (overpayments not yet used or refunded) from the ledger.
        $creditRows = TenantCredit::with('tenant:id,name,initials,color,text_color')->orderByDesc('id');
        $this->scopeByUserProperty($creditRows, $request, 'property_id');
        $creditRows = $creditRows->get();
        $credits = $creditRows->groupBy('tenant_id')->map(fn ($rows) => [
            'tenant' => $rows->first()->tenant,
            'amount' => round((float) $rows->sum('amount'), 2),
            'currency' => 'TZS',
        ])->filter(fn ($c) => $c['amount'] > 0.005)->values();
        $creditHistory = $creditRows->take(100)->map(fn ($r) => [
            'id' => $r->id, 'tenant' => $r->tenant, 'amount' => (float) $r->amount, 'type' => $r->type, 'currency' => 'TZS',
            'date' => $r->created_at?->toDateString(), 'desc' => $r->note ?: ucfirst($r->type), 'method' => $r->method, 'reference' => $r->reference,
        ])->values();

        $invoices = $invoicesQuery->get();
        $tenants  = $tenantsQuery->get();
        $units    = $unitsQuery->get();

        return Inertia::render('Payments/Index', [
            'payments'       => $payments,
            'tenants'        => $tenants,
            'units'          => $units,
            'invoices'       => $invoices,
            'deposits'       => $depositsList,
            'depositRefunds' => $depositsList,
            'paymentRequests' => $paymentRequests,
            'credits'        => $credits,
            'creditHistory'  => $creditHistory,
        ]);
    }

    public function store(Request $request, PaymentReceiptService $paymentReceiptService)
    {
        $effectivePropertyId = $this->shouldScopeToProperty($request) ? $this->effectivePropertyId($request) : null;

        if ($this->shouldScopeToProperty($request)) {
            abort_if($effectivePropertyId === null, 422, 'No property context available.');
            abort_if(!Property::where('id', $effectivePropertyId)->exists(), 422, 'Assigned property not found.');
        }

        $data = $request->validate([
            'invoice_id' => [
                'nullable',
                Rule::exists('invoices', 'id')->when(
                    $effectivePropertyId,
                    fn($rule) => $rule->where(fn($q) => $q->where('property_id', $effectivePropertyId))
                ),
            ],
            'tenant_id' => [
                'required',
                Rule::exists('tenants', 'id')->when(
                    $effectivePropertyId,
                    fn($rule) => $rule->where(fn($q) => $q->where('property_id', $effectivePropertyId))
                ),
            ],
            'unit_id'   => [
                'required',
                Rule::exists('units', 'id')->when(
                    $effectivePropertyId,
                    fn($rule) => $rule->where(fn($q) => $q->where('property_id', $effectivePropertyId)->where('approval_status', 'approved'))
                ),
            ],
            'month'     => 'required|string',
            'amount'    => 'required|numeric',
            'method'    => 'nullable|string',
            'reference' => 'nullable|string|max:255',
            'status'    => 'required|in:paid,overdue,pending',
            'paid_date' => 'nullable|date',
            'notes'     => 'nullable|string',
            'issue_receipt' => 'nullable|boolean',
            'wht_confirmed' => 'nullable|boolean',
            'wht_reference' => 'nullable|string|max:255',
        ]);

        $unit = Unit::findOrFail($data['unit_id']);
        $tenant = Tenant::findOrFail($data['tenant_id']);
        $invoice = !empty($data['invoice_id']) ? Invoice::with('items')->findOrFail($data['invoice_id']) : null;

        if ($invoice && $invoice->type === 'proforma' && $invoice->approval_status !== 'approved') {
            throw ValidationException::withMessages([
                'invoice_id' => 'Invoice ' . $invoice->invoice_number . ' is awaiting superuser approval. A payment can be recorded once it is approved.',
            ]);
        }

        $propertyId = (int) ($unit->property_id ?: $tenant->property_id ?: 0);

        if ($effectivePropertyId !== null) {
            abort_if(!$propertyId, 422, 'Selected unit/tenant is not linked to any property.');
            abort_if($propertyId !== $effectivePropertyId, 403);
        }

        $data['property_id'] = $propertyId;

        // Payments are always TZS (invoices are TZS; USD lives only on units).
        $data['currency'] = 'TZS';
        $data['exchange_rate'] = 1.0;
        $data['amount_in_base'] = (float) $data['amount'];

        // A security deposit never gets a receipt, whatever the form sent.
        $issueReceipt = $invoice?->kind === 'deposit' ? false : (bool) ($data['issue_receipt'] ?? false);
        $whtConfirmed = (bool) ($data['wht_confirmed'] ?? false);
        $data['issue_receipt'] = $issueReceipt;
        $data['wht_confirmed'] = $whtConfirmed;
        $data['wht_reference'] = $data['wht_reference'] ?? null;

        if ($issueReceipt && empty($data['invoice_id'])) {
            return back()->withErrors([
                'invoice_id' => 'Receipt issuance requires an invoice-linked payment.',
            ]);
        }

        $breakdown = $paymentReceiptService->buildPaymentBreakdown($invoice, (float) $data['amount']);
        $data['breakdown_rent'] = $breakdown['rent'];
        $data['breakdown_service_charge'] = $breakdown['service_charge'];
        $data['breakdown_electricity'] = $breakdown['electricity'];

        if (!$breakdown['has_lease_related']) {
            $whtConfirmed = false;
            $data['wht_confirmed'] = false;
            $data['wht_reference'] = null;
        }

        $isPartialPayment = false;
        if ($invoice) {
            $invoiceTotal = $this->invoiceGrossTotal($invoice);
            $alreadyPaid = (float) Payment::where('invoice_id', $invoice->id)
                ->where('status', 'paid')
                ->sum('amount');
            $outstanding = max(0, round($invoiceTotal - $alreadyPaid, 2));
            $isPartialPayment = $outstanding > 0 && ((float) $data['amount'] + 0.01) < $outstanding;
        }

        $paymentReceiptService->assertReceiptEligibility(
            issueReceipt: $issueReceipt,
            whtConfirmed: $whtConfirmed,
            hasLeaseRelatedCharges: (bool) $breakdown['has_lease_related'],
            isPartialPayment: $isPartialPayment
        );

        // A manager's payment waits for a superuser; a superuser's is recorded at once.
        if ($this->needsApproval($request)) {
            return $this->submitPaymentRequest($request, 'create', null, $data, $propertyId, [
                'tenant'   => $tenant->name,
                'unit'     => $unit->unit_number,
                'invoice'  => $invoice?->invoice_number ?? '—',
                'amount'   => (float) $data['amount'],
                'currency' => 'TZS',
            ]);
        }

        $this->applyCreate($data);

        $propertyName = Property::where('id', $propertyId)->value('name');
        $this->logAudit(
            request: $request,
            action: 'Payment recorded',
            resource: sprintf('%s - %s (%s)', $tenant->name, $data['amount'], $data['currency'] ?? 'TZS'),
            propertyName: $propertyName,
            category: 'payment',
            propertyId: $propertyId ?: null,
        );

        return back()->with('success', 'Payment recorded.');
    }

    public function update(Request $request, Payment $payment)
    {
        $this->authorizePaymentProperty($request, $payment);

        $data = $request->validate([
            'amount'    => 'required|numeric|min:0.01',
            'paid_date' => 'nullable|date',
            'method'    => 'nullable|string|max:100',
            'reference' => 'nullable|string|max:255',
            'notes'     => 'nullable|string',
        ]);

        if (!empty($payment->receipt_id) && abs((float) $data['amount'] - (float) $payment->amount) > 0.001) {
            throw ValidationException::withMessages(['amount' => 'A receipt has been issued for this payment, so its amount cannot change.']);
        }

        if ($this->needsApproval($request)) {
            return $this->submitPaymentRequest($request, 'edit', $payment, $data, $payment->property_id, $this->snapshotOf($payment));
        }

        $this->applyEdit($payment, $data);

        $propertyName = Property::where('id', $payment->property_id)->value('name');
        $this->logAudit(
            request: $request,
            action: 'Payment updated',
            resource: sprintf('Payment #%d', $payment->id),
            propertyName: $propertyName,
            category: 'payment',
            propertyId: $payment->property_id ? (int) $payment->property_id : null,
        );

        return back()->with('success', 'Payment updated.');
    }

    public function destroy(Payment $payment)
    {
        $request = request();
        $this->authorizePaymentProperty($request, $payment);

        if (!empty($payment->receipt_id)) {
            throw ValidationException::withMessages(['payment' => 'A receipt has been issued for this payment, so it cannot be deleted.']);
        }

        if ($this->needsApproval($request)) {
            return $this->submitPaymentRequest($request, 'delete', $payment, null, $payment->property_id, $this->snapshotOf($payment));
        }

        $propertyId   = $payment->property_id;
        $propertyName = Property::where('id', $propertyId)->value('name');
        $resource     = sprintf('Payment #%d', $payment->id);

        $this->applyDelete($payment);

        $this->logAudit(
            request: $request,
            action: 'Payment deleted',
            resource: $resource,
            propertyName: $propertyName,
            category: 'payment',
            propertyId: $propertyId ? (int) $propertyId : null,
        );

        return back()->with('success', 'Payment deleted.');
    }

    /** Put a tenant's credit against an invoice (a manager's click is a request a superuser approves). */
    public function applyCredit(Request $request)
    {
        $data = $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'amount'     => 'nullable|numeric|min:0.01',
        ]);

        $invoice = Invoice::with(['items', 'lease'])->findOrFail($data['invoice_id']);
        if ($this->shouldScopeToProperty($request)) {
            $effectiveId = $this->effectivePropertyId($request);
            abort_if($effectiveId !== null && (int) $invoice->property_id !== $effectiveId, 403);
        }

        $service = app(TenantCreditService::class);
        $tenant = Tenant::findOrFail($service->tenantIdFor($invoice) ?? 0);
        $amount = isset($data['amount']) ? (float) $data['amount'] : null;

        if ($this->needsApproval($request)) {
            $balance = $tenant->creditBalance();
            $outstanding = round($this->invoiceGrossTotalInBase($invoice) - $service->paidOn($invoice), 2);
            $shown = round(min($amount ?? PHP_FLOAT_MAX, $balance, max(0, $outstanding)), 2);
            if ($shown <= 0) {
                throw ValidationException::withMessages(['amount' => $balance <= 0 ? 'This tenant has no credit to apply.' : 'Nothing is owing on this invoice.']);
            }
            foreach (PaymentChangeRequest::pending()->where('type', 'credit')->get() as $open) {
                if ((int) ($open->payload['invoice_id'] ?? 0) === (int) $invoice->id) {
                    throw ValidationException::withMessages(['invoice_id' => 'Applying credit to this invoice is already waiting for approval.']);
                }
            }

            return $this->submitPaymentRequest($request, 'credit', null, ['invoice_id' => $invoice->id, 'tenant_id' => $tenant->id, 'amount' => $amount], $invoice->property_id, [
                'tenant' => $tenant->name, 'unit' => $invoice->unit_ref ?? '—', 'invoice' => $invoice->invoice_number, 'amount' => $shown, 'currency' => 'TZS',
            ]);
        }

        $payment = $service->apply($tenant, $invoice, $amount, $request->user()?->id);

        $this->logAudit(
            request: $request, action: 'Tenant credit applied',
            resource: sprintf('%s - %s on %s', $tenant->name, number_format((float) $payment->amount), $invoice->invoice_number),
            propertyName: Property::where('id', $invoice->property_id)->value('name'), category: 'payment',
            propertyId: $invoice->property_id ? (int) $invoice->property_id : null,
        );

        return back()->with('success', 'Credit of TZS ' . number_format((float) $payment->amount) . ' applied to ' . $invoice->invoice_number . '.');
    }

    /** Give a tenant's credit back as money (a manager's click is a request a superuser approves). */
    public function refundCredit(Request $request)
    {
        $data = $request->validate([
            'tenant_id' => 'required|exists:tenants,id',
            'amount'    => 'required|numeric|min:0.01',
            'method'    => 'nullable|string|max:50',
            'reference' => 'nullable|string|max:255',
            'note'      => 'nullable|string|max:500',
        ]);

        $tenant = Tenant::findOrFail($data['tenant_id']);
        if ($this->shouldScopeToProperty($request)) {
            $effectiveId = $this->effectivePropertyId($request);
            abort_if($effectiveId !== null && (int) $tenant->property_id !== $effectiveId, 403);
        }

        if ((float) $data['amount'] > $tenant->creditBalance() + 0.005) {
            throw ValidationException::withMessages(['amount' => 'Enter an amount up to the credit this tenant has.']);
        }

        if ($this->needsApproval($request)) {
            return $this->submitPaymentRequest($request, 'refund', null, $data, $tenant->property_id, [
                'tenant' => $tenant->name, 'unit' => '—', 'invoice' => 'Credit refund', 'amount' => (float) $data['amount'], 'currency' => 'TZS',
            ]);
        }

        app(TenantCreditService::class)->refund($tenant, (float) $data['amount'], $data['method'] ?? null, $data['reference'] ?? null, $data['note'] ?? null, $request->user()?->id);

        $this->logAudit(
            request: $request, action: 'Tenant credit refunded',
            resource: sprintf('%s - %s', $tenant->name, number_format((float) $data['amount'])),
            propertyName: Property::where('id', $tenant->property_id)->value('name'), category: 'payment',
            propertyId: $tenant->property_id ? (int) $tenant->property_id : null,
        );

        return back()->with('success', 'Refund of TZS ' . number_format((float) $data['amount']) . ' recorded.');
    }

    /** A payment action by anyone but a superuser is a request the superuser approves. */
    private function needsApproval(Request $request): bool
    {
        return $request->user()?->role !== 'superuser' && !$this->isSuperuserActing($request);
    }

    private function snapshotOf(Payment $payment): array
    {
        $payment->loadMissing(['tenant', 'unit', 'invoice']);

        return [
            'tenant'   => $payment->tenant?->name ?? '—',
            'unit'     => $payment->unit?->unit_number ?? '—',
            'invoice'  => $payment->invoice?->invoice_number ?? '—',
            'amount'   => (float) $payment->amount,
            'currency' => 'TZS',
            'method'   => $payment->method,
            'paid_date' => $payment->paid_date ? substr((string) $payment->paid_date, 0, 10) : null,
            'reference' => $payment->reference,
            'notes'     => $payment->notes,
        ];
    }

    /** Record a payment (a superuser's own, or a manager's after approval): receipt, unit status, invoice status. */
    public function applyCreate(array $data): Payment
    {
        unset($data['snapshot']);
        $receipts = app(PaymentReceiptService::class);

        return DB::transaction(function () use ($data, $receipts) {
            $payment = Payment::create($data);
            if ($data['issue_receipt'] ?? false) {
                $invoice = !empty($payment->invoice_id) ? Invoice::with('items')->find($payment->invoice_id) : null;
                $receipt = $receipts->createReceiptForPayment($payment, $invoice);
                $payment->update(['receipt_id' => $receipt->id]);
            }

            if (($data['status'] ?? 'paid') === 'paid') {
                Unit::find($data['unit_id'])?->update(['status' => 'occupied']);
            }

            $this->reconcileInvoiceStatus($payment->invoice_id);

            return $payment;
        });
    }

    /** Change a recorded payment: amount, date, method, reference, notes. The invoice status follows. */
    public function applyEdit(Payment $payment, array $data): void
    {
        DB::transaction(function () use ($payment, $data) {
            $amount = round((float) $data['amount'], 2);
            $invoice = $payment->invoice_id ? Invoice::with('items')->find($payment->invoice_id) : null;
            $breakdown = app(PaymentReceiptService::class)->buildPaymentBreakdown($invoice, $amount);

            $payment->update([
                'amount'                   => $amount,
                'amount_in_base'           => $amount, // payments are always TZS
                'paid_date'                => $data['paid_date'] ?? $payment->paid_date,
                'method'                   => $data['method'] ?? $payment->method,
                'reference'                => $data['reference'] ?? null,
                'notes'                    => $data['notes'] ?? null,
                'breakdown_rent'           => $breakdown['rent'],
                'breakdown_service_charge' => $breakdown['service_charge'],
                'breakdown_electricity'    => $breakdown['electricity'],
            ]);

            $this->reconcileInvoiceStatus($payment->invoice_id);
        });
    }

    /** Remove a payment (a superuser's own, or a manager's after approval); the invoice balance goes back up. */
    public function applyDelete(Payment $payment): void
    {
        $invoiceId = $payment->invoice_id;
        DB::transaction(function () use ($payment, $invoiceId) {
            $payment->delete();
            $this->reconcileInvoiceStatus($invoiceId);
        });
    }

    /** A manager's new payment, edit or deletion is kept as a request; nothing is recorded or changed yet. */
    private function submitPaymentRequest(Request $request, string $type, ?Payment $payment, ?array $payload, $propertyId, array $snapshot): \Illuminate\Http\RedirectResponse
    {
        if ($payment && PaymentChangeRequest::pending()->where('payment_id', $payment->id)->exists()) {
            throw ValidationException::withMessages(['payment' => 'This payment already has a change waiting for approval.']);
        }

        $user = $request->user();
        $changeRequest = PaymentChangeRequest::create([
            'payment_id'   => $payment?->id,
            'property_id'  => $propertyId ?: null,
            'type'         => $type,
            'payload'      => array_merge($payload ?? [], ['snapshot' => $snapshot]),
            'status'       => 'pending',
            'requested_by' => $user->id,
        ]);

        $propertyName = Property::where('id', $propertyId)->value('name') ?? 'Unknown property';
        $this->logAudit(
            request: $request,
            action: match ($type) { 'edit' => 'Payment edit requested', 'delete' => 'Payment deletion requested', 'credit' => 'Credit application requested', 'refund' => 'Credit refund requested', default => 'Payment requested' },
            resource: sprintf('%s - %s', $snapshot['tenant'] ?? '—', number_format((float) ($snapshot['amount'] ?? 0))),
            propertyName: $propertyName,
            category: 'payment',
            propertyId: $propertyId ? (int) $propertyId : null,
        );

        AfterResponse::send(
            User::where('role', 'superuser')->get(),
            new PaymentChangeRequestNotification(
                $changeRequest->id,
                $type,
                (string) ($snapshot['tenant'] ?? '—'),
                (string) ($snapshot['unit'] ?? '—'),
                (string) ($snapshot['invoice'] ?? '—'),
                'TZS ' . number_format((float) ($snapshot['amount'] ?? 0)),
                $propertyName,
                $user->name,
            )
        );

        return back()->with('success', match ($type) {
            'credit' => 'Credit application submitted for superuser approval. The invoice stays as it is until then.',
            'refund' => 'Refund submitted for superuser approval. The credit stays as it is until then.',
            'edit' => 'Change submitted for superuser approval. The payment stays as it is until then.',
            'delete' => 'Deletion submitted for superuser approval. The payment stays until then.',
            default => 'Payment submitted for superuser approval. It is recorded once approved.',
        });
    }

    /**
     * Held deposit in TZS: USD-unit leases use the rate typed at approval (display rate as fallback).
     */
    private function depositInTzs(\App\Models\Lease $lease): float
    {
        $deposit = (float) $lease->deposit;
        if (strtoupper((string) $lease->currency) !== 'USD') {
            return $deposit;
        }

        return round($deposit * (float) ($lease->exchange_rate ?: ($lease->display_rate ?? 0)), 2);
    }

    /** Bring the invoice's status in line with what it has received, and keep the tenant's overpayment credit in step. */
    public function reconcileInvoiceStatus(?int $invoiceId): void
    {
        if (empty($invoiceId)) return;

        $this->reconcileInvoiceStatusCore($invoiceId);

        $invoice = Invoice::with(['items', 'lease:id,vat_rate,tenant_id'])->find($invoiceId);
        if ($invoice) {
            app(\App\Services\TenantCreditService::class)->syncOverpayment($invoice);
        }
    }

    private function reconcileInvoiceStatusCore(?int $invoiceId): void
    {
        if (empty($invoiceId)) return;

        $invoice = Invoice::with(['items', 'lease:id,vat_rate'])->find($invoiceId);
        if (!$invoice) return;

        $invoiceTotal = $this->invoiceGrossTotalInBase($invoice);
        $paidTotal = (float) Payment::where('invoice_id', $invoice->id)
            ->where('status', 'paid')
            ->sum(DB::raw('COALESCE(amount_in_base, amount * COALESCE(exchange_rate, 1))'));
        $fullyPaid = $invoiceTotal > 0 && $paidTotal + 0.01 >= $invoiceTotal;

        // A proforma stays a proforma until the full amount (WHT included) is paid; it is then promoted to a tax invoice.
        // A security-deposit proforma never becomes a tax invoice: it is just marked paid (no receipt is issued).
        if ($invoice->kind === 'deposit') {
            $invoice->status = $fullyPaid ? 'paid' : ($paidTotal > 0 ? 'partially_paid' : 'proforma');
            $invoice->save();
            return;
        }

        if ($invoice->type === 'proforma' || $invoice->status === 'proforma') {
            if (!$fullyPaid) {
                return;
            }

            DB::transaction(function () use ($invoice) {
                // Number is kept: the tax invoice is the same bill as the proforma.
                $invoice->updateQuietly(['type' => 'invoice']);
            });
            $invoice->refresh();
        }

        // The proforma→invoice type promotion can also happen outside this flow
        // (e.g. InvoiceController::update changing status away from 'proforma'),
        // which skips the installment link above. Catch it here so a lease-linked
        // tax invoice never goes unlinked to its installment.
        if (
            $invoice->type === 'invoice'
            && !empty($invoice->lease_id)
            && !LeaseInstallment::where('invoice_id', $invoice->id)->exists()
        ) {
            $this->attachInvoiceToInstallment($invoice);
        }

        $paidTotalSource =(float) Payment::where('invoice_id', $invoice->id)
            ->where('status', 'paid')
            ->sum('amount');
        $invoiceTotalSource = $this->invoiceGrossTotal($invoice);

        if ($fullyPaid) {
            $invoice->status = 'paid';
        } elseif ($paidTotal > 0) {
            $invoice->status = 'partially_paid';
        } else {
            $isOverdue = !empty($invoice->due_date)
                && Carbon::today()->gt(Carbon::parse($invoice->due_date)->startOfDay());
            $invoice->status = $isOverdue ? 'overdue' : 'unpaid';
        }

        $invoice->save();
        $this->reconcileInstallmentStatus($invoice, $paidTotalSource, $invoiceTotalSource);
    }

    private function reconcileInstallmentStatus(Invoice $invoice, float $paidTotal, float $invoiceTotal): void
    {
        if (empty($invoice->lease_id) || $invoice->type === 'proforma') {
            return;
        }

        $installment = LeaseInstallment::where('invoice_id', $invoice->id)->first();
        if (!$installment) {
            return;
        }

        if ($invoiceTotal > 0 && $paidTotal + 0.01 >= $invoiceTotal) {
            $status = 'paid';
        } elseif ($paidTotal > 0) {
            $status = 'partially_paid';
        } else {
            $status = Carbon::today()->gt(Carbon::parse($installment->due_date)->startOfDay())
                ? 'overdue'
                : 'unpaid';
        }

        $installment->update([
            'status' => $status,
            'paid_amount' => max(0, round($paidTotal, 2)),
        ]);
    }

    private function invoiceGrossTotal(Invoice $invoice): float
    {
        $invoice->loadMissing(['items', 'lease:id,vat_rate']);

        $items = $invoice->items ?? collect();
        $itemsTotal = (float) $items->sum('total');
        $vatRate = (float) ($invoice->lease?->vat_rate ?? 0);

        if ($vatRate <= 0) {
            return round($itemsTotal, 2);
        }

        $leaseVatBase = (float) $items
            ->filter(fn($item) => $this->isLeaseVatEligibleItem($item))
            ->sum('total');

        return round($itemsTotal + ($leaseVatBase * ($vatRate / 100)), 2);
    }

    public function invoiceGrossTotalInBase(Invoice $invoice): float
    {
        if (!empty($invoice->total_in_base)) {
            return (float) $invoice->total_in_base;
        }

        $gross = $this->invoiceGrossTotal($invoice);
        $exchangeRate = (float) ($invoice->exchange_rate ?: 1);

        return round($gross * $exchangeRate, 2);
    }

    private function isLeaseVatEligibleItem($item): bool
    {
        $itemType = strtolower((string) ($item->item_type ?? 'other'));
        if (in_array($itemType, ['electricity_charge', 'electricity', 'electricity_vat', 'security_deposit'], true)) {
            return false;
        }

        $description = strtolower((string) ($item->description ?? ''));
        if (str_contains($description, 'electricity') || str_contains($description, 'generator') || str_contains($description, 'submeter')) {
            return false;
        }

        return $itemType === 'rent'
            || $itemType === 'service_charge'
            || str_contains($description, 'rent')
            || str_contains($description, 'service charge');
    }

    private function attachInvoiceToInstallment(Invoice $invoice): void
    {
        if (empty($invoice->lease_id) || $invoice->type === 'proforma') {
            return;
        }

        $base = LeaseInstallment::where('lease_id', $invoice->lease_id)->whereNull('invoice_id');

        $installment = !empty($invoice->due_date)
            ? (clone $base)->whereDate('due_date', $invoice->due_date)->orderBy('sequence')->first()
            : null;

        $installment ??= (clone $base)->orderBy('sequence')->first();
        $installment?->update(['invoice_id' => $invoice->id]);
    }

    private function scopeByUserProperty($query, Request $request, string $column): void
    {
        if (!$this->shouldScopeToProperty($request)) return;
        $propertyId = $this->effectivePropertyId($request);
        if ($propertyId === null) { $query->whereRaw('1 = 0'); return; }
        $query->where($column, $propertyId);
    }

    private function authorizePaymentProperty(Request $request, Payment $payment): void
    {
        if (!$this->shouldScopeToProperty($request)) return;
        $effectiveId = $this->effectivePropertyId($request);
        if ($effectiveId === null) return;
        $paymentPropertyId = $payment->property_id ?: $payment->unit?->property_id;
        abort_if((int) $paymentPropertyId !== $effectiveId, 403);
    }
}
