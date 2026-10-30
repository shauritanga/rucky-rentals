<?php

namespace App\Http\Controllers;

use App\Mail\ProformaInvoiceMail;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Lease;
use App\Models\LeaseInstallment;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use Illuminate\Validation\ValidationException;
use App\Services\InvoiceNumberService;
use App\Services\PaymentReceiptService;
use App\Support\MockRentalData;
use App\Traits\LogsAudit;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class InvoiceController extends Controller
{
    use LogsAudit;

    public function __construct(private InvoiceNumberService $invoiceNumberService) {}
    public function index(Request $request)
    {
        $user = $request->user();

        if (MockRentalData::shouldUse() && $user?->role !== 'manager') {
            $activeLeases = array_values(array_filter(
                MockRentalData::leases(),
                fn($lease) => ($lease['status'] ?? null) === 'active'
            ));

            return Inertia::render('Invoices/Index', [
                'invoices' => MockRentalData::invoices(),
                'leases' => $activeLeases,
                'tenants' => MockRentalData::tenants(),
            ]);
        }

        $invoicesQuery = Invoice::with(['items', 'payments:id,invoice_id,status,receipt_id,wht_confirmed'])->orderByDesc('created_at');
        $leasesQuery = Lease::with(['tenant', 'unit', 'installments'])
            ->where('status', 'active');
        $tenantsQuery = Tenant::query()->orderBy('name');

        $this->scopeByUserProperty($invoicesQuery, $request, 'property_id');
        $this->scopeByUserProperty($leasesQuery, $request, 'property_id');
        $this->scopeByUserProperty($tenantsQuery, $request, 'property_id');

        $receiptService = app(PaymentReceiptService::class);
        $invoices = $invoicesQuery->get()->each(function (Invoice $invoice) use ($receiptService) {
            $paid = $invoice->payments->where('status', 'paid');
            $hasLeaseRelated = $receiptService
                ->buildPaymentBreakdown($invoice, (float) $invoice->items->sum('total'))['has_lease_related'];

            // Receipts are only issued against full payment; WHT is tracked once money has been received.
            $invoice->setAttribute('receipt_status', match (true) {
                $paid->contains(fn ($p) => !empty($p->receipt_id)) => 'issued',
                $invoice->status === 'paid' => 'not_issued',
                default => 'na',
            });
            $invoice->setAttribute('wht_status', match (true) {
                $invoice->kind === 'deposit' || !$hasLeaseRelated || $paid->isEmpty() => 'na',
                $paid->contains(fn ($p) => $p->wht_confirmed) => 'paid',
                default => 'pending',
            });
            $invoice->unsetRelation('payments');
        });
        $leases   = $leasesQuery->get();
        $this->attachReferenceRates($leases);
        $tenants  = $tenantsQuery->get();
        return Inertia::render('Invoices/Index', compact('invoices', 'leases', 'tenants'));
    }

    /**
     * Overdue + due-within-7-days invoices for the bottom-right reminder popup.
     * Eligibility is computed from due_date directly rather than trusting `status`,
     * since status is only recomputed reactively when a payment is recorded — an
     * untouched invoice past its due date will not automatically flip to 'overdue'.
     */
    public function dueReminders(Request $request)
    {
        $user = $request->user();
        abort_unless(in_array($user?->role, ['superuser', 'manager', 'accountant'], true), 403);

        $today = Carbon::today();
        $in7Days = $today->copy()->addDays(7);

        $query = Invoice::with('items')
            ->where('kind', '!=', 'deposit')
            ->whereNotIn('status', ['draft', 'proforma', 'paid'])
            ->where('due_date', '<=', $in7Days)
            ->orderBy('due_date');

        $this->scopeByUserProperty($query, $request, 'property_id');

        $invoices = $query->get();

        $paidByInvoice = Payment::whereIn('invoice_id', $invoices->pluck('id'))
            ->where('status', 'paid')
            ->selectRaw('invoice_id, SUM(amount) as paid_sum')
            ->groupBy('invoice_id')
            ->pluck('paid_sum', 'invoice_id');

        $due = $invoices->map(function ($inv) use ($today, $paidByInvoice) {
            $total = (float) $inv->items->sum('total');
            $amountDue = max(0, round($total - (float) ($paidByInvoice[$inv->id] ?? 0), 2));
            $dueDate = Carbon::parse($inv->due_date)->startOfDay();
            $daysDiff = $today->diffInDays($dueDate, false); // negative = overdue

            return [
                'id'             => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'tenant_name'    => $inv->tenant_name,
                'unit_ref'       => $inv->unit_ref,
                'amount_due'     => $amountDue,
                'currency'       => 'TZS',
                'due_date'       => $inv->due_date,
                'is_overdue'     => $daysDiff < 0,
                'days'           => abs($daysDiff),
            ];
        })
            ->filter(fn ($row) => $row['amount_due'] > 0)
            ->sortBy('due_date')
            ->values();

        return response()->json(['due_invoices' => $due]);
    }

    public function store(Request $request)
    {
        $effectivePropertyId = $this->shouldScopeToProperty($request) ? $this->effectivePropertyId($request) : null;

        if ($this->shouldScopeToProperty($request)) {
            abort_if($effectivePropertyId === null, 422, 'No property context available.');
            abort_if(!Property::where('id', $effectivePropertyId)->exists(), 422, 'Assigned property not found.');
        }

        $data = $request->validate([
            'type'         => 'required|in:invoice,proforma',
            'status'       => 'nullable|in:draft',
            'lease_id'     => [
                'nullable',
                Rule::exists('leases', 'id')->when(
                    true,
                    fn($rule) => $rule->where(function ($q) use ($effectivePropertyId) {
                        $q->where('status', 'active');

                        if ($effectivePropertyId) {
                            $q->where('property_id', $effectivePropertyId);
                        }
                    })
                ),
            ],
            'tenant_name'  => 'required|string',
            'tenant_email' => 'nullable|email',
            'unit_ref'     => 'required|string',
            'issued_date'  => 'required|date',
            'due_date'     => 'nullable|date',
            'period'       => 'nullable|string',
            'notes'        => 'nullable|string',
            'usd_rate'      => 'nullable|numeric|min:0.0001|max:999999.9999',
            'items'        => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.quantity'    => 'required|integer|min:1',
            'items.*.unit_price'  => 'required|numeric',
        ]);

        // Business rule: lease invoices are always proforma.
        // GL posts on payment when they convert to Tax Invoices, not at creation.
        if (!empty($data['lease_id'])) {
            $data['type'] = 'proforma';
        }

        $status = ($data['status'] ?? null) === 'draft'
            ? 'draft'
            : ($data['type'] === 'proforma' ? 'proforma' : 'unpaid');

        $approvalStatus = $data['type'] === 'proforma'
            ? (($status === 'draft') ? 'draft' : 'pending_approval')
            : 'approved';

        $propertyId = null;
        if (!empty($data['lease_id'])) {
            $lease = Lease::findOrFail($data['lease_id']);
            $propertyId = $lease->property_id;

            // A lease invoice is due on the first day of the installment it bills.
            $nextInstallment = LeaseInstallment::where('lease_id', $lease->id)
                ->whereNull('invoice_id')
                ->orderBy('sequence')
                ->first();
            if ($nextInstallment) {
                $data['due_date'] = Carbon::parse($nextInstallment->period_start ?? $nextInstallment->due_date)->toDateString();
            }
        }

        if ($effectivePropertyId !== null) {
            if ($propertyId === null) {
                $propertyId = $effectivePropertyId;
            }
            abort_if((int) $propertyId !== $effectivePropertyId, 403);
        }

        $createdInvoiceId = null;

        DB::transaction(function () use ($request, $data, $propertyId, $status, $approvalStatus, &$createdInvoiceId) {
            $leaseTenant = !empty($data['lease_id']) ? Lease::with('tenant')->find($data['lease_id'])?->tenant : null;

            $invoice = Invoice::create([
                ...$data,
                'property_id'    => $propertyId,
                'requested_by_user_id' => $request->user()?->id,
                'invoice_number' => $this->invoiceNumberService->generateForTenant($leaseTenant, $data['tenant_name'] ?? null, $data['issued_date'] ?? null),
                'status'         => $status,
                'approval_status' => $approvalStatus,
                'approval_requested_at' => $approvalStatus === 'pending_approval' ? now() : null,
                'approval_decided_at' => $approvalStatus === 'approved' ? now() : null,
                'approval_decided_by' => $approvalStatus === 'approved' ? $request->user()?->id : null,
            ]);
            $createdInvoiceId = $invoice->id;

            // Invoices are always TZS. A USD-unit lease is converted by the client using the
            // rate typed on the proforma; that rate is kept in usd_rate for audit.
            $lease = $invoice->lease_id ? Lease::find($invoice->lease_id) : null;
            $usdRate = $this->resolveUsdRate($lease, $data['usd_rate'] ?? null);
            $invoice->update([
                'currency'      => 'TZS',
                'exchange_rate' => 1.0,
                'usd_rate'      => $usdRate,
            ]);

            $invoiceTotal = 0.0;
            foreach ($data['items'] as $item) {
                $lineTotal = (float) $item['quantity'] * (float) $item['unit_price'];
                $invoiceTotal += $lineTotal;

                // Derive item_type: use explicit value from request if provided,
                // otherwise infer from description for backward compatibility.
                $itemType = $item['item_type']
                    ?? (stripos($item['description'] ?? '', 'service charge') !== false ? 'service_charge' : 'rent');

                InvoiceItem::create([
                    'invoice_id'      => $invoice->id,
                    'description'     => $item['description'],
                    'item_type'       => $itemType,
                    'sub_description' => $item['sub_description'] ?? null,
                    'quantity'        => $item['quantity'],
                    'unit_price'      => $item['unit_price'],
                    'total'           => $lineTotal,
                ]);
            }

            // Pre-calculate base currency amount
            $vatRate = (float) ($lease?->vat_rate ?? 0);
            $elecItemsVat = (float) $invoice->items()->where('item_type', 'electricity_vat')->sum('total');
            $netItemsTotal = (float) $invoice->items()->get()->reject(fn($i) => in_array($i->item_type, ['electricity_charge', 'electricity_vat']))->sum('total');
            $leaseVat = ($elecItemsVat > 0 || $vatRate <= 0) ? 0.0 : round($netItemsTotal * ($vatRate / 100), 2);
            $grandTotal = $invoiceTotal + $leaseVat;
            $invoice->updateQuietly([
                'total_in_base' => round($grandTotal, 2),
            ]);

            $this->attachInvoiceToInstallment($invoice);

        });

        $createdInvoice = Invoice::with('items')->find($createdInvoiceId);

        $propertyName = Property::where('id', $propertyId)->value('name');
        $this->logAudit(
            request: $request,
            action: 'Invoice created',
            resource: $createdInvoice?->invoice_number ?? ('INV #' . $createdInvoiceId),
            propertyName: $propertyName,
            category: 'invoice',
            propertyId: $propertyId ? (int) $propertyId : null,
        );

        return back()
            ->with('success', $createdInvoice?->type === 'proforma'
                ? 'Proforma invoice submitted for superuser approval.'
                : 'Invoice created.')
            ->with('created_invoice_id', $createdInvoiceId);
    }

    public function update(Request $request, Invoice $invoice)
    {
        if ($this->shouldScopeToProperty($request)) {
            $effectiveId = $this->effectivePropertyId($request);
            abort_if($effectiveId !== null && (int) $invoice->property_id !== $effectiveId, 403);
        }

        if ($request->has('items') || $request->has('tenant_name') || $request->has('issued_date') || $request->has('notes')) {
            return $this->updateEditableProforma($request, $invoice);
        }

        $data = $request->validate([
            'status' => 'required|in:draft,proforma,unpaid,partially_paid,paid,overdue',
        ]);

        // A proforma becomes a tax invoice only automatically, once fully paid
        // (PaymentController::reconcileInvoiceStatus). No manual conversion.
        if ($invoice->type === 'proforma' && !in_array($data['status'] ?? '', ['draft', 'proforma'], true)) {
            abort(422, 'A proforma converts to a tax invoice automatically once it is fully paid.');
        }

        $invoice->update($data);

        // Observer handles status transitions (post/void as appropriate)

        // Ensure corresponding Payment record exists when marked as paid
        if ($data['status'] === 'paid' && ($original['status'] ?? '') !== 'paid') {
            $this->ensurePaymentRecordedForInvoice($invoice);
        } elseif ($data['status'] !== 'paid' && ($original['status'] ?? '') === 'paid') {
            Payment::where('invoice_id', $invoice->id)
                ->where('notes', 'like', '%auto-recorded%')
                ->get()
                ->each(fn($p) => $p->delete());
        }

        // Manual status changes here (e.g. the "Mark as Paid" button) don't go through
        // PaymentController's payment-driven reconciliation, so the linked lease
        // installment's status/paid_amount would otherwise stay stale (e.g. still
        // "overdue" after the invoice is marked paid). Mirror the chosen status onto it.
        //
        // A proforma invoice never gets linked at creation (attachInvoiceToInstallment()
        // bails out for type='proforma'), so when this same request promotes it to
        // type='invoice' above, catch it up here too — mirrors the same catch-up in
        // PaymentController::reconcileInvoiceStatus().
        if (
            $invoice->type === 'invoice'
            && !empty($invoice->lease_id)
            && !LeaseInstallment::where('invoice_id', $invoice->id)->exists()
        ) {
            $this->attachInvoiceToInstallment($invoice);
        }

        $installment = LeaseInstallment::where('invoice_id', $invoice->id)->first();
        if ($installment) {
            $installment->update([
                'status'      => in_array($data['status'], ['paid', 'partially_paid', 'overdue', 'unpaid'], true)
                    ? $data['status']
                    : 'unpaid',
                'paid_amount' => match ($data['status']) {
                    'paid'   => (float) $installment->amount,
                    'unpaid' => 0,
                    default  => $installment->paid_amount,
                },
            ]);
        }

        $propertyName = Property::where('id', $invoice->property_id)->value('name');
        $this->logAudit(
            request: $request,
            action: 'Invoice updated',
            resource: $invoice->invoice_number,
            propertyName: $propertyName,
            category: 'invoice',
            propertyId: $invoice->property_id ? (int) $invoice->property_id : null,
        );

        return back()->with('success', 'Invoice updated.');
    }

    public function resubmit(Request $request, Invoice $invoice): \Illuminate\Http\RedirectResponse
    {
        if ($this->shouldScopeToProperty($request)) {
            $effectiveId = $this->effectivePropertyId($request);
            abort_if($effectiveId !== null && (int) $invoice->property_id !== $effectiveId, 403);
        }

        abort_if($invoice->type !== 'proforma', 422, 'Only proforma invoices can be resubmitted.');
        abort_if($invoice->sent_to_tenant_at !== null, 422, 'Sent proforma invoices cannot be resubmitted.');
        abort_if(!in_array($invoice->approval_status, ['draft', 'rejected', 'approved', 'pending_approval'], true), 422, 'Invoice cannot be resubmitted.');

        $invoice->update([
            'approval_status' => 'pending_approval',
            'approval_requested_at' => now(),
            'approval_decided_at' => null,
            'approval_decided_by' => null,
            'approval_note' => null,
        ]);

        $propertyName = Property::where('id', $invoice->property_id)->value('name');
        $this->logAudit(
            request: $request,
            action: 'Invoice resubmitted',
            resource: $invoice->invoice_number,
            propertyName: $propertyName,
            category: 'invoice',
            propertyId: $invoice->property_id ? (int) $invoice->property_id : null,
        );

        return back()->with('success', 'Proforma invoice resubmitted for approval.');
    }

    public function destroy(Invoice $invoice)
    {
        $req = request();
        if ($this->shouldScopeToProperty($req)) {
            $effectiveId = $this->effectivePropertyId($req);
            abort_if($effectiveId !== null && (int) $invoice->property_id !== $effectiveId, 403);
        }

        $propertyId   = $invoice->property_id;
        $propertyName = Property::where('id', $propertyId)->value('name');
        $resource     = $invoice->invoice_number;

        // Observer will handle voiding before deletion
        $invoice->delete();

        $this->logAudit(
            request: request(),
            action: 'Invoice deleted',
            resource: $resource,
            propertyName: $propertyName,
            category: 'invoice',
            propertyId: $propertyId ? (int) $propertyId : null,
        );

        return back()->with('success', 'Invoice deleted.');
    }

    private function scopeByUserProperty($query, Request $request, string $column): void
    {
        if (!$this->shouldScopeToProperty($request)) return;
        $propertyId = $this->effectivePropertyId($request);
        if ($propertyId === null) { $query->whereRaw('1 = 0'); return; }
        $query->where($column, $propertyId);
    }

    private function attachInvoiceToInstallment(Invoice $invoice): void
    {
        if (empty($invoice->lease_id) || $invoice->type === 'proforma') {
            return;
        }

        $base = LeaseInstallment::where('lease_id', $invoice->lease_id)
            ->whereNull('invoice_id');

        $installment = null;

        if (!empty($invoice->due_date)) {
            $installment = (clone $base)
                ->whereDate('due_date', $invoice->due_date)
                ->orderBy('sequence')
                ->first();
        }

        if (!$installment) {
            $installment = (clone $base)->orderBy('sequence')->first();
        }

        if ($installment) {
            $installment->update(['invoice_id' => $invoice->id]);
        }
    }

    /**
     * Generate a PDF for any invoice and return it as a download (always fresh — not cached).
     */
    public function downloadPdf(Request $request, Invoice $invoice): \Illuminate\Http\Response
    {
        if ($this->shouldScopeToProperty($request)) {
            $effectiveId = $this->effectivePropertyId($request);
            abort_if($effectiveId !== null && (int) $invoice->property_id !== $effectiveId, 403);
        }

        $invoice->load('items');

        $items        = $invoice->items;
        $invoiceLabel = $invoice->kind === 'deposit'
            ? 'PROFORMA – SECURITY DEPOSIT'
            : ($invoice->type === 'proforma' ? 'PROFORMA INVOICE' : 'TAX INVOICE');

        // ── Company/property/tenant/unit details (mirrors ProformaInvoiceMail::generatePdf) ──
        $viewData   = $invoice->buildPdfViewData($items);
        $tenantId   = $viewData['tenantId'];
        $property   = $viewData['property'];
        unset($viewData['tenantId']);

        $pdfContent = Pdf::loadView('pdf.proforma-invoice', array_merge($viewData, compact(
            'invoice', 'items', 'invoiceLabel',
        )))->setPaper('a4', 'portrait')->output();

        $filename    = str_replace('/', '-', $invoice->invoice_number) . '.pdf';
        $storagePath = 'documents/' . $filename;

        Storage::disk('public')->put($storagePath, $pdfContent);

        Document::updateOrCreate(
            ['invoice_id' => $invoice->id],
            [
                'name'          => $invoice->invoice_number,
                'file_path'     => $storagePath,
                'file_type'     => 'pdf',
                'file_size'     => round(strlen($pdfContent) / 1024, 1) . ' KB',
                'tag'           => 'other',
                'document_type' => 'invoice',
                'unit_ref'      => $invoice->unit_ref,
                'tenant_id'     => $tenantId,
                'invoice_id'    => $invoice->id,
                'description'   => $invoiceLabel . ' — ' . $invoice->tenant_name,
                'uploaded_by'   => $request->user()?->name ?? 'System',
            ]
        );

        return response($pdfContent, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Re-send a proforma invoice email on demand (from the Email button in the drawer).
     */
    /**
     * Record that the tenant has paid the withholding tax on this invoice.
     */
    public function confirmWht(Request $request, Invoice $invoice): \Illuminate\Http\RedirectResponse
    {
        $this->authorizeInvoiceProperty($request, $invoice);

        $data = $request->validate(['wht_reference' => 'nullable|string|max:255']);

        $paid = Payment::where('invoice_id', $invoice->id)->where('status', 'paid')->get();
        if ($paid->isEmpty()) {
            return back()->with('error', 'Record a payment before confirming WHT.');
        }

        Payment::whereIn('id', $paid->pluck('id'))->update([
            'wht_confirmed' => true,
            'wht_reference' => $data['wht_reference'] ?? null,
        ]);

        $this->logAudit(
            request: $request,
            action: 'WHT confirmed paid',
            resource: $invoice->invoice_number,
            propertyName: Property::where('id', $invoice->property_id)->value('name'),
            category: 'payment',
            propertyId: $invoice->property_id ? (int) $invoice->property_id : null,
        );

        return back()->with('success', 'WHT marked as paid.');
    }

    /**
     * Issue the receipt for a fully paid invoice (one receipt for the full amount).
     */
    public function issueReceipt(Request $request, Invoice $invoice, PaymentReceiptService $receiptService): \Illuminate\Http\RedirectResponse
    {
        $this->authorizeInvoiceProperty($request, $invoice);

        $invoice->load('items');
        $paid = Payment::where('invoice_id', $invoice->id)->where('status', 'paid')->orderBy('id')->get();

        if ($invoice->status !== 'paid' || $paid->isEmpty()) {
            return back()->with('error', 'A receipt can only be issued once the invoice is fully paid.');
        }
        if ($paid->contains(fn ($p) => !empty($p->receipt_id))) {
            return back()->with('error', 'A receipt has already been issued for this invoice.');
        }

        $hasLeaseRelated = $receiptService
            ->buildPaymentBreakdown($invoice, (float) $invoice->items->sum('total'))['has_lease_related'];
        $receiptService->assertReceiptEligibility(
            issueReceipt: true,
            whtConfirmed: $paid->every(fn ($p) => $p->wht_confirmed),
            hasLeaseRelatedCharges: (bool) $hasLeaseRelated,
            isPartialPayment: false,
        );

        DB::transaction(function () use ($invoice, $paid, $receiptService) {
            $last = $paid->last();
            $receipt = $receiptService->createReceiptForPayment($last, $invoice);
            $receipt->update(['amount' => round((float) $paid->sum('amount'), 2)]);
            $last->update(['receipt_id' => $receipt->id, 'issue_receipt' => true]);
        });

        $this->logAudit(
            request: $request,
            action: 'Receipt issued',
            resource: $invoice->invoice_number,
            propertyName: Property::where('id', $invoice->property_id)->value('name'),
            category: 'payment',
            propertyId: $invoice->property_id ? (int) $invoice->property_id : null,
        );

        return back()->with('success', 'Receipt issued.');
    }

    private function authorizeInvoiceProperty(Request $request, Invoice $invoice): void
    {
        if ($this->shouldScopeToProperty($request)) {
            $effectiveId = $this->effectivePropertyId($request);
            abort_if($effectiveId !== null && (int) $invoice->property_id !== $effectiveId, 403);
        }
    }

    public function send(Request $request, Invoice $invoice): \Illuminate\Http\RedirectResponse
    {
        if ($this->shouldScopeToProperty($request)) {
            $effectiveId = $this->effectivePropertyId($request);
            abort_if($effectiveId !== null && (int) $invoice->property_id !== $effectiveId, 403);
        }

        if ($invoice->type !== 'proforma') {
            return back()->with('error', 'Only proforma invoices can be re-sent this way.');
        }

        if ($invoice->approval_status !== 'approved') {
            return back()->with('error', 'This proforma must be approved by a superuser before it can be sent.');
        }

        if ($invoice->sent_to_tenant_at !== null) {
            return back()->with('error', 'This proforma has already been sent to the tenant.');
        }

        if (empty($invoice->tenant_email)) {
            return back()->with('error', 'This invoice has no tenant email address.');
        }

        try {
            $invoice->load('items');
            Mail::to($invoice->tenant_email)->send(new ProformaInvoiceMail($invoice, $invoice->items));
            $invoice->update([
                'sent_to_tenant_at' => now(),
                'sent_to_tenant_by' => $request->user()?->id,
            ]);
            return back()->with('success', 'Proforma invoice emailed to ' . $invoice->tenant_email . '.');
        } catch (\Exception $e) {
            \Log::warning('Proforma re-send failed for invoice #' . $invoice->id . ': ' . $e->getMessage());
            return back()->with('error', 'Email could not be sent. Please try again.');
        }
    }

    /**
     * Latest rate typed on a proforma for each lease (a hint only, never applied automatically).
     */
    private function attachReferenceRates($leases): void
    {
        $rates = Invoice::whereIn('lease_id', $leases->pluck('id'))
            ->whereNotNull('usd_rate')
            ->orderBy('id')
            ->pluck('usd_rate', 'lease_id'); // later rows overwrite earlier ones => latest wins

        $rebalancer = app(\App\Services\LeaseShareRebalancer::class);

        $leases->each(function ($lease) use ($rates, $rebalancer) {
            $lease->setAttribute('reference_rate', $rates[$lease->id] ?? null);

            // Rent/service charge for the next unbilled period (a co-tenant's share can change over the lease).
            $next = \App\Models\LeaseInstallment::where('lease_id', $lease->id)
                ->whereNull('invoice_id')->orderBy('sequence')->first();
            $share = $next
                ? $rebalancer->monthlyShareFor($lease, Carbon::parse($next->period_start), Carbon::parse($next->period_end)->addDay())
                : null;
            $lease->setAttribute('billing_rent', $share['rent'] ?? null);
            $lease->setAttribute('billing_service_charge', $share['service_charge'] ?? null);
        });
    }

    /**
     * Rate typed on a proforma for a USD-unit lease; required for those, ignored otherwise.
     */
    private function resolveUsdRate(?Lease $lease, $typedRate): ?float
    {
        if (strtoupper((string) ($lease?->currency ?? 'TZS')) !== 'USD') {
            return null;
        }

        if (empty($typedRate) || (float) $typedRate <= 0) {
            throw ValidationException::withMessages([
                'usd_rate' => 'Enter the current USD → TZS exchange rate for this USD unit.',
            ]);
        }

        return round((float) $typedRate, 4);
    }

    private function updateEditableProforma(Request $request, Invoice $invoice): \Illuminate\Http\RedirectResponse
    {
        abort_if($invoice->type !== 'proforma', 422, 'Only proforma invoices can be edited this way.');
        abort_if(!$invoice->isEditable(), 422, 'This proforma can no longer be edited.');

        $effectivePropertyId = $this->shouldScopeToProperty($request) ? $this->effectivePropertyId($request) : null;

        $data = $request->validate([
            'lease_id'     => [
                'nullable',
                Rule::exists('leases', 'id')->when(
                    true,
                    fn($rule) => $rule->where(function ($q) use ($effectivePropertyId) {
                        $q->where('status', 'active');

                        if ($effectivePropertyId) {
                            $q->where('property_id', $effectivePropertyId);
                        }
                    })
                ),
            ],
            'tenant_name'  => 'required|string',
            'tenant_email' => 'nullable|email',
            'unit_ref'     => 'required|string',
            'issued_date'  => 'required|date',
            'due_date'     => 'nullable|date',
            'period'       => 'nullable|string',
            'notes'        => 'nullable|string',
            'status'       => 'nullable|in:draft',
            'usd_rate'      => 'nullable|numeric|min:0.0001|max:999999.9999',
            'items'        => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.quantity'    => 'required|integer|min:1',
            'items.*.unit_price'  => 'required|numeric',
            'items.*.item_type'   => 'nullable|string',
            'items.*.sub_description' => 'nullable|string',
        ]);

        $propertyId = $invoice->property_id;
        $lease = null;
        if (!empty($data['lease_id'])) {
            $lease = Lease::findOrFail($data['lease_id']);
            $propertyId = $lease->property_id;
        }

        if ($effectivePropertyId !== null) {
            abort_if((int) $propertyId !== $effectivePropertyId, 403);
        }

        $approvalStatus = ($data['status'] ?? null) === 'draft' ? 'draft' : 'pending_approval';

        DB::transaction(function () use ($invoice, $data, $propertyId, $lease, $approvalStatus) {
            $usdRate = $this->resolveUsdRate($lease, $data['usd_rate'] ?? null);

            $invoice->update([
                'property_id' => $propertyId,
                'lease_id' => $data['lease_id'] ?? null,
                'currency' => 'TZS',
                'exchange_rate' => 1.0,
                'usd_rate' => $usdRate,
                'tenant_name' => $data['tenant_name'],
                'tenant_email' => $data['tenant_email'] ?? null,
                'unit_ref' => $data['unit_ref'],
                'issued_date' => $data['issued_date'],
                'due_date' => $data['due_date'] ?? null,
                'period' => $data['period'] ?? null,
                'status' => ($data['status'] ?? null) === 'draft' ? 'draft' : 'proforma',
                'notes' => $data['notes'] ?? null,
                'approval_status' => $approvalStatus,
                'approval_requested_at' => $approvalStatus === 'pending_approval' ? now() : null,
                'approval_decided_at' => null,
                'approval_decided_by' => null,
                'approval_note' => null,
            ]);

            $invoice->items()->delete();

            $invoiceTotal = 0.0;
            foreach ($data['items'] as $item) {
                $lineTotal = (float) $item['quantity'] * (float) $item['unit_price'];
                $invoiceTotal += $lineTotal;
                $itemType = $item['item_type']
                    ?? (stripos($item['description'] ?? '', 'service charge') !== false ? 'service_charge' : 'rent');

                InvoiceItem::create([
                    'invoice_id'      => $invoice->id,
                    'description'     => $item['description'],
                    'item_type'       => $itemType,
                    'sub_description' => $item['sub_description'] ?? null,
                    'quantity'        => $item['quantity'],
                    'unit_price'      => $item['unit_price'],
                    'total'           => $lineTotal,
                ]);
            }

            // Recalculate total_in_base
            $vatRate = (float) ($lease?->vat_rate ?? 0);
            $elecItemsVat = (float) $invoice->items()->where('item_type', 'electricity_vat')->sum('total');
            $netItemsTotal = (float) $invoice->items()->get()->reject(fn($i) => in_array($i->item_type, ['electricity_charge', 'electricity_vat']))->sum('total');
            $leaseVat = ($elecItemsVat > 0 || $vatRate <= 0) ? 0.0 : round($netItemsTotal * ($vatRate / 100), 2);
            $grandTotal = $invoiceTotal + $leaseVat;
            $invoice->updateQuietly([
                'total_in_base' => round($grandTotal, 2),
            ]);

            $invoice->load('items');
        });

        $propertyName = Property::where('id', $invoice->property_id)->value('name');
        $this->logAudit(
            request: $request,
            action: 'Invoice edited',
            resource: $invoice->invoice_number,
            propertyName: $propertyName,
            category: 'invoice',
            propertyId: $invoice->property_id ? (int) $invoice->property_id : null,
        );

        return back()->with('success', $approvalStatus === 'draft'
            ? 'Draft invoice updated.'
            : 'Proforma invoice updated and resubmitted for approval.');
    }

    private function ensurePaymentRecordedForInvoice(Invoice $invoice): void
    {
        $invoice->loadMissing(['items', 'lease.tenant', 'lease.unit']);
        $alreadyPaid = (float) Payment::where('invoice_id', $invoice->id)
            ->where('status', 'paid')
            ->sum('amount');
        $grossTotal = $this->invoiceGrossTotal($invoice);
        $remaining = max(0, round($grossTotal - $alreadyPaid, 2));

        if ($remaining <= 0) {
            return;
        }

        $lease = $invoice->lease;
        $tenantId = $lease?->tenant_id ?: Tenant::where('property_id', $invoice->property_id)->where('name', $invoice->tenant_name)->value('id');
        $unitId = $lease?->unit_id ?: Unit::where('property_id', $invoice->property_id)->where('unit_number', $invoice->unit_ref)->value('id');

        if (!$tenantId || !$unitId) {
            return;
        }

        $paymentReceiptService = app(PaymentReceiptService::class);
        $breakdown = $paymentReceiptService->buildPaymentBreakdown($invoice, $remaining);

        Payment::create([
            'property_id'              => $invoice->property_id,
            'invoice_id'               => $invoice->id,
            'tenant_id'                => $tenantId,
            'unit_id'                  => $unitId,
            'month'                    => $invoice->period ?: Carbon::now()->format('M Y'),
            'amount'                   => $remaining,
            'method'                   => 'Bank Transfer',
            'status'                   => 'paid',
            'paid_date'                => Carbon::now()->toDateString(),
            'currency'                 => $invoice->currency ?: 'TZS',
            'breakdown_rent'           => $breakdown['rent'],
            'breakdown_service_charge' => $breakdown['service_charge'],
            'breakdown_electricity'    => $breakdown['electricity'],
            'notes'                    => 'Payment auto-recorded when invoice was marked as paid',
        ]);

        Unit::find($unitId)?->update(['status' => 'occupied']);
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
}
