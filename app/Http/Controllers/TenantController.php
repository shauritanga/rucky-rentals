<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\Property;
use App\Support\MockRentalData;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TenantController extends Controller
{
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
            'name'         => 'required|string',
            'email'        => 'required|email|unique:tenants',
            'phone'        => 'required|string',
            'national_id'  => 'nullable|string',
            'nok_name'     => 'nullable|string',
            'nok_phone'    => 'nullable|string',
            'nok_relation' => 'nullable|string',
            'notes'        => 'nullable|string',
        ]);
        $words = explode(' ', trim($data['name']));
        $data['initials']   = strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));
        $data['color']      = 'rgba(59,130,246,.18)';
        $data['text_color'] = 'var(--accent)';

        if ($user?->role === 'manager') {
            abort_if(empty($user->property_id), 422, 'Manager is not assigned to any property.');
            abort_if(!Property::where('id', $user->property_id)->exists(), 422, 'Assigned property not found.');
            $data['property_id'] = $user->property_id;
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
            category: 'settings',
        );

        return back()->with('success', 'Tenant created.');
    }

    public function update(Request $request, Tenant $tenant)
    {
        $user = $request->user();
        if ($user?->role === 'manager') {
            abort_if((int) $tenant->property_id !== (int) $user->property_id, 403);
        }

        $data = $request->validate([
            'name'         => 'required|string',
            'email'        => 'required|email|unique:tenants,email,' . $tenant->id,
            'phone'        => 'required|string',
            'national_id'  => 'nullable|string',
            'nok_name'     => 'nullable|string',
            'nok_phone'    => 'nullable|string',
            'nok_relation' => 'nullable|string',
            'notes'        => 'nullable|string',
        ]);
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
            category: 'settings',
        );

        return back()->with('success', 'Tenant updated.');
    }

    public function destroy(Tenant $tenant)
    {
        $request = request();
        $user = $request->user();
        if ($user?->role === 'manager') {
            abort_if((int) $tenant->property_id !== (int) $user->property_id, 403);
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
            category: 'settings',
        );

        return back()->with('success', 'Tenant deleted.');
    }

    private function scopeTenantsForUser($query, Request $request): void
    {
        $user = $request->user();

        if ($user?->role === 'manager') {
            if (empty($user->property_id)) {
                $query->whereRaw('1 = 0');
                return;
            }

            $query->where('property_id', $user->property_id);
        }
    }

    private function logAudit(Request $request, string $action, ?string $resource, ?string $propertyName, string $category, string $result = 'success', array $metadata = []): void
    {
        $actor = $request->user();

        AuditLog::create([
            'user_id' => $actor?->id,
            'user_name' => $actor?->name ?? 'System',
            'action' => $action,
            'resource' => $resource,
            'property_name' => $propertyName,
            'ip_address' => $request->ip(),
            'result' => $result,
            'category' => $category,
            'metadata' => empty($metadata) ? null : $metadata,
        ]);
    }
}
