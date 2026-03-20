<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\Unit;
use App\Support\MockRentalData;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TenantController extends Controller
{
    public function index()
    {
        if (MockRentalData::shouldUse()) {
            return Inertia::render('Tenants/Index', ['tenants' => MockRentalData::tenants()]);
        }

        $tenants = Tenant::with(['leases.unit'])->orderBy('name')->get();
        return Inertia::render('Tenants/Index', ['tenants' => $tenants]);
    }

    public function store(Request $request)
    {
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
        Tenant::create($data);
        return back()->with('success', 'Tenant created.');
    }

    public function update(Request $request, Tenant $tenant)
    {
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
        return back()->with('success', 'Tenant updated.');
    }

    public function destroy(Tenant $tenant)
    {
        $tenant->delete();
        return back()->with('success', 'Tenant deleted.');
    }
}
