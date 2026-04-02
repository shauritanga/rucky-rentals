<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\Unit;
use App\Models\Property;
use App\Support\MockRentalData;
use App\Traits\LogsAudit;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TenantController extends Controller
{
    use LogsAudit;
    public function index(Request $request)
    {
        $user = $request->user();

        if (MockRentalData::shouldUse() && $user?->role !== 'manager') {
            return Inertia::render('Tenants/Index', ['tenants' => MockRentalData::tenants()]);
        }

        $tenantsQuery = Tenant::query()->with([
            'leases.unit',
            'payments' => fn($q) => $q->orderByDesc('paid_date')->orderByDesc('created_at'),
        ]);
        $this->scopeTenantsForUser($tenantsQuery, $request);

        $tenants = $tenantsQuery->orderBy('name')->get();
        return Inertia::render('Tenants/Index', ['tenants' => $tenants]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'tenant_type'         => 'required|in:individual,company',
            'name'                => 'required_if:tenant_type,individual|nullable|string|max:255',
            'national_id'         => 'nullable|string|max:100',
            'nok_name'            => 'nullable|string|max:255',
            'nok_phone'           => 'nullable|string|max:50',
            'nok_relation'        => 'nullable|string|max:100',
            'company_name'        => 'required_if:tenant_type,company|nullable|string|max:255',
            'registration_number' => 'nullable|string|max:100',
            'tin'                 => 'required_if:tenant_type,company|nullable|string|max:50',
            'vrn'                 => 'nullable|string|max:50',
            'contact_person'      => 'required_if:tenant_type,company|nullable|string|max:255',
            'email'               => 'required|email|unique:tenants',
            'phone'               => 'required|string|max:50',
            'notes'               => 'nullable|string',
        ]);

        if ($data['tenant_type'] === 'company') {
            $data['name'] = $data['company_name'];
            $words = explode(' ', trim($data['company_name']));
            $data['national_id']  = null;
            $data['nok_name']     = null;
            $data['nok_phone']    = null;
            $data['nok_relation'] = null;
        } else {
            $words = explode(' ', trim($data['name']));
            $data['company_name']        = null;
            $data['registration_number'] = null;
            $data['tin']                 = null;
            $data['vrn']                 = null;
            $data['contact_person']      = null;
        }
        $data['initials']   = strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));
        $data['color']      = 'rgba(59,130,246,.18)';
        $data['text_color'] = 'var(--accent)';

        if ($this->shouldScopeToProperty($request)) {
            $propertyId = $this->effectivePropertyId($request);
            abort_if($propertyId === null, 422, 'No property context available.');
            abort_if(!Property::where('id', $propertyId)->exists(), 422, 'Assigned property not found.');
            $data['property_id'] = $propertyId;
        }

        $tenant = Tenant::create($data);

        $propertyName = null;
        if (!empty($tenant->property_id)) {
            $propertyName = Property::where('id', $tenant->property_id)->value('name');
        }

        $this->logAudit(
            request: $request,
            action: 'Tenant created',
            resource: sprintf('%s (%s)', $tenant->name, $tenant->email),
            propertyName: $propertyName,
            category: 'tenant',
            propertyId: $tenant->property_id ? (int) $tenant->property_id : null,
        );

        return back()->with('success', 'Tenant created.');
    }

    public function update(Request $request, Tenant $tenant)
    {
        if ($this->shouldScopeToProperty($request)) {
            $effectiveId = $this->effectivePropertyId($request);
            abort_if($effectiveId !== null && (int) $tenant->property_id !== $effectiveId, 403);
        }

        $data = $request->validate([
            'tenant_type'         => 'required|in:individual,company',
            'name'                => 'required_if:tenant_type,individual|nullable|string|max:255',
            'national_id'         => 'nullable|string|max:100',
            'nok_name'            => 'nullable|string|max:255',
            'nok_phone'           => 'nullable|string|max:50',
            'nok_relation'        => 'nullable|string|max:100',
            'company_name'        => 'required_if:tenant_type,company|nullable|string|max:255',
            'registration_number' => 'nullable|string|max:100',
            'tin'                 => 'required_if:tenant_type,company|nullable|string|max:50',
            'vrn'                 => 'nullable|string|max:50',
            'contact_person'      => 'required_if:tenant_type,company|nullable|string|max:255',
            'email'               => 'required|email|unique:tenants,email,' . $tenant->id,
            'phone'               => 'required|string|max:50',
            'notes'               => 'nullable|string',
        ]);

        if ($data['tenant_type'] === 'company') {
            $data['name'] = $data['company_name'];
            $data['national_id']  = null;
            $data['nok_name']     = null;
            $data['nok_phone']    = null;
            $data['nok_relation'] = null;
        } else {
            $data['company_name']        = null;
            $data['registration_number'] = null;
            $data['tin']                 = null;
            $data['vrn']                 = null;
            $data['contact_person']      = null;
        }

        $tenant->update($data);

        $propertyName = null;
        if (!empty($tenant->property_id)) {
            $propertyName = Property::where('id', $tenant->property_id)->value('name');
        }

        $this->logAudit(
            request: $request,
            action: 'Tenant updated',
            resource: sprintf('%s (%s)', $tenant->name, $tenant->email),
            propertyName: $propertyName,
            category: 'tenant',
            propertyId: $tenant->property_id ? (int) $tenant->property_id : null,
        );

        return back()->with('success', 'Tenant updated.');
    }

    public function destroy(Tenant $tenant)
    {
        $request = request();
        if ($this->shouldScopeToProperty($request)) {
            $effectiveId = $this->effectivePropertyId($request);
            abort_if($effectiveId !== null && (int) $tenant->property_id !== $effectiveId, 403);
        }

        $propertyName = null;
        if (!empty($tenant->property_id)) {
            $propertyName = Property::where('id', $tenant->property_id)->value('name');
        }

        $resource = sprintf('%s (%s)', $tenant->name, $tenant->email);

        $tenant->delete();

        $this->logAudit(
            request: $request,
            action: 'Tenant deleted',
            resource: $resource,
            propertyName: $propertyName,
            category: 'tenant',
            propertyId: $tenant->property_id ? (int) $tenant->property_id : null,
        );

        return back()->with('success', 'Tenant deleted.');
    }

    private function scopeTenantsForUser($query, Request $request): void
    {
        if (!$this->shouldScopeToProperty($request)) return;
        $propertyId = $this->effectivePropertyId($request);
        if ($propertyId === null) { $query->whereRaw('1 = 0'); return; }
        $query->where('property_id', $propertyId);
    }
}
