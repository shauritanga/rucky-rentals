<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\MockRentalData;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PaymentController extends Controller
{
    public function index()
    {
        if (MockRentalData::shouldUse()) {
            return Inertia::render('Payments/Index', [
                'payments' => MockRentalData::payments(),
                'tenants' => MockRentalData::tenants(),
                'units' => MockRentalData::units(),
            ]);
        }

        $payments = Payment::with(['tenant', 'unit'])->orderByDesc('created_at')->get();
        $tenants  = Tenant::orderBy('name')->get();
        $units    = Unit::orderBy('unit_number')->get();
        return Inertia::render('Payments/Index', compact('payments', 'tenants', 'units'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'tenant_id' => 'required|exists:tenants,id',
            'unit_id'   => 'required|exists:units,id',
            'month'     => 'required|string',
            'amount'    => 'required|numeric',
            'method'    => 'nullable|string',
            'status'    => 'required|in:paid,overdue,pending',
            'paid_date' => 'nullable|date',
        ]);
        Payment::create($data);
        if ($data['status'] === 'paid') {
            Unit::find($data['unit_id'])->update(['status' => 'occupied']);
        }
        return back()->with('success', 'Payment recorded.');
    }

    public function update(Request $request, Payment $payment)
    {
        $payment->update($request->only(['status', 'method', 'paid_date']));
        return back()->with('success', 'Payment updated.');
    }

    public function destroy(Payment $payment)
    {
        $payment->delete();
        return back()->with('success', 'Payment deleted.');
    }
}
