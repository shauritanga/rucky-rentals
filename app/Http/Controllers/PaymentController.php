<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Property;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\MockRentalData;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PaymentController extends Controller
{
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
            'invoice_id' => [
                'nullable',
                Rule::exists('invoices', 'id')->when(
                    $managerPropertyId,
                    fn($rule) => $rule->where(fn($q) => $q->where('property_id', $managerPropertyId))
                ),
            ],
            'tenant_id' => [
                'required',
                Rule::exists('tenants', 'id')->when(
                    $managerPropertyId,
                    fn($rule) => $rule->where(fn($q) => $q->where('property_id', $managerPropertyId))
                ),
            ],
            'unit_id'   => [
                'required',
                Rule::exists('units', 'id')->when(
                    $managerPropertyId,
                    fn($rule) => $rule->where(fn($q) => $q->where('property_id', $managerPropertyId))
                ),
            ],
            'month'     => 'required|string',
            'amount'    => 'required|numeric',
            'method'    => 'nullable|string',
            'reference' => 'nullable|string|max:255',
            'status'    => 'required|in:paid,overdue,pending',
            'paid_date' => 'nullable|date',
            'notes'     => 'nullable|string',
        ]);

        $unit = Unit::findOrFail($data['unit_id']);
        $tenant = Tenant::findOrFail($data['tenant_id']);

        $propertyId = (int) ($unit->property_id ?: $tenant->property_id ?: 0);

        if ($managerPropertyId !== null) {
            abort_if(!$propertyId, 422, 'Selected unit/tenant is not linked to any property.');
        }

        if ($managerPropertyId !== null) {
            abort_if($propertyId !== $managerPropertyId, 403);
        }

        $data['property_id'] = $propertyId;

        DB::transaction(function () use ($data) {
            $payment = Payment::create($data);

            if ($data['status'] === 'paid') {
                Unit::find($data['unit_id'])?->update(['status' => 'occupied']);
            }

            $this->reconcileInvoiceStatus($payment->invoice_id);
        });

        return back()->with('success', 'Payment recorded.');
    }

    public function update(Request $request, Payment $payment)
    {
        $this->authorizePaymentProperty($request, $payment);
        $payment->update($request->only(['status', 'method', 'paid_date']));

        $this->reconcileInvoiceStatus($payment->invoice_id);

        return back()->with('success', 'Payment updated.');
    }

    public function destroy(Payment $payment)
    {
        $this->authorizePaymentProperty(request(), $payment);

        $invoiceId = $payment->invoice_id;
        $payment->delete();

        $this->reconcileInvoiceStatus($invoiceId);

        return back()->with('success', 'Payment deleted.');
    }

    private function reconcileInvoiceStatus(?int $invoiceId): void
    {
        if (empty($invoiceId)) return;

        $invoice = Invoice::with('items')->find($invoiceId);
        if (!$invoice || $invoice->type === 'proforma') return;

        $invoiceTotal = (float) $invoice->items->sum(fn($item) => (float) $item->total);
        $paidTotal = (float) Payment::where('invoice_id', $invoice->id)->sum('amount');

        if ($invoiceTotal > 0 && $paidTotal + 0.00001 >= $invoiceTotal) {
            $invoice->status = 'paid';
        } elseif ($paidTotal > 0) {
            $invoice->status = 'partially_paid';
        } else {
            $isOverdue = !empty($invoice->due_date)
                && Carbon::today()->gt(Carbon::parse($invoice->due_date)->startOfDay());
            $invoice->status = $isOverdue ? 'overdue' : 'unpaid';
        }

        $invoice->save();
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

    private function authorizePaymentProperty(Request $request, Payment $payment): void
    {
        $user = $request->user();
        if ($user?->role !== 'manager') return;

        $paymentPropertyId = $payment->property_id ?: $payment->unit?->property_id;
        abort_if((int) $paymentPropertyId !== (int) $user->property_id, 403);
    }
}
