<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Models\Tenant;
use App\Models\Lease;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UnitController extends Controller
{
    public function index()
    {
        $units = Unit::with(['leases' => fn($q) => $q->with('tenant')->whereIn('status', ['active','expiring','overdue'])->latest()])->orderBy('floor')->orderBy('unit_number')->get();
        return Inertia::render('Units/Index', ['units' => $units]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'unit_number' => 'required|string|unique:units',
            'floor'       => 'required|integer',
            'type'        => 'required|string',
            'size_sqft'   => 'required|integer',
            'rent'        => 'required|numeric',
            'status'      => 'required|in:occupied,vacant,overdue,maintenance',
            'notes'       => 'nullable|string',
        ]);
        $data['deposit'] = $data['rent'] * 2;
        Unit::create($data);
        return back()->with('success', 'Unit created.');
    }

    public function update(Request $request, Unit $unit)
    {
        $data = $request->validate([
            'unit_number' => 'required|string|unique:units,unit_number,'.$unit->id,
            'floor'       => 'required|integer',
            'type'        => 'required|string',
            'size_sqft'   => 'required|integer',
            'rent'        => 'required|numeric',
            'status'      => 'required|in:occupied,vacant,overdue,maintenance',
            'notes'       => 'nullable|string',
        ]);
        $unit->update($data);
        return back()->with('success', 'Unit updated.');
    }

    public function destroy(Unit $unit)
    {
        $unit->delete();
        return back()->with('success', 'Unit deleted.');
    }
}
