<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Models\Tenant;
use App\Models\Lease;
use App\Support\MockRentalData;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UnitController extends Controller
{
    private const SQM_PER_SQFT = 0.09290304;
    private const COMMERCIAL_UNIT_TYPES = [
        'Office Suite',
        'Retail Shop',
        'Showroom',
        'Warehouse',
        'Restaurant Space',
        'Clinic Space',
        'Salon Space',
        'Kiosk',
    ];

    public function index()
    {
        if (MockRentalData::shouldUse()) {
            return Inertia::render('Units/Index', ['units' => MockRentalData::units()]);
        }

        $units = Unit::with(['leases' => fn($q) => $q->with('tenant')->whereIn('status', ['active', 'expiring', 'overdue'])->latest()])->orderBy('floor')->orderBy('unit_number')->get();
        return Inertia::render('Units/Index', ['units' => $units]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'unit_number' => 'required|string|unique:units',
            'floor'       => 'required|integer',
            'type'        => ['required', 'string', Rule::in(self::COMMERCIAL_UNIT_TYPES)],
            'size_sqm'    => 'required|numeric|min:0.1',
            'rate_per_sqm' => 'required|numeric|min:0',
            'currency'    => 'required|in:TZS,USD',
            'status'      => 'required|in:occupied,vacant,overdue,maintenance',
            'notes'       => 'nullable|string',
        ]);

        $data['size_sqm'] = (float) $data['size_sqm'];
        $data['rate_per_sqm'] = (float) $data['rate_per_sqm'];
        $data['size_sqft'] = (int) round($data['size_sqm'] / self::SQM_PER_SQFT);
        $data['rent'] = round($data['size_sqm'] * $data['rate_per_sqm'], 2);
        $data['deposit'] = round($data['rent'] * 2, 2);

        Unit::create($data);
        return back()->with('success', 'Unit created.');
    }

    public function update(Request $request, Unit $unit)
    {
        $data = $request->validate([
            'unit_number' => 'required|string|unique:units,unit_number,' . $unit->id,
            'floor'       => 'required|integer',
            'type'        => 'required|string',
            'size_sqm'    => 'required|numeric|min:0.1',
            'rate_per_sqm' => 'required|numeric|min:0',
            'currency'    => 'required|in:TZS,USD',
            'status'      => 'required|in:occupied,vacant,overdue,maintenance',
            'notes'       => 'nullable|string',
        ]);

        $data['size_sqm'] = (float) $data['size_sqm'];
        $data['rate_per_sqm'] = (float) $data['rate_per_sqm'];
        $data['size_sqft'] = (int) round($data['size_sqm'] / self::SQM_PER_SQFT);
        $data['rent'] = round($data['size_sqm'] * $data['rate_per_sqm'], 2);
        $data['deposit'] = round($data['rent'] * 2, 2);

        $unit->update($data);
        return back()->with('success', 'Unit updated.');
    }

    public function destroy(Unit $unit)
    {
        $unit->delete();
        return back()->with('success', 'Unit deleted.');
    }
}
