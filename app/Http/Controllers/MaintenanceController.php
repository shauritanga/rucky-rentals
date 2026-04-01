<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceRecord;
use App\Models\Property;
use App\Models\ScheduledMaintenance;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\MaintenanceApprovalNotification;
use App\Support\AccountingAutoPoster;
use App\Support\MockRentalData;
use App\Traits\LogsAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;

class MaintenanceController extends Controller
{
    use LogsAudit;

    public function index(Request $request)
    {
        $user = $request->user();

        if (MockRentalData::shouldUse() && $user?->role !== 'manager') {
            return Inertia::render('Maintenance/Index', [
                'tickets'        => MockRentalData::maintenanceTickets(),
                'units'          => MockRentalData::units(),
                'scheduledTasks' => [],
                'approvalCount'  => 0,
            ]);
        }

        $tickets = MaintenanceRecord::with('unit')->orderByDesc('reported_date');
        $units   = Unit::query()->orderBy('unit_number');

        $this->scopeByUserProperty($tickets, $request);
        $this->scopeUnitsByUserProperty($units, $request);

        $tickets = $tickets->get();
        $units   = $units->get();

        $scheduledTasks = ScheduledMaintenance::query()
            ->when($this->shouldScopeToProperty($request), function ($q) use ($request) {
                $pid = $this->effectivePropertyId($request);
                if ($pid) $q->where('property_id', $pid);
                else $q->whereRaw('1 = 0');
            })
            ->orderBy('next_due')
            ->get();

        // Approval count — only superuser handles approvals
        $approvalCount = 0;
        if ($user?->role === 'superuser') {
            $propertyId = $this->shouldScopeToProperty($request) ? $this->effectivePropertyId($request) : null;
            $approvalCount = MaintenanceRecord::where('workflow_status', 'submitted')
                ->when($propertyId, fn ($q) => $q->where('property_id', $propertyId))
                ->count();
        }

        return Inertia::render('Maintenance/Index', compact('tickets', 'units', 'scheduledTasks', 'approvalCount'));
    }

    public function store(Request $request)
    {
        $user = $request->user();

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

        $propertyId = $unit?->property_id;
        if ($propertyId === null && $this->shouldScopeToProperty($request)) {
            $propertyId = $this->effectivePropertyId($request);
        }

        abort_if(empty($propertyId), 422, 'Unable to determine property for this ticket.');

        // Superuser acting in property view → auto-approve
        if ($this->isSuperuserActing($request)) {
            $workflowStatus = 'approved';
        } else {
            $workflowStatus = 'submitted';
        }

        $count  = MaintenanceRecord::count() + 1;
        $ticket = MaintenanceRecord::create([
            ...$data,
            'property_id'     => $propertyId,
            'unit_id'         => $unit?->id,
            'ticket_number'   => 'TK-' . str_pad($count, 3, '0', STR_PAD_LEFT),
            'status'          => 'open',
            'workflow_status' => $workflowStatus,
            'reported_by'     => $user?->name ?? 'System',
            'reported_date'   => now()->toDateString(),
            'notes'           => json_encode([]),
        ]);

        // Send notifications — to superuser if submitted, not to accountants
        if ($workflowStatus === 'submitted') {
            $superusers = User::where('role', 'superuser')->get();
            if ($superusers->isNotEmpty()) {
                Notification::send($superusers, new MaintenanceApprovalNotification($ticket, 'submitted'));
            }
        }

        $propertyName = Property::where('id', $propertyId)->value('name');
        $this->logAudit(
            request: $request,
            action: 'Maintenance ticket created',
            resource: $data['title'],
            propertyName: $propertyName,
            category: 'maintenance',
            propertyId: (int) $propertyId,
        );

        return back()->with('success', 'Ticket created.');
    }

    public function update(Request $request, MaintenanceRecord $maintenanceTicket, AccountingAutoPoster $poster)
    {
        $this->authorizeTicketProperty($request, $maintenanceTicket);

        $previousStatus = $maintenanceTicket->status;
        $previousCost   = (float) ($maintenanceTicket->cost ?? 0);

        if ($request->has('note')) {
            $notes        = json_decode($maintenanceTicket->notes ?? '[]', true);
            $noteUser     = $request->user();
            $noteName     = $noteUser?->name ?? 'System';
            $noteWords    = explode(' ', trim($noteName));
            $noteInitials = strtoupper(substr($noteWords[0], 0, 1) . (isset($noteWords[1]) ? substr($noteWords[1], 0, 1) : ''));
            $notes[]      = [
                'author' => $noteName,
                'av'     => $noteInitials,
                'date'   => now()->format('M d'),
                'text'   => $request->input('note'),
            ];
            $maintenanceTicket->update(['notes' => json_encode($notes)]);
        } elseif ($request->has('workflow_status')) {
            $next = $request->input('workflow_status');
            $user = $request->user();

            // Only superuser can approve tickets
            if ($next === 'approved') {
                abort_if($user?->role !== 'superuser', 403, 'Only superuser can approve maintenance tickets.');
            }

            // Sync base status to match workflow stage
            $newStatus = match ($next) {
                'in_progress' => 'in-progress',
                'resolved'    => 'resolved',
                default       => 'open',
            };

            $maintenanceTicket->update([
                'workflow_status' => $next,
                'status'          => $newStatus,
            ]);

            // Send notification when approved
            if ($next === 'approved') {
                $reporter = User::where('name', $maintenanceTicket->reported_by)->first();
                if ($reporter) {
                    $reporter->notify(new MaintenanceApprovalNotification($maintenanceTicket, 'approved'));
                }
            }
        } else {
            $updateData = $request->validate([
                'status'   => 'nullable|string',
                'assignee' => 'nullable|string|max:255',
                'cost'     => 'nullable|numeric|min:0',
            ]);
            $maintenanceTicket->update(array_filter($updateData, fn($v) => $v !== null));

            $currentCost = (float) ($maintenanceTicket->cost ?? 0);
            $propertyId  = $maintenanceTicket->property_id ?? $maintenanceTicket->unit?->property_id;
            $reference   = 'MAINT-' . $maintenanceTicket->id;

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
                                'type'         => 'expense',
                                'category'     => 'Operating Expenses',
                                'debit'        => $currentCost,
                                'credit'       => 0,
                            ],
                            [
                                'account_code' => '2000',
                                'account_name' => 'Accounts Payable',
                                'type'         => 'liability',
                                'category'     => 'Current Liabilities',
                                'debit'        => 0,
                                'credit'       => $currentCost,
                            ],
                        ]
                    );
                }
            }

            if (($previousStatus === 'resolved' && $maintenanceTicket->status !== 'resolved') || ($previousStatus === 'resolved' && $previousCost > 0 && $currentCost <= 0)) {
                $poster->voidByReference($propertyId, $reference);
            }
        }

        $ticketPropertyId = $maintenanceTicket->property_id ?? $maintenanceTicket->unit?->property_id;
        $propertyName     = Property::where('id', $ticketPropertyId)->value('name');
        $isResolved       = $maintenanceTicket->status === 'resolved';
        $this->logAudit(
            request: $request,
            action: $isResolved ? 'Maintenance resolved' : 'Maintenance ticket updated',
            resource: $maintenanceTicket->title,
            propertyName: $propertyName,
            category: 'maintenance',
            propertyId: $ticketPropertyId ? (int) $ticketPropertyId : null,
        );

        return back()->with('success', 'Ticket updated.');
    }

    public function destroy(Request $request, MaintenanceRecord $maintenanceTicket)
    {
        $this->authorizeTicketProperty($request, $maintenanceTicket);

        $propertyId   = $maintenanceTicket->property_id;
        $propertyName = Property::where('id', $propertyId)->value('name');
        $title        = $maintenanceTicket->title;

        $maintenanceTicket->delete();

        $this->logAudit(
            request: $request,
            action: 'Maintenance ticket deleted',
            resource: $title,
            propertyName: $propertyName,
            category: 'maintenance',
            propertyId: $propertyId ? (int) $propertyId : null,
        );

        return back()->with('success', 'Ticket deleted.');
    }

    private function scopeByUserProperty($query, Request $request): void
    {
        if (!$this->shouldScopeToProperty($request)) return;
        $propertyId = $this->effectivePropertyId($request);
        if ($propertyId === null) { $query->whereRaw('1 = 0'); return; }
        $query->where('property_id', $propertyId);
    }

    private function scopeUnitsByUserProperty($query, Request $request): void
    {
        if (!$this->shouldScopeToProperty($request)) return;
        $propertyId = $this->effectivePropertyId($request);
        if ($propertyId === null) { $query->whereRaw('1 = 0'); return; }
        $query->where('property_id', $propertyId);
    }

    private function authorizeTicketProperty(Request $request, MaintenanceRecord $ticket): void
    {
        if (!$this->shouldScopeToProperty($request)) return;
        $effectiveId = $this->effectivePropertyId($request);
        if ($effectiveId === null) return;
        abort_if((int) $ticket->property_id !== $effectiveId, 403);
    }

    private function authorizeUnitProperty(Request $request, ?Unit $unit): void
    {
        if (!$this->shouldScopeToProperty($request) || !$unit) return;
        $effectiveId = $this->effectivePropertyId($request);
        abort_if($effectiveId === null, 422, 'No property context available.');
        abort_if(!Property::where('id', $effectiveId)->exists(), 422, 'Assigned property not found.');
        abort_if((int) $unit->property_id !== $effectiveId, 403);
    }
}
