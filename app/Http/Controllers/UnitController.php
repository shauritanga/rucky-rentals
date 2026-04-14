<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Models\Tenant;
use App\Models\Lease;
use App\Models\Property;
use App\Models\SystemSetting;
use App\Support\FloorConfig;
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
        'Restaurant',
        'Clinic',
        'Salon',
        'Store',
    ];

    public function index(Request $request)
    {
        $user = $request->user();
        $floorOptions = $this->resolveFloorOptions($request);
        $canCreateUnit = count($floorOptions) > 0;

        $settings = SystemSetting::pluck('value', 'key');

        if (MockRentalData::shouldUse() && $user?->role !== 'manager') {
            return Inertia::render('Units/Index', [
                'units'         => MockRentalData::units(),
                'floorOptions'  => $floorOptions,
                'canCreateUnit' => $canCreateUnit,
                'settings'      => $settings,
            ]);
        }

        $unitsQuery = Unit::query()
            ->with(['leases' => fn($q) => $q->with('tenant')->whereIn('status', ['active', 'expiring', 'overdue'])->latest()]);

        $this->scopeUnitsForUser($unitsQuery, $request);

        $units = $unitsQuery->orderBy('unit_number')->get()
            ->sortBy(fn ($u) => FloorConfig::sortOrder($u->floor ?? ''))
            ->values();
        return Inertia::render('Units/Index', [
            'units'         => $units,
            'floorOptions'  => $floorOptions,
            'canCreateUnit' => $canCreateUnit,
            'settings'      => $settings,
        ]);
    }

    public function store(Request $request)
    {
        $effectivePropertyId = $this->shouldScopeToProperty($request)
            ? $this->effectivePropertyId($request)
            : null;
        $validFloorIds = [];

        if ($this->shouldScopeToProperty($request)) {
            abort_if($effectivePropertyId === null, 422, 'No property context available.');
            $managerProperty = Property::find($effectivePropertyId);
            abort_if(!$managerProperty, 422, 'Assigned property not found.');
            $validFloorIds = FloorConfig::floorIds(FloorConfig::parse($managerProperty->floor_config));
        }

        $floorRule = $validFloorIds
            ? ['required', 'string', Rule::in($validFloorIds)]
            : ['required', 'string'];

        $unitNumberUniqueRule = Rule::unique('units', 'unit_number')
            ->where(fn ($query) => $effectivePropertyId === null
                ? $query->whereNull('property_id')
                : $query->where('property_id', $effectivePropertyId));

        $data = $request->validate([
            'unit_number'            => ['required', 'string', $unitNumberUniqueRule],
            'floor'                  => $floorRule,
            'type'                   => ['required', 'string', Rule::in(self::COMMERCIAL_UNIT_TYPES)],
            'size_sqm'               => 'required|numeric|min:0.1',
            'rate_per_sqm'           => 'required|numeric|min:0',
            'service_charge_per_sqm' => 'nullable|numeric|min:0',
            'currency'               => 'required|in:TZS,USD',
            'status'                 => 'required|in:occupied,vacant,overdue,maintenance',
            'electricity_type'       => 'nullable|in:direct,submeter',
            'notes'                  => 'nullable|string',
        ]);

        $data['size_sqm']               = (float) $data['size_sqm'];
        $data['rate_per_sqm']           = (float) $data['rate_per_sqm'];
        $data['size_sqft']              = (int) round($data['size_sqm'] / self::SQM_PER_SQFT);
        $data['rent']                   = round($data['size_sqm'] * $data['rate_per_sqm'], 2);
        $data['service_charge_per_sqm'] = (float) ($data['service_charge_per_sqm'] ?? 0);
        $data['service_charge']         = round($data['size_sqm'] * $data['service_charge_per_sqm'], 2);
        $data['electricity_type']       = $data['electricity_type'] ?? 'direct';
        $rentMonths                     = (float) SystemSetting::get('deposit_rent_months', 1);
        $scMonths                       = (float) SystemSetting::get('deposit_service_charge_months', 1);
        $data['deposit']                = round(($data['rent'] * $rentMonths) + ($data['service_charge'] * $scMonths), 2);

        if ($this->shouldScopeToProperty($request)) {
            $data['property_id'] = $effectivePropertyId;
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
        $effectivePropertyId = $this->shouldScopeToProperty($request)
            ? $this->effectivePropertyId($request)
            : ($unit->property_id ? (int) $unit->property_id : null);
        $validFloorIds = [];
        if ($this->shouldScopeToProperty($request)) {
            abort_if($effectivePropertyId !== null && (int) $unit->property_id !== $effectivePropertyId, 403);
            $managerProperty = $effectivePropertyId ? Property::find($effectivePropertyId) : null;
            if ($managerProperty) {
                $validFloorIds = FloorConfig::floorIds(FloorConfig::parse($managerProperty->floor_config));
            }
        }

        $floorRule = $validFloorIds
            ? ['required', 'string', Rule::in($validFloorIds)]
            : ['required', 'string'];

        $unitNumberUniqueRule = Rule::unique('units', 'unit_number')
            ->ignore($unit->id)
            ->where(fn ($query) => $effectivePropertyId === null
                ? $query->whereNull('property_id')
                : $query->where('property_id', $effectivePropertyId));

        $data = $request->validate([
            'unit_number'            => ['required', 'string', $unitNumberUniqueRule],
            'floor'                  => $floorRule,
            'type'                   => 'required|string',
            'size_sqm'               => 'required|numeric|min:0.1',
            'rate_per_sqm'           => 'required|numeric|min:0',
            'service_charge_per_sqm' => 'nullable|numeric|min:0',
            'currency'               => 'required|in:TZS,USD',
            'status'                 => 'required|in:occupied,vacant,overdue,maintenance',
            'electricity_type'       => 'nullable|in:direct,submeter',
            'notes'                  => 'nullable|string',
        ]);

        $data['size_sqm']               = (float) $data['size_sqm'];
        $data['rate_per_sqm']           = (float) $data['rate_per_sqm'];
        $data['size_sqft']              = (int) round($data['size_sqm'] / self::SQM_PER_SQFT);
        $data['rent']                   = round($data['size_sqm'] * $data['rate_per_sqm'], 2);
        $data['service_charge_per_sqm'] = (float) ($data['service_charge_per_sqm'] ?? 0);
        $data['service_charge']         = round($data['size_sqm'] * $data['service_charge_per_sqm'], 2);
        $data['electricity_type']       = $data['electricity_type'] ?? 'direct';
        $rentMonths                     = (float) SystemSetting::get('deposit_rent_months', 1);
        $scMonths                       = (float) SystemSetting::get('deposit_service_charge_months', 1);
        $data['deposit']                = round(($data['rent'] * $rentMonths) + ($data['service_charge'] * $scMonths), 2);

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
            return $property->floorList();
        }
        // Default for superuser without a property context
        return FloorConfig::floors(FloorConfig::parse(null));
    }

}
