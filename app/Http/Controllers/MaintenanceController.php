<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\MaintenanceRecord;
use App\Models\Property;
use App\Models\ScheduledMaintenance;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
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

        $tickets = MaintenanceRecord::with(['unit', 'documents'])->orderByDesc('reported_date');
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

        // Approval count — for superuser in view mode, show pending tickets for that property
        $approvalCount = 0;
        if ($user?->role === 'superuser' && $this->shouldScopeToProperty($request)) {
            $propertyId = $this->effectivePropertyId($request);
            $approvalCount = MaintenanceRecord::whereIn('workflow_status', ['submitted', 'pending_manager'])
                ->when($propertyId, fn ($q) => $q->where('property_id', $propertyId))
                ->count();
        } elseif ($user?->role === 'accountant') {
            $approvalCount = MaintenanceRecord::where('workflow_status', 'submitted')
                ->when(!empty($user->property_id), fn ($q) => $q->where('property_id', $user->property_id))
                ->count();
        } elseif ($user?->role === 'manager') {
            $approvalCount = MaintenanceRecord::where('workflow_status', 'pending_manager')
                ->when(!empty($user->property_id), fn ($q) => $q->where('property_id', $user->property_id))
                ->count();
        }

        return Inertia::render('Maintenance/Index', compact('tickets', 'units', 'scheduledTasks', 'approvalCount'));
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'title'              => 'required|string|max:255',
            'description'        => 'nullable|string',
            'unit_ref'           => 'required|string',
            'category'           => 'required|string',
            'priority'           => 'required|in:high,med,low,critical',
            'assignee'           => 'nullable|string|max:255',
            'cost'               => 'nullable|numeric|min:0',
            'materials'          => 'nullable|array',
            'materials.*.name'   => 'required|string|max:255',
            'materials.*.unit'   => 'nullable|string|max:50',
            'materials.*.qty'    => 'required|numeric|min:0',
            'materials.*.unit_price' => 'required|numeric|min:0',
            'images'             => 'nullable|array',
            'images.*'           => 'file|image|max:5120',
        ]);

        // Property always comes from the authenticated user's context — not inferred from the unit.
        // The unit lookup is only used to capture unit_id for reference.
        $propertyId = $this->effectivePropertyId($request)
            ?? $request->user()?->property_id;

        abort_if(empty($propertyId), 422, 'No property context found. Please select a property first.');

        $unit = Unit::where('unit_number', $data['unit_ref'])
            ->where('property_id', $propertyId)
            ->first();

        // Superuser acting in property view → auto-approve
        if ($this->isSuperuserActing($request)) {
            $workflowStatus = 'approved';
        } else {
            $workflowStatus = 'submitted';
        }

        $count  = MaintenanceRecord::count() + 1;
        $ticket = MaintenanceRecord::create([
            'title'           => $data['title'],
            'description'     => $data['description'] ?? null,
            'unit_ref'        => $data['unit_ref'],
            'category'        => $data['category'],
            'priority'        => $data['priority'],
            'assignee'        => $data['assignee'] ?? null,
            'cost'            => !empty($data['cost']) ? $data['cost'] : null,
            'materials'       => !empty($data['materials']) ? $data['materials'] : null,
            'property_id'     => $propertyId,
            'unit_id'         => $unit?->id,
            'ticket_number'   => 'TK-' . str_pad($count, 3, '0', STR_PAD_LEFT),
            'status'          => 'open',
            'workflow_status' => $workflowStatus,
            'reported_by'     => $user?->name ?? 'System',
            'reported_date'   => now()->toDateString(),
            'notes'           => json_encode([]),
        ]);

        // Save uploaded images as Document records
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('maintenance/' . $ticket->id, 'public');
                Document::create([
                    'maintenance_record_id' => $ticket->id,
                    'name'          => $image->getClientOriginalName(),
                    'file_path'     => $path,
                    'file_type'     => $image->getClientOriginalExtension() ?: 'jpg',
                    'file_size'     => round($image->getSize() / 1024, 1) . ' KB',
                    'tag'           => 'maintenance',
                    'document_type' => 'maintenance_image',
                    'unit_ref'      => $ticket->unit_ref,
                    'uploaded_by'   => $user?->name ?? 'System',
                ]);
            }
        }

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
            if (in_array($next, ['approved', 'pending_manager'])) {
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
            $allowed = ['status', 'assignee', 'cost'];
            $maintenanceTicket->update($request->only($allowed));

            // GL posting for status transitions (open→resolved, resolved→open) is handled
            // exclusively by MaintenanceRecordObserver to avoid double-posting.
            //
            // The controller only handles the case the observer cannot: a cost amount change
            // while the ticket is ALREADY resolved (status stays 'resolved', only cost changes).
            $currentCost = (float) ($maintenanceTicket->cost ?? 0);
            $propertyId  = $maintenanceTicket->property_id ?? $maintenanceTicket->unit?->property_id;
            $reference   = 'MAINT-' . $maintenanceTicket->id;

            if ($previousStatus === 'resolved' && $maintenanceTicket->status === 'resolved') {
                if ($currentCost <= 0) {
                    // Cost zeroed out on an already-resolved ticket — void the GL entry.
                    $poster->voidByReference($propertyId, $reference);
                } elseif (abs($previousCost - $currentCost) > 0.00001) {
                    // Cost changed while already resolved — void old, re-post with new amount.
                    $poster->voidByReference($propertyId, $reference);
                    $poster->post(
                        propertyId: $propertyId,
                        entryDate: now()->toDateString(),
                        description: 'Maintenance expense updated',
                        reference: $reference,
                        sourceType: 'maintenance',
                        sourceId: $maintenanceTicket->id,
                        lines: [
                            ['account_code' => '5000', 'debit' => $currentCost, 'credit' => 0],
                            ['account_code' => '2000', 'debit' => 0,            'credit' => $currentCost],
                        ]
                    );
                }
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
