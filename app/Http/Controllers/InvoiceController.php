<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Lease;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\MockRentalData;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InvoiceController extends Controller
{
    public function index()
    {
        if (MockRentalData::shouldUse()) {
            return Inertia::render('Invoices/Index', [
                'invoices' => MockRentalData::invoices(),
                'leases' => MockRentalData::leases(),
                'tenants' => MockRentalData::tenants(),
            ]);
        }

        $invoices = Invoice::with('items')->orderByDesc('created_at')->get();
        $leases   = Lease::with(['tenant', 'unit'])->whereIn('status', ['active', 'expiring', 'overdue', 'pending_accountant', 'pending_pm'])->get();
        $tenants  = Tenant::orderBy('name')->get();
        return Inertia::render('Invoices/Index', compact('invoices', 'leases', 'tenants'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type'         => 'required|in:invoice,proforma',
            'status'       => 'nullable|in:draft',
            'lease_id'     => 'nullable|exists:leases,id',
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

        $count = Invoice::count() + 1;
        $prefix = $data['type'] === 'proforma' ? 'PF' : 'INV';
        $status = ($data['status'] ?? null) === 'draft'
            ? 'draft'
            : ($data['type'] === 'proforma' ? 'proforma' : 'unpaid');

        $invoice = Invoice::create([
            ...$data,
            'invoice_number' => $prefix . '-' . str_pad($count, 4, '0', STR_PAD_LEFT),
            'status'         => $status,
        ]);

        foreach ($data['items'] as $item) {
            InvoiceItem::create([
                'invoice_id'      => $invoice->id,
                'description'     => $item['description'],
                'sub_description' => $item['sub_description'] ?? null,
                'quantity'        => $item['quantity'],
                'unit_price'      => $item['unit_price'],
                'total'           => $item['quantity'] * $item['unit_price'],
            ]);
        }

        return back()->with('success', 'Invoice created.');
    }

    public function update(Request $request, Invoice $invoice)
    {
        $invoice->update($request->only(['status']));
        return back()->with('success', 'Invoice updated.');
    }

    public function destroy(Invoice $invoice)
    {
        $invoice->delete();
        return back()->with('success', 'Invoice deleted.');
    }
}
