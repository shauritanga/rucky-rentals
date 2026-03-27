<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Models\Tenant;
use App\Models\Lease;
use App\Models\Property;
use App\Support\MockRentalData;
use App\Traits\LogsAudit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UnitController extends Controller
{
    use LogsAudit;
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

    public function index(Request $request)
    {
        $user = $request->user();
        $floorOptions = $this->resolveFloorOptions($request);
        $canCreateUnit = count($floorOptions) > 0;

        if (MockRentalData::shouldUse() && $user?->role !== 'manager') {
            return Inertia::render('Units/Index', [
                'units' => MockRentalData::units(),
                'floorOptions' => $floorOptions,
                'canCreateUnit' => $canCreateUnit,
            ]);
        }

        $unitsQuery = Unit::query()
            ->with(['leases' => fn($q) => $q->with('tenant')->whereIn('status', ['active', 'expiring', 'overdue'])->latest()]);

        $this->scopeUnitsForUser($unitsQuery, $request);

        $units = $unitsQuery->orderBy('floor')->orderBy('unit_number')->get();
        return Inertia::render('Units/Index', [
            'units' => $units,
            'floorOptions' => $floorOptions,
            'canCreateUnit' => $canCreateUnit,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $managerProperty = null;
        $maxFloor = null;

        if ($this->shouldScopeToProperty($request)) {
            $effectiveId = $this->effectivePropertyId($request);
            abort_if($effectiveId === null, 422, 'No property context available.');
            $managerProperty = Property::find($effectiveId);
            abort_if(!$managerProperty, 422, 'Assigned property not found.');
            $maxFloor = max(1, (int) ($managerProperty->total_floors ?? 1));
        }

        $data = $request->validate([
            'unit_number' => 'required|string|unique:units',
            'floor'       => array_filter(['required', 'integer', 'min:1', $maxFloor ? 'max:' . $maxFloor : null]),
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

        if ($this->shouldScopeToProperty($request)) {
            $data['property_id'] = $this->effectivePropertyId($request);
        }

        $unit = Unit::create($data);

        $propertyName = null;
        if (!empty($data['property_id'])) {
            $propertyName = Property::where('id', $data['property_id'])->value('name');
        }

        $this->logAudit(
            request: $request,
            action: 'Unit created',
            resource: $unit->unit_number,
            propertyName: $propertyName,
            category: 'settings',
            propertyId: $unit->property_id ? (int) $unit->property_id : null,
        );

        return back()->with('success', 'Unit created.');
    }

    public function update(Request $request, Unit $unit)
    {
        $user = $request->user();
        $maxFloor = null;
        if ($this->shouldScopeToProperty($request)) {
            $effectiveId = $this->effectivePropertyId($request);
            abort_if($effectiveId !== null && (int) $unit->property_id !== $effectiveId, 403);
            $managerProperty = $effectiveId ? Property::find($effectiveId) : null;
            $maxFloor = $managerProperty ? max(1, (int) ($managerProperty->total_floors ?? 1)) : null;
        }

        $data = $request->validate([
            'unit_number' => 'required|string|unique:units,unit_number,' . $unit->id,
            'floor'       => array_filter(['required', 'integer', 'min:1', $maxFloor ? 'max:' . $maxFloor : null]),
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

        $propertyName = null;
        if (!empty($unit->property_id)) {
            $propertyName = Property::where('id', $unit->property_id)->value('name');
        }

        $this->logAudit(
            request: $request,
            action: 'Unit updated',
            resource: $unit->unit_number,
            propertyName: $propertyName,
            category: 'settings',
            propertyId: $unit->property_id ? (int) $unit->property_id : null,
        );

        return back()->with('success', 'Unit updated.');
    }

    public function destroy(Unit $unit)
    {
        $request = request();
        if ($this->shouldScopeToProperty($request)) {
            $effectiveId = $this->effectivePropertyId($request);
            abort_if($effectiveId !== null && (int) $unit->property_id !== $effectiveId, 403);
        }

        $propertyName = null;
        if (!empty($unit->property_id)) {
            $propertyName = Property::where('id', $unit->property_id)->value('name');
        }

        $unitNumber = $unit->unit_number;

        $unit->delete();

        $this->logAudit(
            request: $request,
            action: 'Unit deleted',
            resource: $unitNumber,
            propertyName: $propertyName,
            category: 'settings',
            propertyId: $unit->property_id ? (int) $unit->property_id : null,
        );

        return back()->with('success', 'Unit deleted.');
    }

    private function scopeUnitsForUser($query, Request $request): void
    {
        if (!$this->shouldScopeToProperty($request)) return;
        $propertyId = $this->effectivePropertyId($request);
        if ($propertyId === null) { $query->whereRaw('1 = 0'); return; }
        $query->where('property_id', $propertyId);
    }

    private function resolveFloorOptions(Request $request): array
    {
        if ($this->shouldScopeToProperty($request)) {
            $propertyId = $this->effectivePropertyId($request);
            if ($propertyId === null) return [];
            $property = Property::find($propertyId);
            if (!$property) return [];
            $maxFloor = max(1, (int) ($property->total_floors ?? 1));
            return range(1, $maxFloor);
        }
        return range(1, 7);
    }

}
