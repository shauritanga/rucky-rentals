<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\MockRentalData;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class InvoiceController extends Controller
{
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
        $leasesQuery = Lease::with(['tenant', 'unit'])
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
        $user = $request->user();
        $managerPropertyId = null;

        if ($user?->role === 'manager') {
            abort_if(empty($user->property_id), 422, 'Manager is not assigned to any property.');
            abort_if(!Property::where('id', $user->property_id)->exists(), 422, 'Assigned property not found.');
            $managerPropertyId = (int) $user->property_id;
        }

        $data = $request->validate([
            'type'         => 'required|in:invoice,proforma',
            'status'       => 'nullable|in:draft',
            'lease_id'     => [
                'nullable',
                Rule::exists('leases', 'id')->when(
                    true,
                    fn($rule) => $rule->where(function ($q) use ($managerPropertyId) {
                        $q->where('status', 'active');

                        if ($managerPropertyId) {
                            $q->where('property_id', $managerPropertyId);
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

        $count = Invoice::count() + 1;
        $prefix = $data['type'] === 'proforma' ? 'PF' : 'INV';
        $status = ($data['status'] ?? null) === 'draft'
            ? 'draft'
            : ($data['type'] === 'proforma' ? 'proforma' : 'unpaid');

        $propertyId = null;
        if (!empty($data['lease_id'])) {
            $lease = Lease::findOrFail($data['lease_id']);
            $propertyId = $lease->property_id;
        }

        if ($managerPropertyId !== null) {
            if ($propertyId === null) {
                $propertyId = $managerPropertyId;
            }

            abort_if((int) $propertyId !== $managerPropertyId, 403);
        }

        $invoice = Invoice::create([
            ...$data,
            'property_id' => $propertyId,
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

        return back()
            ->with('success', 'Invoice created.')
            ->with('created_invoice_id', $invoice->id);
    }

    public function update(Request $request, Invoice $invoice)
    {
        $user = $request->user();
        if ($user?->role === 'manager') {
            abort_if((int) $invoice->property_id !== (int) $user->property_id, 403);
        }

        $data = $request->validate([
            'status' => 'required|in:draft,proforma,unpaid,partially_paid,paid,overdue',
        ]);

        $invoice->update($data);
        return back()->with('success', 'Invoice updated.');
    }

    public function destroy(Invoice $invoice)
    {
        $user = request()->user();
        if ($user?->role === 'manager') {
            abort_if((int) $invoice->property_id !== (int) $user->property_id, 403);
        }

        $invoice->delete();
        return back()->with('success', 'Invoice deleted.');
    }

    private function scopeByUserProperty($query, Request $request, string $column): void
    {
        $user = $request->user();

        if ($user?->role === 'manager') {
            if (empty($user->property_id)) {
                $query->whereRaw('1 = 0');
                return;
            }

            $query->where($column, $user->property_id);
        }
    }
}
