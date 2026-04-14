<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Returns the property ID to scope all queries/writes to.
     * - Manager: their assigned property_id (null = no access)
     * - Superuser in property-view mode: the session property_id
     * - Superuser not in property-view mode: null (sees everything)
     */
    protected function effectivePropertyId(Request $request): ?int
    {
        $user = $request->user();
        if ($user?->role === 'superuser') {
            $id = $request->session()->get('superuser_viewing_property_id');
            return $id ? (int) $id : null;
        }
        return empty($user?->property_id) ? null : (int) $user->property_id;
    }

    /**
     * True when the current request must be scoped to a specific property.
     * Always true for managers. True for superusers only when in property-view mode.
     */
    protected function shouldScopeToProperty(Request $request): bool
    {
        $user = $request->user();
        return $user?->role === 'manager'
            || ($user?->role === 'superuser' && (bool) $request->session()->get('superuser_viewing_property_id'));
    }

    /**
     * True when a superuser is operating inside a specific property view.
     * Used to auto-approve workflows and bypass approval chains.
     */
    protected function isSuperuserActing(Request $request): bool
    {
        return $request->user()?->role === 'superuser'
            && (bool) $request->session()->get('superuser_viewing_property_id');
    }

    /**
     * Resolve a unit reference without guessing across properties.
     * In property-scoped contexts, resolve inside that property only.
     * Outside property scope, only resolve when the reference matches exactly one unit globally.
     */
    protected function resolveUnitByReference(Request $request, ?string $unitRef): ?Unit
    {
        $unitRef = trim((string) $unitRef);
        if ($unitRef === '') {
            return null;
        }

        $query = Unit::query()->where('unit_number', $unitRef);

        if ($this->shouldScopeToProperty($request)) {
            $propertyId = $this->effectivePropertyId($request);
            if ($propertyId === null) {
                return null;
            }

            return $query->where('property_id', $propertyId)->first();
        }

        $matches = $query->limit(2)->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }
}
