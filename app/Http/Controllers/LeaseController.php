<?php

namespace App\Http\Controllers;

use App\Models\Lease;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\MockRentalData;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LeaseController extends Controller
{
    public function index()
    {
        if (MockRentalData::shouldUse()) {
            return Inertia::render('Leases/Index', [
                'leases' => MockRentalData::leases(),
                'tenants' => MockRentalData::tenants(),
                'units' => MockRentalData::units(),
            ]);
        }

        $leases  = Lease::with(['tenant', 'unit'])->orderByDesc('created_at')->get();
        $tenants = Tenant::orderBy('name')->get();
        $units   = Unit::orderBy('floor')->orderBy('unit_number')->get();
        return Inertia::render('Leases/Index', compact('leases', 'tenants', 'units'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tenant_mode'     => 'required|in:existing,new',
            'tenant_id'       => 'nullable|required_if:tenant_mode,existing|exists:tenants,id',
            'new_tenant_name' => 'nullable|required_if:tenant_mode,new|string|max:255',
            'new_tenant_email' => 'nullable|required_if:tenant_mode,new|email|max:255|unique:tenants,email',
            'new_tenant_phone' => 'nullable|required_if:tenant_mode,new|string|max:255',
            'new_tenant_national_id' => 'nullable|string|max:255',
            'unit_id'         => 'required|exists:units,id',
            'start_date'      => 'required|date',
            'end_date'        => 'required|date|after:start_date',
            'duration_months' => 'required|integer|min:1',
            'payment_cycle'   => 'required|integer|in:3,4,6,12',
            'monthly_rent'    => 'required|numeric',
            'deposit'         => 'nullable|numeric',
            'terms'           => 'nullable|string',
        ]);

        $tenantId = $validated['tenant_id'] ?? null;

        if (($validated['tenant_mode'] ?? 'existing') === 'new') {
            $words = preg_split('/\s+/', trim($validated['new_tenant_name']));
            $initials = strtoupper(
                substr($words[0] ?? '', 0, 1) .
                    substr($words[1] ?? '', 0, 1)
            );

            $tenant = Tenant::create([
                'name' => $validated['new_tenant_name'],
                'email' => $validated['new_tenant_email'],
                'phone' => $validated['new_tenant_phone'],
                'national_id' => $validated['new_tenant_national_id'] ?? null,
                'initials' => $initials ?: 'NA',
                'color' => 'rgba(59,130,246,.18)',
                'text_color' => 'var(--accent)',
                'nok_name' => null,
                'nok_phone' => null,
                'nok_relation' => null,
                'notes' => null,
            ]);

            $tenantId = $tenant->id;
        }

        $data = [
            'tenant_id' => $tenantId,
            'unit_id' => $validated['unit_id'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'duration_months' => $validated['duration_months'],
            'payment_cycle' => $validated['payment_cycle'],
            'monthly_rent' => $validated['monthly_rent'],
            'deposit' => $validated['deposit'] ?? ($validated['monthly_rent'] * 2),
            'terms' => $validated['terms'] ?? null,
            'status' => 'pending_accountant',
            'approval_log' => json_encode([
                ['step' => 0, 'action' => 'submitted', 'by' => 'James Mwangi (Lease Manager)', 'date' => now()->toDateString(), 'text' => 'Lease submitted for approval.']
            ]),
        ];

        Lease::create($data);
        Unit::find($data['unit_id'])->update(['status' => 'occupied']);
        return back()->with('success', 'Lease created and submitted for approval.');
    }

    public function update(Request $request, Lease $lease)
    {
        $action = $request->input('action');

        if ($action === 'approve_accountant' && $lease->status === 'pending_accountant') {
            $log = json_decode($lease->approval_log, true) ?? [];
            $log[] = ['step' => 1, 'action' => 'approved', 'by' => 'Diana Ochieng (Accountant)', 'date' => now()->toDateString(), 'text' => 'Financials verified. Approved.'];
            $lease->update(['status' => 'pending_pm', 'approval_log' => json_encode($log)]);
        } elseif ($action === 'approve_pm' && $lease->status === 'pending_pm') {
            $log = json_decode($lease->approval_log, true) ?? [];
            $log[] = ['step' => 2, 'action' => 'approved', 'by' => 'James Mwangi (Property Manager)', 'date' => now()->toDateString(), 'text' => 'Final approval. Lease activated.'];
            $lease->update(['status' => 'active', 'approval_log' => json_encode($log)]);
        } elseif ($action === 'reject') {
            $log = json_decode($lease->approval_log, true) ?? [];
            $log[] = ['step' => 0, 'action' => 'rejected', 'by' => 'James Mwangi', 'date' => now()->toDateString(), 'reason' => $request->input('reason', ''), 'text' => 'Lease rejected.'];
            $lease->update(['status' => 'rejected', 'approval_log' => json_encode($log)]);
        } elseif ($action === 'resubmit') {
            $log = json_decode($lease->approval_log, true) ?? [];
            $log[] = ['step' => 0, 'action' => 'submitted', 'by' => 'James Mwangi (Lease Manager)', 'date' => now()->toDateString(), 'text' => 'Lease resubmitted after rejection.'];
            $lease->update(['status' => 'pending_accountant', 'approval_log' => json_encode($log)]);
        }

        return back()->with('success', 'Lease updated.');
    }

    public function destroy(Lease $lease)
    {
        $lease->delete();
        return back()->with('success', 'Lease terminated.');
    }
}
