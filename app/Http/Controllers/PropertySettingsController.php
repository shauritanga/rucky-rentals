<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Traits\LogsAudit;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PropertySettingsController extends Controller
{
    use LogsAudit;

    public function show(Request $request): \Inertia\Response
    {
        $propertyId = $this->effectivePropertyId($request);
        abort_if($propertyId === null, 403, 'No property context.');

        $property = Property::findOrFail($propertyId);

        return Inertia::render('Settings/Index', [
            'property' => $property,
        ]);
    }

    public function update(Request $request): \Illuminate\Http\RedirectResponse
    {
        $propertyId = $this->effectivePropertyId($request);
        abort_if($propertyId === null, 403, 'No property context.');

        $property = Property::findOrFail($propertyId);

        $data = $request->validate([
            'address'           => 'nullable|string|max:255',
            'city'              => 'nullable|string|max:120',
            'country'           => 'nullable|string|max:120',
            'phone'             => 'nullable|string|max:30',
            'bank_name'         => 'nullable|string|max:120',
            'bank_account'      => 'nullable|string|max:60',
            'bank_account_name' => 'nullable|string|max:120',
            'swift_code'        => 'nullable|string|max:20',
        ]);

        $property->update($data);

        $this->logAudit(
            request: $request,
            action: 'Property settings updated',
            resource: $property->name,
            propertyName: $property->name,
            category: 'settings',
            propertyId: $propertyId,
        );

        return back()->with('success', 'Settings saved.');
    }
}
