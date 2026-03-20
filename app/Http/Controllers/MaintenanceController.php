<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceTicket;
use App\Models\Unit;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MaintenanceController extends Controller
{
    public function index()
    {
        $tickets = MaintenanceTicket::with('unit')->orderByDesc('reported_date')->get();
        $units   = Unit::orderBy('unit_number')->get();
        return Inertia::render('Maintenance/Index', compact('tickets', 'units'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'       => 'required|string',
            'description' => 'nullable|string',
            'unit_ref'    => 'required|string',
            'category'    => 'required|string',
            'priority'    => 'required|in:high,med,low',
            'assignee'    => 'nullable|string',
        ]);
        $unit = Unit::where('unit_number', $data['unit_ref'])->first();
        $count = MaintenanceTicket::count() + 1;
        MaintenanceTicket::create([
            ...$data,
            'unit_id'       => $unit?->id,
            'ticket_number' => 'TK-' . str_pad($count, 3, '0', STR_PAD_LEFT),
            'status'        => 'open',
            'reported_date' => now()->toDateString(),
            'notes'         => json_encode([]),
        ]);
        return back()->with('success', 'Ticket created.');
    }

    public function update(Request $request, MaintenanceTicket $maintenanceTicket)
    {
        $allowed = ['status', 'assignee', 'cost'];
        if ($request->has('note')) {
            $notes = json_decode($maintenanceTicket->notes ?? '[]', true);
            $notes[] = ['author'=>'James Mwangi','av'=>'JM','date'=>now()->format('M d'),'text'=>$request->input('note')];
            $maintenanceTicket->update(['notes' => json_encode($notes)]);
        } else {
            $maintenanceTicket->update($request->only($allowed));
        }
        return back()->with('success', 'Ticket updated.');
    }

    public function destroy(MaintenanceTicket $maintenanceTicket)
    {
        $maintenanceTicket->delete();
        return back()->with('success', 'Ticket deleted.');
    }
}
