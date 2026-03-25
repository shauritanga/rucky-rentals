<?php

namespace App\Http\Controllers;

use App\Models\ScheduledMaintenance;
use App\Models\Unit;
use Illuminate\Http\Request;

class ScheduledMaintenanceController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'title'     => 'required|string',
            'unit_ref'  => 'nullable|string',
            'category'  => 'required|string',
            'frequency' => 'required|in:weekly,monthly,quarterly,biannual,annual',
            'next_due'  => 'required|date',
            'assignee'  => 'nullable|string',
            'notes'     => 'nullable|string',
        ]);

        $unit       = !empty($data['unit_ref']) ? Unit::where('unit_number', $data['unit_ref'])->first() : null;
        $propertyId = $unit?->property_id ?? $request->user()?->property_id;

        abort_if(empty($propertyId), 422, 'Unable to determine property for this task.');

        ScheduledMaintenance::create([
            ...$data,
            'property_id' => $propertyId,
            'unit_id'     => $unit?->id,
            'status'      => 'upcoming',
        ]);

        return back()->with('success', 'Task scheduled.');
    }

    public function update(Request $request, ScheduledMaintenance $scheduledMaintenance)
    {
        $data = $request->validate([
            'status'    => 'sometimes|in:upcoming,overdue,completed',
            'next_due'  => 'sometimes|date',
            'assignee'  => 'sometimes|nullable|string',
        ]);

        $scheduledMaintenance->update($data);

        return back()->with('success', 'Task updated.');
    }

    public function destroy(ScheduledMaintenance $scheduledMaintenance)
    {
        $scheduledMaintenance->delete();

        return back()->with('success', 'Task deleted.');
    }
}
