<?php

namespace App\Http\Controllers;

use App\Mail\ProformaInvoiceMail;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Lease;
use App\Models\LeaseInstallment;
use App\Models\Property;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\ExchangeRate;
use App\Services\InvoiceNumberService;
use App\Support\MockRentalData;
use App\Traits\LogsAudit;
use Barryvdh\DomPDF\Facade\Pdf;
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

        // Business rule: lease invoices are always proforma.
        // GL posts on payment when they convert to Tax Invoices, not at creation.
        if (!empty($data['lease_id'])) {
            $data['type'] = 'proforma';
        }

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

            $invoice = Invoice::create([
                ...$data,
                'property_id'    => $propertyId,
                'invoice_number' => $this->invoiceNumberService->generateNumber($prefix),
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

        $createdInvoice = Invoice::with('items')->find($createdInvoiceId);

        // Send proforma email automatically on creation
        if ($createdInvoice?->type === 'proforma' && !empty($createdInvoice->tenant_email)) {
            try {
                Mail::to($createdInvoice->tenant_email)
                    ->send(new ProformaInvoiceMail($createdInvoice, $createdInvoice->items));
            } catch (\Exception $e) {
                \Log::warning('Proforma invoice email failed for invoice #' . $createdInvoiceId . ': ' . $e->getMessage());
            }
        }

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

        $original = $invoice->getOriginal();
        $invoice->update($data);

        // Promote type from proforma → invoice on conversion (quiet update avoids re-firing observer)
        if (
            ($original['status'] ?? '') === 'proforma' &&
            $invoice->status !== 'proforma' &&
            $invoice->type === 'proforma'
        ) {
            $invoice->updateQuietly(['type' => 'invoice']);
        }

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

        // ── Company & property details (mirrors ProformaInvoiceMail::generatePdf) ──
        $companyName     = SystemSetting::get('company_name', 'Ruky Rentals');
        $companyEmail    = SystemSetting::get('support_email', '');
        $vatNumber       = SystemSetting::get('vat_number', '');
        $companyReg      = SystemSetting::get('company_registration', '');
        $property        = $invoice->property_id ? Property::find($invoice->property_id) : null;
        $companyPhone    = $property?->phone ?? '';
        $bankName        = $property?->bank_name ?? '';
        $bankAccount     = $property?->bank_account ?? '';
        $bankAccountName = $property?->bank_account_name ?? '';
        $swiftCode       = $property?->swift_code ?? '';
        $tenantPhone     = '';
        $tenantId        = null;
        $vatRate         = 0;

        if ($invoice->lease_id) {
            $lease = $invoice->relationLoaded('lease') ? $invoice->lease : $invoice->load('lease')->lease;
            if ($lease) {
                $tenantId    = $lease->tenant_id;
                $vatRate     = (float) ($lease->vat_rate ?? 0);
                $tenant      = Tenant::find($tenantId);
                $tenantPhone = $tenant?->phone ?? '';
            }
        }
        if (!$tenantPhone && $invoice->tenant_email) {
            $tenant      = Tenant::where('email', $invoice->tenant_email)->first();
            $tenantPhone = $tenant?->phone ?? '';
            $tenantId    = $tenantId ?? $tenant?->id;
        }

        $invoiceLabel = $invoice->type === 'proforma' ? 'PROFORMA INVOICE' : 'TAX INVOICE';
        $items        = $invoice->items;
        $tenantUnit   = $invoice->unit_ref ?? '';

        $pdfContent = Pdf::loadView('pdf.proforma-invoice', compact(
            'invoice', 'items', 'property',
            'companyName', 'companyEmail', 'companyPhone',
            'vatNumber', 'companyReg',
            'tenantUnit', 'tenantPhone',
            'bankName', 'bankAccount', 'bankAccountName', 'swiftCode',
            'vatRate', 'invoiceLabel',
        ))->setPaper('a4', 'portrait')->output();

        $filename    = $invoice->invoice_number . '.pdf';
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
    public function send(Invoice $invoice): \Illuminate\Http\RedirectResponse
    {
        if ($invoice->type !== 'proforma') {
            return back()->with('error', 'Only proforma invoices can be re-sent this way.');
        }

        if (empty($invoice->tenant_email)) {
            return back()->with('error', 'This invoice has no tenant email address.');
        }

        try {
            $invoice->load('items');
            Mail::to($invoice->tenant_email)->send(new ProformaInvoiceMail($invoice, $invoice->items));
            return back()->with('success', 'Proforma invoice emailed to ' . $invoice->tenant_email . '.');
        } catch (\Exception $e) {
            \Log::warning('Proforma re-send failed for invoice #' . $invoice->id . ': ' . $e->getMessage());
            return back()->with('error', 'Email could not be sent. Please try again.');
        }
    }
}
