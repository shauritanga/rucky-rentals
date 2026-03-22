<?php

namespace App\Http\Controllers;

use App\Models\Lease;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\MockRentalData;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class LeaseController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if (MockRentalData::shouldUse() && $user?->role !== 'manager') {
            return Inertia::render('Leases/Index', [
                'leases' => MockRentalData::leases(),
                'tenants' => MockRentalData::tenants(),
                'units' => MockRentalData::units(),
            ]);
        }

        $leasesQuery = Lease::with(['tenant', 'unit'])->orderByDesc('created_at');
        $tenantsQuery = Tenant::query()->orderBy('name');
        $unitsQuery = Unit::query()->orderBy('floor')->orderBy('unit_number');

        $this->scopeByUserProperty($leasesQuery, $request, 'property_id');
        $this->scopeByUserProperty($tenantsQuery, $request, 'property_id');
        $this->scopeByUserProperty($unitsQuery, $request, 'property_id');

        $leases  = $leasesQuery->get();
        $tenants = $tenantsQuery->get();
        $units   = $unitsQuery->get();
        return Inertia::render('Leases/Index', compact('leases', 'tenants', 'units'));
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

        $validated = $request->validate([
            'tenant_mode'     => 'required|in:existing,new',
            'tenant_id'       => [
                'nullable',
                'required_if:tenant_mode,existing',
                Rule::exists('tenants', 'id')->when(
                    $managerPropertyId,
                    fn($rule) => $rule->where(fn($q) => $q->where('property_id', $managerPropertyId))
                ),
            ],
            'new_tenant_name' => 'nullable|required_if:tenant_mode,new|string|max:255',
            'new_tenant_email' => 'nullable|required_if:tenant_mode,new|email|max:255|unique:tenants,email',
            'new_tenant_phone' => 'nullable|required_if:tenant_mode,new|string|max:255',
            'new_tenant_national_id' => 'nullable|string|max:255',
            'unit_id'         => [
                'required',
                Rule::exists('units', 'id')->when(
                    $managerPropertyId,
                    fn($rule) => $rule->where(fn($q) => $q->where('property_id', $managerPropertyId))
                ),
            ],
            'possession_date' => 'nullable|date',
            'rent_start_date' => 'nullable|date',
            'fitout_enabled'  => 'nullable|boolean',
            'fitout_to_date'  => 'nullable|date',
            'fitout_days'     => 'nullable|integer|min:0',
            'wht_rate'        => 'nullable|numeric|min:0|max:100',
            'service_charge_rate' => 'nullable|numeric|min:0|max:100',
            'vat_rate'        => 'nullable|numeric|min:0|max:100',
            'start_date'      => 'required|date',
            'end_date'        => 'required|date|after:start_date',
            'duration_months' => 'required|integer|min:1',
            'payment_cycle'   => 'required|integer|in:3,4,6,12',
            'monthly_rent'    => 'required|numeric',
            'deposit'         => 'nullable|numeric',
            'terms'           => 'nullable|string',
        ]);

        $unit = Unit::findOrFail($validated['unit_id']);
        $propertyId = $unit->property_id;
        abort_if(empty($propertyId), 422, 'Selected unit is not linked to any property.');

        if ($managerPropertyId !== null) {
            abort_if((int) $propertyId !== $managerPropertyId, 403);
        }

        $tenantId = $validated['tenant_id'] ?? null;

        if (($validated['tenant_mode'] ?? 'existing') === 'new') {
            $words = preg_split('/\s+/', trim($validated['new_tenant_name']));
            $initials = strtoupper(
                substr($words[0] ?? '', 0, 1) .
                    substr($words[1] ?? '', 0, 1)
            );

            $tenant = Tenant::create([
                'property_id' => $propertyId,
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

        if (($validated['tenant_mode'] ?? 'existing') === 'existing') {
            $tenant = Tenant::findOrFail($tenantId);
            if ($managerPropertyId !== null) {
                abort_if((int) $tenant->property_id !== $managerPropertyId, 403);
            }
        }

        $data = [
            'property_id' => $propertyId,
            'tenant_id' => $tenantId,
            'unit_id' => $validated['unit_id'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'duration_months' => $validated['duration_months'],
            'payment_cycle' => $validated['payment_cycle'],
            'currency' => $unit->currency ?: 'USD',
            'possession_date' => $validated['possession_date'] ?? $validated['start_date'],
            'rent_start_date' => $validated['rent_start_date'] ?? $validated['start_date'],
            'fitout_enabled' => (bool) ($validated['fitout_enabled'] ?? false),
            'fitout_to_date' => $validated['fitout_to_date'] ?? null,
            'fitout_days' => (int) ($validated['fitout_days'] ?? 0),
            'wht_rate' => (float) ($validated['wht_rate'] ?? 10),
            'service_charge_rate' => (float) ($validated['service_charge_rate'] ?? 5),
            'vat_rate' => (float) ($validated['vat_rate'] ?? 18),
            'monthly_rent' => $validated['monthly_rent'],
            'deposit' => $validated['deposit'] ?? ($validated['monthly_rent'] * 2),
            'terms' => $validated['terms'] ?? null,
            'status' => 'pending_accountant',
            'approval_log' => json_encode([
                ['step' => 0, 'action' => 'submitted', 'by' => 'James Mwangi (Lease Manager)', 'date' => now()->toDateString(), 'text' => 'Lease submitted for approval.']
            ]),
        ];

        Lease::create($data);
        $unit->update(['status' => 'occupied']);
        return back()->with('success', 'Lease created and submitted for approval.');
    }

    public function update(Request $request, Lease $lease)
    {
        $user = $request->user();
        if ($user?->role === 'manager') {
            abort_if((int) $lease->property_id !== (int) $user->property_id, 403);
        }

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
        $user = request()->user();
        if ($user?->role === 'manager') {
            abort_if((int) $lease->property_id !== (int) $user->property_id, 403);
        }

        $lease->delete();
        return back()->with('success', 'Lease terminated.');
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
