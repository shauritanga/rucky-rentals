<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Property;
use App\Models\Invoice;
use App\Models\LeaseInstallment;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\ExchangeRate;
use App\Services\AccountingService;
use App\Services\PaymentReceiptService;
use App\Support\MockRentalData;
use App\Traits\LogsAudit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PaymentController extends Controller
{
    use LogsAudit;
    public function index(Request $request)
    {
        $user = $request->user();

        if (MockRentalData::shouldUse() && $user?->role !== 'manager') {
            return Inertia::render('Payments/Index', [
                'payments' => MockRentalData::payments(),
                'tenants' => MockRentalData::tenants(),
                'units' => MockRentalData::units(),
                'invoices' => MockRentalData::invoices(),
            ]);
        }

        $paymentsQuery = Payment::with(['tenant', 'unit'])->orderByDesc('created_at');
        $invoicesQuery = Invoice::with('items')->orderByDesc('created_at');
        $tenantsQuery  = Tenant::query()->orderBy('name');
        $unitsQuery    = Unit::query()->orderBy('unit_number');

        $this->scopeByUserProperty($paymentsQuery, $request, 'property_id');
        $this->scopeByUserProperty($invoicesQuery, $request, 'property_id');
        $this->scopeByUserProperty($tenantsQuery, $request, 'property_id');
        $this->scopeByUserProperty($unitsQuery, $request, 'property_id');

        $payments = $paymentsQuery->get();
        $invoices = $invoicesQuery->get();
        $tenants  = $tenantsQuery->get();
        $units    = $unitsQuery->get();
        return Inertia::render('Payments/Index', compact('payments', 'tenants', 'units', 'invoices'));
    }

    public function store(Request $request, AccountingService $accountingService, PaymentReceiptService $paymentReceiptService)
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
                    fn($rule) => $rule->where(fn($q) => $q->where('property_id', $effectivePropertyId))
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

        $propertyId = (int) ($unit->property_id ?: $tenant->property_id ?: 0);

        if ($effectivePropertyId !== null) {
            abort_if(!$propertyId, 422, 'Selected unit/tenant is not linked to any property.');
            abort_if($propertyId !== $effectivePropertyId, 403);
        }

        $data['property_id'] = $propertyId;

        // Resolve currency/rate before create so observer posts with correct values.
        $data['currency'] = 'TZS';
        if (!empty($data['invoice_id'])) {
            if ($invoice) {
                $data['currency'] = $invoice->currency ?? 'TZS';
            }
        }

        if (($data['currency'] ?? 'TZS') !== 'TZS') {
            $rate = ExchangeRate::getRate(
                propertyId: null,
                fromCurrency: $data['currency'],
                toCurrency: 'TZS',
                date: $data['paid_date'] ?? now()
            );

            if ($rate === null) {
                return back()->withErrors([
                    'amount' => 'Exchange rate not found for ' . $data['currency'] . ' to TZS.',
                ]);
            }

            $data['exchange_rate'] = $rate;
            $data['amount_in_base'] = (float) $data['amount'] * (float) $rate;
        }

        $issueReceipt = (bool) ($data['issue_receipt'] ?? false);
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

        $isPartialPayment = false;
        if ($invoice) {
            $invoiceTotal = (float) $invoice->items->sum('total');
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

        DB::transaction(function () use ($data, $accountingService, $paymentReceiptService) {
            $payment = Payment::create($data);
            if ($data['issue_receipt'] ?? false) {
                $invoice = !empty($payment->invoice_id) ? Invoice::with('items')->find($payment->invoice_id) : null;
                $receipt = $paymentReceiptService->createReceiptForPayment($payment, $invoice);
                $payment->update(['receipt_id' => $receipt->id]);
            }

            // Service layer handles posting logic (observer will also watch for status changes)
            if ($data['status'] === 'paid') {
                Unit::find($data['unit_id'])?->update(['status' => 'occupied']);
            }

            $this->reconcileInvoiceStatus($payment->invoice_id);
        });

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
        $payment->update($request->only(['status', 'method', 'paid_date']));

        // Observer handles status transitions (post/void as appropriate)

        $this->reconcileInvoiceStatus($payment->invoice_id);

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
        $this->authorizePaymentProperty(request(), $payment);

        $propertyId   = $payment->property_id;
        $propertyName = Property::where('id', $propertyId)->value('name');
        $resource     = sprintf('Payment #%d', $payment->id);

        // Observer will handle voiding before deletion
        $invoiceId = $payment->invoice_id;
        $payment->delete();

        $this->reconcileInvoiceStatus($invoiceId);

        $this->logAudit(
            request: request(),
            action: 'Payment deleted',
            resource: $resource,
            propertyName: $propertyName,
            category: 'payment',
            propertyId: $propertyId ? (int) $propertyId : null,
        );

        return back()->with('success', 'Payment deleted.');
    }

    private function reconcileInvoiceStatus(?int $invoiceId): void
    {
        if (empty($invoiceId)) return;

        $invoice = Invoice::with('items')->find($invoiceId);
        if (!$invoice || $invoice->type === 'proforma') return;

        // Use base-currency amounts so USD invoices reconcile correctly.
        // total_in_base is set by AccountingService when posting; fall back to
        // items sum × exchange_rate for invoices not yet converted.
        $invoiceTotal = (float) ($invoice->total_in_base
            ?: ($invoice->exchange_rate
                ? $invoice->items->sum('total') * (float) $invoice->exchange_rate
                : $invoice->items->sum('total')));
        $paidTotal = (float) Payment::where('invoice_id', $invoice->id)
            ->where('status', 'paid')
            ->sum(DB::raw('COALESCE(amount_in_base, amount * COALESCE(exchange_rate, 1))'));

        if ($invoiceTotal > 0 && $paidTotal + 0.01 >= $invoiceTotal) {
            $invoice->status = 'paid';
        } elseif ($paidTotal > 0) {
            $invoice->status = 'partially_paid';
        } else {
            $isOverdue = !empty($invoice->due_date)
                && Carbon::today()->gt(Carbon::parse($invoice->due_date)->startOfDay());
            $invoice->status = $isOverdue ? 'overdue' : 'unpaid';
        }

        $invoice->save();
        $this->reconcileInstallmentStatus($invoice, $paidTotal, $invoiceTotal);
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
