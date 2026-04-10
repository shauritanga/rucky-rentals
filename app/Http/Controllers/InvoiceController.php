<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Lease;
use App\Models\LeaseInstallment;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\ExchangeRate;
use App\Support\MockRentalData;
use App\Traits\LogsAudit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class InvoiceController extends Controller
{
    use LogsAudit;
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

        $invoicesQuery = Invoice::with('items')->orderByDesc('created_at');
        $leasesQuery = Lease::with(['tenant', 'unit', 'installments'])
            ->where('status', 'active');
        $tenantsQuery = Tenant::query()->orderBy('name');

        $this->scopeByUserProperty($invoicesQuery, $request, 'property_id');
        $this->scopeByUserProperty($leasesQuery, $request, 'property_id');
        $this->scopeByUserProperty($tenantsQuery, $request, 'property_id');

        $invoices = $invoicesQuery->get();
        $leases   = $leasesQuery->get();
        $tenants  = $tenantsQuery->get();
        return Inertia::render('Invoices/Index', compact('invoices', 'leases', 'tenants'));
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
            'items'        => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.quantity'    => 'required|integer|min:1',
            'items.*.unit_price'  => 'required|numeric',
        ]);

        $status = ($data['status'] ?? null) === 'draft'
            ? 'draft'
            : ($data['type'] === 'proforma' ? 'proforma' : 'unpaid');

        $propertyId = null;
        if (!empty($data['lease_id'])) {
            $lease = Lease::findOrFail($data['lease_id']);
            $propertyId = $lease->property_id;
        }

        if ($effectivePropertyId !== null) {
            if ($propertyId === null) {
                $propertyId = $effectivePropertyId;
            }
            abort_if((int) $propertyId !== $effectivePropertyId, 403);
        }

        $createdInvoiceId = null;

        DB::transaction(function () use ($data, $propertyId, $status, &$createdInvoiceId) {
            $prefix = $data['type'] === 'proforma' ? 'PF' : 'INV';
            $nextNumber = $this->nextInvoiceSequenceValue();

            $invoice = Invoice::create([
                ...$data,
                'property_id' => $propertyId,
                'invoice_number' => $prefix . '-' . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT),
                'status'         => $status,
            ]);
            $createdInvoiceId = $invoice->id;

            // Set currency from lease if invoice is lease-linked
            if ($invoice->lease_id) {
                $lease = Lease::find($invoice->lease_id);
                if ($lease) {
                    $currency = $lease->currency ?? 'TZS';
                    $invoice->update(['currency' => $currency]);

                    // Pre-populate exchange rate for audit trail
                    if ($currency !== 'TZS') {
                        $rate = ExchangeRate::getRate(
                            propertyId: null,
                            fromCurrency: $currency,
                            toCurrency: 'TZS',
                            date: $invoice->issued_date ?? now()
                        );

                        if ($rate) {
                            // Will be recalculated during postInvoice, but store for reference
                            $invoice->update(['exchange_rate' => $rate]);
                        }
                    }
                }
            }

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

            $this->attachInvoiceToInstallment($invoice);

            // Refresh invoice to load newly created items, then post to GL.
            // We call postInvoice() explicitly here rather than relying on InvoiceObserver::created()
            // because the observer fires on Invoice::create() before items exist, so it sees an
            // empty items collection and returns without posting.
            $invoice->load('items');
            app(\App\Services\AccountingService::class)->postInvoice($invoice);
        });

        $createdInvoice = Invoice::find($createdInvoiceId);
        $propertyName   = Property::where('id', $propertyId)->value('name');
        $this->logAudit(
            request: $request,
            action: 'Invoice created',
            resource: $createdInvoice?->invoice_number ?? ('INV #' . $createdInvoiceId),
            propertyName: $propertyName,
            category: 'invoice',
            propertyId: $propertyId ? (int) $propertyId : null,
        );

        return back()
            ->with('success', 'Invoice created.')
            ->with('created_invoice_id', $createdInvoiceId);
    }

    public function update(Request $request, Invoice $invoice)
    {
        if ($this->shouldScopeToProperty($request)) {
            $effectiveId = $this->effectivePropertyId($request);
            abort_if($effectiveId !== null && (int) $invoice->property_id !== $effectiveId, 403);
        }

        $data = $request->validate([
            'status' => 'required|in:draft,proforma,unpaid,partially_paid,paid,overdue',
        ]);

        $invoice->update($data);

        // Observer handles status transitions (post/void as appropriate)

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

    private function nextInvoiceSequenceValue(): int
    {
        $this->lockInvoiceNumberSequence();

        $maxNumber = 0;

        foreach (Invoice::query()->select('invoice_number')->lockForUpdate()->pluck('invoice_number') as $invoiceNumber) {
            if (preg_match('/(\d+)$/', (string) $invoiceNumber, $matches) === 1) {
                $maxNumber = max($maxNumber, (int) $matches[1]);
            }
        }

        return $maxNumber + 1;
    }

    private function lockInvoiceNumberSequence(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::select('SELECT pg_advisory_xact_lock(?)', [856331]);
        }
    }
}
