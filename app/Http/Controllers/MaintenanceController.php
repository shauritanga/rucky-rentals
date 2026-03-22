<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceTicket;
use App\Models\Property;
use App\Models\Unit;
use App\Support\AccountingAutoPoster;
use App\Support\MockRentalData;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MaintenanceController extends Controller
{
    public function index(Request $request)
    {
        if (MockRentalData::shouldUse()) {
            return Inertia::render('Maintenance/Index', [
                'tickets' => MockRentalData::maintenanceTickets(),
                'units' => MockRentalData::units(),
            ]);
        }

        $tickets = MaintenanceTicket::with('unit')->orderByDesc('reported_date');
        $units   = Unit::query()->orderBy('unit_number');

        $this->scopeByUserProperty($tickets, $request);
        $this->scopeUnitsByUserProperty($units, $request);

        $tickets = $tickets->get();
        $units = $units->get();

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
        $this->authorizeUnitProperty($request, $unit);

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

    public function update(Request $request, MaintenanceTicket $maintenanceTicket, AccountingAutoPoster $poster)
    {
        $this->authorizeTicketProperty($request, $maintenanceTicket);

        $previousStatus = $maintenanceTicket->status;
        $previousCost = (float) ($maintenanceTicket->cost ?? 0);

        $allowed = ['status', 'assignee', 'cost'];
        if ($request->has('note')) {
            $notes = json_decode($maintenanceTicket->notes ?? '[]', true);
            $notes[] = ['author' => 'James Mwangi', 'av' => 'JM', 'date' => now()->format('M d'), 'text' => $request->input('note')];
            $maintenanceTicket->update(['notes' => json_encode($notes)]);
        } else {
            $maintenanceTicket->update($request->only($allowed));

            $currentCost = (float) ($maintenanceTicket->cost ?? 0);
            $propertyId = $maintenanceTicket->unit?->property_id;
            $reference = 'MAINT-' . $maintenanceTicket->id;

            if ($maintenanceTicket->status === 'resolved' && $currentCost > 0) {
                if ($previousStatus === 'resolved' && abs($previousCost - $currentCost) > 0.00001) {
                    $poster->voidByReference($propertyId, $reference);
                }

                if ($previousStatus !== 'resolved' || $previousCost <= 0 || abs($previousCost - $currentCost) > 0.00001) {
                    $poster->post(
                        propertyId: $propertyId,
                        entryDate: now()->toDateString(),
                        description: 'Maintenance expense recognized',
                        reference: $reference,
                        sourceType: 'maintenance',
                        sourceId: $maintenanceTicket->id,
                        lines: [
                            [
                                'account_code' => '5000',
                                'account_name' => 'Maintenance Expense',
                                'type' => 'expense',
                                'category' => 'Operating Expenses',
                                'debit' => $currentCost,
                                'credit' => 0,
                            ],
                            [
                                'account_code' => '2000',
                                'account_name' => 'Accounts Payable',
                                'type' => 'liability',
                                'category' => 'Current Liabilities',
                                'debit' => 0,
                                'credit' => $currentCost,
                            ],
                        ]
                    );
                }
            }

            if (($previousStatus === 'resolved' && $maintenanceTicket->status !== 'resolved') || ($previousStatus === 'resolved' && $previousCost > 0 && $currentCost <= 0)) {
                $poster->voidByReference($propertyId, $reference);
            }
        }

        return back()->with('success', 'Ticket updated.');
    }

    public function destroy(Request $request, MaintenanceTicket $maintenanceTicket)
    {
        $this->authorizeTicketProperty($request, $maintenanceTicket);
        $maintenanceTicket->delete();
        return back()->with('success', 'Ticket deleted.');
    }

    private function scopeByUserProperty($query, Request $request): void
    {
        $user = $request->user();

        if ($user?->role === 'manager') {
            if (empty($user->property_id)) {
                $query->whereRaw('1 = 0');
                return;
            }

            $query->whereHas('unit', fn($u) => $u->where('property_id', $user->property_id));
        }
    }

    private function scopeUnitsByUserProperty($query, Request $request): void
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

    private function authorizeTicketProperty(Request $request, MaintenanceTicket $ticket): void
    {
        $user = $request->user();
        if ($user?->role !== 'manager') {
            return;
        }

        $ticketPropertyId = $ticket->unit?->property_id;
        abort_if((int) $ticketPropertyId !== (int) $user->property_id, 403);
    }

    private function authorizeUnitProperty(Request $request, ?Unit $unit): void
    {
        $user = $request->user();
        if ($user?->role !== 'manager' || !$unit) {
            return;
        }

        abort_if(empty($user->property_id), 422, 'Manager is not assigned to any property.');
        abort_if(!Property::where('id', $user->property_id)->exists(), 422, 'Assigned property not found.');
        abort_if((int) $unit->property_id !== (int) $user->property_id, 403);
    }
}
